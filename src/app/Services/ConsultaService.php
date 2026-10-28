<?php
namespace App\Services;

use App\Repositories\CitaRepository;
use App\Repositories\ConsultaVeterinariaRepository;
use App\Repositories\HistorialMascotaRepository;
use App\Repositories\VeterinarioRepository;
use DateTimeImmutable;
use PDOException;

/**
 * ConsultaService — US-06 (T-044), ARQ-01, DAT-01, SEC-01, ACC-01
 * Reglas de la atención clínica:
 *
 *  - Solo el veterinario autenticado, y solo sobre una cita suya en estado Programada.
 *  - Diagnóstico y tratamiento obligatorios; observaciones y servicios adicionales opcionales.
 *  - La consulta y el paso de la cita a Atendida se guardan en UNA transacción: o quedan
 *    las dos cosas o ninguna. La fila de la cita se bloquea mientras tanto.
 *  - No se crea más de una consulta por cita (además lo impide un UNIQUE en la base).
 *  - La consulta NO se copia a `historial_mascota`: el historial consolidado la lee
 *    directamente de `consultas_veterinarias`, así aparece una sola vez.
 */
class ConsultaService {

    /** Longitud máxima de cada campo de texto clínico. */
    public const CAMPO_MAX = 2000;

    /** Máximo de citas mostradas en la agenda del veterinario. */
    public const LIMITE_AGENDA = 200;

    /** Eventos mostrados en el historial consolidado de la pantalla de consulta. */
    public const LIMITE_HISTORIAL = 10;

    /** Filtros de estado admitidos en la agenda del veterinario. */
    public const ESTADOS_AGENDA = ['Programada', 'Atendida', 'Cancelada', 'Todas'];

    public function __construct(
        private ?CitaRepository $citaRepo = null,
        private ?ConsultaVeterinariaRepository $consultaRepo = null,
        private ?VeterinarioRepository $veterinarioRepo = null,
        private ?HistorialMascotaRepository $historialRepo = null
    ) {
        $this->citaRepo        ??= new CitaRepository();
        $this->consultaRepo    ??= new ConsultaVeterinariaRepository();
        $this->veterinarioRepo ??= new VeterinarioRepository();
        $this->historialRepo   ??= new HistorialMascotaRepository();
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Agenda y pantalla de consulta
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Citas del veterinario autenticado (por defecto Programadas), de la más próxima a la más lejana.
     *
     * @return array{veterinario: ?array, citas: array, truncado: bool, estado?: string}
     */
    public function agendaDelVeterinario(mixed $idUsuario, mixed $estado = 'Programada'): array {
        $vet = $this->resolverVeterinario($idUsuario);
        if ($vet === null) {
            return ['veterinario' => null, 'citas' => [], 'truncado' => false];
        }

        $estadoFiltro = (is_string($estado) && in_array($estado, self::ESTADOS_AGENDA, true))
            ? $estado
            : 'Programada';

        $filas = $this->citaRepo->listDetalle(
            $estadoFiltro === 'Todas' ? null : $estadoFiltro,
            self::LIMITE_AGENDA + 1,
            (int) $vet['id_veterinario']
        );
        $truncado = count($filas) > self::LIMITE_AGENDA;

        return [
            'veterinario' => $vet,
            'citas'       => $truncado ? array_slice($filas, 0, self::LIMITE_AGENDA) : $filas,
            'truncado'    => $truncado,
            'estado'      => $estadoFiltro,
        ];
    }

    /**
     * Datos para mostrar el formulario; falla si la cita no se puede atender.
     *
     * @return array{success: bool, message: string, cita: ?array, historial: array}
     */
    public function prepararConsulta(mixed $idUsuario, mixed $idCita): array {
        $vet = $this->resolverVeterinario($idUsuario);
        if ($vet === null) {
            return self::noPreparada('Tu cuenta no tiene un perfil de veterinario activo.');
        }

        $id = self::aEntero($idCita);
        if ($id <= 0) {
            return self::noPreparada('La cita indicada no es válida.');
        }

        $cita = $this->citaRepo->findDetalleById($id);
        if (!$cita) {
            return self::noPreparada('La cita no existe.');
        }

        $error = $this->evaluarCita($cita, $vet);
        if ($error !== null) {
            return self::noPreparada($error);
        }

        return [
            'success'   => true,
            'message'   => '',
            'cita'      => $cita,
            'historial' => $this->historialClinico((int) $cita['id_mascota']),
        ];
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Registro de la consulta
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Guarda la consulta y pasa la cita a Atendida de forma atómica.
     * La mascota y el veterinario salen de la cita guardada, nunca del cliente.
     *
     * @param array{diagnostico?:mixed,observaciones?:mixed,tratamiento?:mixed,servicios_adicionales?:mixed} $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int, reintentable: bool}
     *         `reintentable`: el formulario puede corregirse y reenviarse (conserva lo escrito).
     */
    public function registrarConsulta(mixed $idUsuario, mixed $idCita, array $datos, ?DateTimeImmutable $ahora = null): array {
        $ahora = $ahora ?? new DateTimeImmutable();

        $vet = $this->resolverVeterinario($idUsuario);
        if ($vet === null) {
            return self::fallo('Tu cuenta no tiene un perfil de veterinario activo.');
        }

        $id = self::aEntero($idCita);
        if ($id <= 0) {
            return self::fallo('La cita indicada no es válida.');
        }

        // Comprobación previa (sin bloqueo) para dar un mensaje claro cuanto antes.
        $cita = $this->citaRepo->findDetalleById($id);
        if (!$cita) {
            return self::fallo('La cita no existe.');
        }
        $error = $this->evaluarCita($cita, $vet);
        if ($error !== null) {
            return self::fallo($error);
        }

        $errors = [];
        $campos = self::normalizarCampos($datos, $errors);
        if ($errors !== []) {
            return self::fallo('Revisa los campos señalados.', $errors, true);
        }

        $idVet = (int) $vet['id_veterinario'];

        try {
            return $this->citaRepo->transaccion(function () use ($id, $idVet, $campos, $ahora): array {
                // 1) Bloquear la cita: serializa a quien registra, cancela o atiende a la vez.
                $bloqueada = $this->citaRepo->bloquearPorId($id);
                if ($bloqueada === null) {
                    return self::fallo('La cita no existe.');
                }

                // 2) Revalidar con la cita ya bloqueada.
                if ($bloqueada['estado'] !== 'Programada') {
                    return self::fallo(self::mensajeEstado((string) $bloqueada['estado']));
                }
                if ((int) $bloqueada['id_veterinario'] !== $idVet) {
                    return self::fallo('Esta cita no está asignada a tu usuario.');
                }
                if ($this->consultaRepo->existsForCita($id)) {
                    return self::fallo('Ya existe una consulta para esta cita.');
                }

                // 3) Guardar la consulta (mascota y veterinario salen de la cita).
                $idConsulta = $this->consultaRepo->create([
                    'id_cita'               => $id,
                    'id_mascota'            => (int) $bloqueada['id_mascota'],
                    'id_veterinario'        => (int) $bloqueada['id_veterinario'],
                    'fecha_atencion'        => $ahora->format('Y-m-d H:i:s'),
                    'diagnostico'           => $campos['diagnostico'],
                    'observaciones'         => $campos['observaciones'],
                    'tratamiento'           => $campos['tratamiento'],
                    'servicios_adicionales' => $campos['servicios_adicionales'],
                ]);

                // 4) Pasar la cita a Atendida. Si no cambió, se revierte la consulta.
                if (!$this->citaRepo->marcarAtendidaSiProgramada($id)) {
                    throw new ConsultaNoGuardadaException('La cita ya no está Programada; no se guardó la consulta.');
                }

                return [
                    'success'      => true,
                    'message'      => 'Consulta registrada. La cita quedó como Atendida.',
                    'errors'       => [],
                    'id'           => $idConsulta,
                    'reintentable' => false,
                ];
            });
        } catch (ConsultaNoGuardadaException $e) {
            return self::fallo($e->getMessage());
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return self::fallo('Ya existe una consulta para esta cita.');
            }
            error_log('[ConsultaService] ' . $e->getMessage());
            return self::fallo(
                'No se pudo guardar la consulta y no se realizó ningún cambio. Intenta de nuevo.',
                [],
                true
            );
        }
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Historial clínico consolidado
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Línea de tiempo de una mascota: consultas (desde `consultas_veterinarias`) y
     * antecedentes previos (desde `historial_mascota`), de lo más reciente a lo más antiguo.
     * Cada consulta aparece una sola vez porque no se copia a `historial_mascota`.
     *
     * @return array<array<string,mixed>>
     */
    public function historialClinico(int $idMascota, int $limite = self::LIMITE_HISTORIAL): array {
        $eventos = [];

        foreach ($this->consultaRepo->listByMascota($idMascota, $limite) as $c) {
            $clave = 'consulta-' . $c['id_consulta'];
            $eventos[$clave] = [
                'tipo'                  => 'Consulta',
                'fecha'                 => (string) $c['fecha_atencion'],
                'orden'                 => (string) $c['fecha_atencion'],
                'titulo'                => (string) $c['diagnostico'],
                'tratamiento'           => (string) ($c['tratamiento'] ?? ''),
                'observaciones'         => $c['observaciones'] ?? null,
                'servicios_adicionales' => $c['servicios_adicionales'] ?? null,
                'veterinario'           => (string) ($c['veterinario_nombre'] ?? ''),
                'servicio'              => (string) ($c['nombre_servicio'] ?? ''),
            ];
        }

        foreach ($this->historialRepo->listByMascota($idMascota, $limite) as $a) {
            $clave = 'antecedente-' . $a['id_historial_mascota'];
            $fecha = $a['fecha_registro_antecedente'] ?? null;
            $eventos[$clave] = [
                'tipo'        => 'Antecedente',
                'fecha'       => $fecha !== null ? (string) $fecha : '',
                'orden'       => $fecha !== null ? $fecha . ' 00:00:00' : '',
                'titulo'      => (string) $a['condicion_medica'],
                'tratamiento' => '',
                'detalle'     => $a['descripcion_problema'] ?? null,
                'alergias'    => $a['alergias'] ?? null,
            ];
        }

        $lista = array_values($eventos);
        usort($lista, static fn(array $x, array $y): int => strcmp($y['orden'], $x['orden']));

        return array_slice($lista, 0, $limite);
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Internos
    // ────────────────────────────────────────────────────────────────── //

    /** @return array<string,mixed>|null Perfil de veterinario activo del usuario autenticado. */
    private function resolverVeterinario(mixed $idUsuario): ?array {
        $id = self::aEntero($idUsuario);
        return $id > 0 ? $this->veterinarioRepo->findActivoByUsuarioId($id) : null;
    }

    /**
     * Motivo por el que NO se puede registrar la consulta, o null si se puede.
     *
     * @param array<string,mixed> $cita Cita con detalle
     * @param array<string,mixed> $vet  Perfil del veterinario autenticado
     */
    private function evaluarCita(array $cita, array $vet): ?string {
        if ($cita['estado'] !== 'Programada') {
            return self::mensajeEstado((string) $cita['estado']);
        }
        if ((int) $cita['id_veterinario'] !== (int) $vet['id_veterinario']) {
            return 'Esta cita no está asignada a tu usuario.';
        }
        if ($this->consultaRepo->existsForCita((int) $cita['id_cita'])) {
            return 'Ya existe una consulta para esta cita.';
        }
        return null;
    }

    private static function mensajeEstado(string $estado): string {
        return match ($estado) {
            'Cancelada' => 'La cita fue cancelada; no se puede registrar una consulta.',
            'Atendida'  => 'La cita ya fue atendida; no se puede registrar otra consulta.',
            default     => sprintf('Solo se puede registrar una consulta en una cita Programada (esta cita está %s).', $estado),
        };
    }

    /**
     * Normaliza los cuatro campos clínicos y rellena $errors por campo.
     *
     * @param array<string,mixed>  $datos
     * @param array<string,string> $errors
     * @return array{diagnostico: ?string, observaciones: ?string, tratamiento: ?string, servicios_adicionales: ?string}
     */
    private static function normalizarCampos(array $datos, array &$errors): array {
        $reglas = [
            'diagnostico'           => [true,  'El diagnóstico',            'El diagnóstico es obligatorio.'],
            'tratamiento'           => [true,  'El tratamiento',            'El tratamiento es obligatorio.'],
            'observaciones'         => [false, 'Las observaciones',         ''],
            'servicios_adicionales' => [false, 'Los servicios adicionales', ''],
        ];

        $res = [];
        foreach ($reglas as $campo => [$obligatorio, $etiqueta, $msgVacio]) {
            $valor = $datos[$campo] ?? null;

            if ($valor !== null && (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8'))) {
                $errors[$campo] = $etiqueta . ' no es válido.';
                $res[$campo] = null;
                continue;
            }

            // Saltos de línea unificados; sin caracteres de control (salvo \n y \t).
            $limpio = $valor === null ? '' : str_replace(["\r\n", "\r"], "\n", $valor);
            $limpio = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $limpio) ?? '');

            if ($limpio === '') {
                if ($obligatorio) {
                    $errors[$campo] = $msgVacio;
                }
                $res[$campo] = null;
                continue;
            }

            if (mb_strlen($limpio, 'UTF-8') > self::CAMPO_MAX) {
                $errors[$campo] = sprintf('%s no puede superar los %d caracteres.', $etiqueta, self::CAMPO_MAX);
                $res[$campo] = null;
                continue;
            }

            $res[$campo] = $limpio;
        }

        return $res;
    }

    /** Entero estricto: solo enteros o cadenas de dígitos; cualquier otra cosa es 0. */
    private static function aEntero(mixed $valor): int {
        if (is_int($valor)) {
            return $valor;
        }
        return (is_string($valor) && ctype_digit($valor)) ? (int) $valor : 0;
    }

    /** @param array<string,string> $errors */
    private static function fallo(string $mensaje, array $errors = [], bool $reintentable = false): array {
        return ['success' => false, 'message' => $mensaje, 'errors' => $errors, 'id' => null, 'reintentable' => $reintentable];
    }

    private static function noPreparada(string $mensaje): array {
        return ['success' => false, 'message' => $mensaje, 'cita' => null, 'historial' => []];
    }
}
