<?php
namespace App\Services;

use App\Repositories\CitaRepository;
use App\Repositories\HorarioAtencionRepository;
use App\Repositories\MascotaRepository;
use App\Repositories\ServicioRepository;
use App\Repositories\VeterinarioRepository;
use DateTimeImmutable;

/**
 * CitaService — US-04 (T-032), ARQ-01, DAT-01, SEC-01
 * Reglas de negocio del agendamiento de citas:
 *
 *  - Solo mascotas, servicios y veterinarios activos.
 *  - Las franjas se calculan con `horarios_atencion` y la duración del servicio.
 *  - Una cita no puede solaparse con otra no cancelada del mismo veterinario.
 *  - La disponibilidad se revalida al guardar, dentro de una transacción que
 *    bloquea al veterinario, para que dos solicitudes simultáneas no reserven
 *    el mismo hueco.
 */
class CitaService {

    /** Separación, en minutos, entre horas de inicio ofrecidas. */
    public const PASO_MINUTOS = 15;

    /** Filtros de estado admitidos en el listado. */
    public const FILTRO_TODAS     = 'Todas';
    public const FILTRO_PREDETERM = 'Programada';
    public const ESTADOS_FILTRO   = ['Programada', 'Atendida', 'Cancelada', self::FILTRO_TODAS];

    /** Máximo de citas mostradas en el listado. */
    public const LIMITE_LISTADO = 200;

    /** Longitud máxima del motivo de cancelación. */
    public const MOTIVO_MAX = 500;

    public function __construct(
        private ?CitaRepository $citaRepo = null,
        private ?HorarioAtencionRepository $horarioRepo = null,
        private ?ServicioRepository $servicioRepo = null,
        private ?VeterinarioRepository $veterinarioRepo = null,
        private ?MascotaRepository $mascotaRepo = null
    ) {
        $this->citaRepo        ??= new CitaRepository();
        $this->horarioRepo     ??= new HorarioAtencionRepository();
        $this->servicioRepo    ??= new ServicioRepository();
        $this->veterinarioRepo ??= new VeterinarioRepository();
        $this->mascotaRepo     ??= new MascotaRepository();
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Opciones del formulario
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Registros válidos para poblar los selectores del formulario.
     *
     * @return array{mascotas: array, servicios: array, veterinarios: array}
     */
    public function obtenerOpciones(): array {
        return [
            'mascotas'     => $this->mascotaRepo->listAll(true),
            'servicios'    => $this->servicioRepo->listActivos(),
            'veterinarios' => $this->veterinarioRepo->listActivos(),
        ];
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Disponibilidad
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Valida la selección y calcula los horarios libres.
     *
     * @param array{id_mascota?:mixed,id_servicio?:mixed,id_veterinario?:mixed,fecha?:mixed} $filtros
     * @return array{
     *     errors: array<string,string>,
     *     franjas: string[],
     *     duracion: int,
     *     mascota: ?array, servicio: ?array, veterinario: ?array, fecha: ?string
     * }
     */
    public function consultarDisponibilidad(array $filtros, ?DateTimeImmutable $ahora = null): array {
        $ahora = $ahora ?? new DateTimeImmutable();
        $sel   = $this->resolverSeleccion($filtros, $ahora);

        $franjas  = [];
        $duracion = 0;
        if ($sel['servicio'] !== null) {
            $duracion = (int) $sel['servicio']['duracion_estimada_min'];
        }

        if ($sel['errors'] === []) {
            $franjas = $this->calcularFranjas(
                (int) $sel['veterinario']['id_veterinario'],
                $duracion,
                $sel['fecha'],
                $ahora
            );
        }

        return [
            'errors'      => $sel['errors'],
            'franjas'     => $franjas,
            'duracion'    => $duracion,
            'mascota'     => $sel['mascota'],
            'servicio'    => $sel['servicio'],
            'veterinario' => $sel['veterinario'],
            'fecha'       => $sel['fecha'],
        ];
    }

    /**
     * Horas de inicio (HH:MM) en las que cabe un servicio de $duracionMin minutos
     * dentro del horario del veterinario sin solaparse con citas existentes.
     *
     * @param bool $bloquear Lectura de bloqueo de las citas (solo dentro de la transacción).
     * @return string[]
     */
    public function calcularFranjas(int $idVeterinario, int $duracionMin, string $fecha, DateTimeImmutable $ahora, bool $bloquear = false): array {
        if ($duracionMin <= 0) {
            return [];
        }

        $dia = (int) DateTimeImmutable::createFromFormat('!Y-m-d', $fecha)->format('N');
        $bloques = $this->horarioRepo->listByVeterinarioYDia($idVeterinario, $dia);
        if ($bloques === []) {
            return [];
        }

        // Intervalos ocupados [inicio, fin) en minutos desde medianoche.
        $ocupadas = [];
        foreach ($this->citaRepo->listOcupadas($idVeterinario, $fecha, $bloquear) as $cita) {
            $ini = self::aMinutos((string) $cita['hora_cita']);
            $ocupadas[] = [$ini, $ini + (int) $cita['duracion_estimada_min']];
        }

        // Si la fecha es hoy, solo horas posteriores al momento actual.
        $minimo = -1;
        if ($fecha === $ahora->format('Y-m-d')) {
            $minimo = (int) $ahora->format('G') * 60 + (int) $ahora->format('i');
        }

        $inicios = [];
        foreach ($bloques as $bloque) {
            $desde = self::aMinutos((string) $bloque['hora_inicio']);
            $hasta = self::aMinutos((string) $bloque['hora_fin']);
            for ($s = $desde; $s + $duracionMin <= $hasta; $s += self::PASO_MINUTOS) {
                if ($s <= $minimo) {
                    continue;
                }
                if ($this->haySolape($s, $s + $duracionMin, $ocupadas)) {
                    continue;
                }
                $inicios[$s] = true;
            }
        }

        ksort($inicios);
        return array_map([self::class, 'formatear'], array_keys($inicios));
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Creación de la cita
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Agenda una cita. La validación definitiva se hace aquí, dentro de una
     * transacción que bloquea al veterinario (T-032).
     *
     * @param array{id_mascota?:mixed,id_servicio?:mixed,id_veterinario?:mixed,fecha?:mixed,hora_cita?:mixed} $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int}
     */
    public function agendarCita(array $datos, ?DateTimeImmutable $ahora = null): array {
        $ahora = $ahora ?? new DateTimeImmutable();
        $sel   = $this->resolverSeleccion($datos, $ahora);
        $errors = $sel['errors'];

        $hora = trim((string) ($datos['hora_cita'] ?? ''));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            $errors['hora_cita'] = 'Selecciona un horario disponible.';
        }

        if ($errors !== []) {
            return self::fallo('Por favor corrige los errores señalados.', $errors);
        }

        $idVet    = (int) $sel['veterinario']['id_veterinario'];
        $duracion = (int) $sel['servicio']['duracion_estimada_min'];

        return $this->citaRepo->transaccion(function () use ($sel, $idVet, $duracion, $hora, $ahora): array {
            // 1) Serializar reservas del mismo veterinario.
            if (!$this->veterinarioRepo->bloquearActivo($idVet)) {
                return self::fallo('Por favor corrige los errores señalados.', [
                    'id_veterinario' => 'El veterinario seleccionado no existe o no está activo.',
                ]);
            }

            // 2) Revalidar con lectura de bloqueo: ve lo último confirmado.
            $franjas = $this->calcularFranjas($idVet, $duracion, $sel['fecha'], $ahora, true);
            if (!in_array($hora, $franjas, true)) {
                return self::fallo(
                    'El horario seleccionado ya no está disponible. Elige otro de la lista actualizada.',
                    ['hora_cita' => 'Ese horario ya no está disponible.']
                );
            }

            // 3) Persistir.
            $id = $this->citaRepo->create([
                'id_mascota'     => (int) $sel['mascota']['id_mascota'],
                'id_veterinario' => $idVet,
                'id_servicio'    => (int) $sel['servicio']['id_servicio'],
                'fecha_cita'     => $sel['fecha'],
                'hora_cita'      => $hora . ':00',
                'estado'         => 'Programada',
            ]);

            return [
                'success' => true,
                'message' => sprintf(
                    'Cita programada para %s el %s a las %s con %s (%s).',
                    $sel['mascota']['nombre'],
                    $sel['fecha'],
                    $hora,
                    $sel['veterinario']['nombre'],
                    $sel['servicio']['nombre_servicio']
                ),
                'errors'  => [],
                'id'      => $id,
            ];
        });
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Listado y cancelación (US-05, US-08 / T-054, T-055)
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Valida estados permitidos, fechas y valores de filtro antes de consultar (T-055, RF08).
     * Solo se admiten Programada, Atendida o Cancelada (o Todas/vacío para ver todas).
     * Fechas inválidas o rangos invertidos producen mensajes claros.
     *
     * @param array<string,mixed> $criterios
     * @return array{valido: bool, errores: array<string,string>, criterios: array<string,mixed>}
     */
    public function validarFiltros(array $criterios): array {
        $errores = [];
        $limpios = [
            'estado'         => null,
            'fecha_desde'    => null,
            'fecha_hasta'    => null,
            'id_veterinario' => null,
            'q'              => null,
        ];

        // 1. Validar estado: solo se admiten Programada, Atendida, Cancelada o Todas (T-055)
        if (isset($criterios['estado']) && $criterios['estado'] !== '' && $criterios['estado'] !== null) {
            $estado = is_string($criterios['estado']) ? trim($criterios['estado']) : '';
            if (!in_array($estado, self::ESTADOS_FILTRO, true)) {
                $errores['estado'] = 'Estado de cita no válido: solo se admiten Programada, Atendida o Cancelada.';
            } else {
                $limpios['estado'] = $estado;
            }
        } else {
            $limpios['estado'] = self::FILTRO_PREDETERM;
        }

        // 2. Validar fecha_desde
        if (isset($criterios['fecha_desde']) && $criterios['fecha_desde'] !== '' && $criterios['fecha_desde'] !== null) {
            $fd = is_string($criterios['fecha_desde']) ? trim($criterios['fecha_desde']) : '';
            if (!self::esFechaValida($fd)) {
                $errores['fecha_desde'] = 'La fecha inicial no tiene un formato válido (AAAA-MM-DD).';
            } else {
                $limpios['fecha_desde'] = $fd;
            }
        }

        // 3. Validar fecha_hasta
        if (isset($criterios['fecha_hasta']) && $criterios['fecha_hasta'] !== '' && $criterios['fecha_hasta'] !== null) {
            $fh = is_string($criterios['fecha_hasta']) ? trim($criterios['fecha_hasta']) : '';
            if (!self::esFechaValida($fh)) {
                $errores['fecha_hasta'] = 'La fecha final no tiene un formato válido (AAAA-MM-DD).';
            } else {
                $limpios['fecha_hasta'] = $fh;
            }
        }

        // 4. Coherencia de rango (fecha_desde <= fecha_hasta)
        if ($limpios['fecha_desde'] !== null && $limpios['fecha_hasta'] !== null && $limpios['fecha_desde'] > $limpios['fecha_hasta']) {
            $errores['rango_fechas'] = 'La fecha inicial no puede ser posterior a la fecha final.';
        }

        // 5. Validar id_veterinario
        if (isset($criterios['id_veterinario']) && $criterios['id_veterinario'] !== '' && $criterios['id_veterinario'] !== null) {
            $idVet = filter_var($criterios['id_veterinario'], FILTER_VALIDATE_INT);
            if ($idVet === false || $idVet <= 0) {
                $errores['id_veterinario'] = 'El médico veterinario seleccionado no es válido.';
            } else {
                $limpios['id_veterinario'] = $idVet;
            }
        }

        // 6. Texto de búsqueda opcional
        if (isset($criterios['q']) && is_string($criterios['q'])) {
            $q = trim($criterios['q']);
            if ($q !== '') {
                $limpios['q'] = $q;
            }
        }

        return [
            'valido'    => ($errores === []),
            'errores'   => $errores,
            'criterios' => $limpios,
        ];
    }

    /**
     * Verifica que una cadena corresponda a una fecha del calendario gregoriano en formato AAAA-MM-DD.
     */
    private static function esFechaValida(string $fecha): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $fecha));
        return checkdate($m, $d, $y);
    }

    /**
     * Citas para el listado con filtros avanzados (US-05, US-08 / T-054, T-055).
     * Admite arreglo de criterios o un estado individual para compatibilidad retrospectiva.
     *
     * @param array<string,mixed>|string|null $filtros
     * @return array{estado: string, citas: array, truncado: bool, limite: int, errors: array<string,string>, filtros: array}
     */
    public function listarCitas(mixed $filtros = null): array {
        // Compatibilidad retrospectiva para llamada clásica con solo string o null (US-05)
        if (!is_array($filtros)) {
            $estado = (is_string($filtros) && in_array($filtros, self::ESTADOS_FILTRO, true))
                ? $filtros
                : self::FILTRO_PREDETERM;

            $filas = $this->citaRepo->listDetalle(
                $estado === self::FILTRO_TODAS ? null : $estado,
                self::LIMITE_LISTADO + 1
            );

            $truncado = count($filas) > self::LIMITE_LISTADO;
            if ($truncado) {
                $filas = array_slice($filas, 0, self::LIMITE_LISTADO);
            }

            return [
                'estado'   => $estado,
                'citas'    => $filas,
                'truncado' => $truncado,
                'limite'   => self::LIMITE_LISTADO,
                'errors'   => [],
                'filtros'  => ['estado' => $estado],
            ];
        }

        // Flujo multi-criterio de US-08: validar antes de consultar (T-055)
        $validacion = $this->validarFiltros($filtros);
        $criterios  = $validacion['criterios'];
        $estado     = $criterios['estado'] ?? self::FILTRO_PREDETERM;

        if (!$validacion['valido']) {
            return [
                'estado'   => $estado,
                'citas'    => [],
                'truncado' => false,
                'limite'   => self::LIMITE_LISTADO,
                'errors'   => $validacion['errores'],
                'filtros'  => $criterios,
            ];
        }

        $filas = $this->citaRepo->listDetalle(
            $criterios,
            self::LIMITE_LISTADO + 1
        );

        $truncado = count($filas) > self::LIMITE_LISTADO;
        if ($truncado) {
            $filas = array_slice($filas, 0, self::LIMITE_LISTADO);
        }

        return [
            'estado'   => $estado,
            'citas'    => $filas,
            'truncado' => $truncado,
            'limite'   => self::LIMITE_LISTADO,
            'errors'   => [],
            'filtros'  => $criterios,
        ];
    }

    /**
     * Cita lista para mostrar la pantalla de confirmación; falla si no se puede cancelar.
     *
     * @return array{success: bool, message: string, cita: ?array}
     */
    public function obtenerParaCancelar(mixed $id): array {
        $idCita = self::aEntero($id);
        if ($idCita <= 0) {
            return ['success' => false, 'message' => 'La cita indicada no es válida.', 'cita' => null];
        }

        $cita = $this->citaRepo->findDetalleById($idCita);
        if (!$cita) {
            return ['success' => false, 'message' => 'La cita no existe.', 'cita' => null];
        }
        if ($cita['estado'] !== 'Programada') {
            return ['success' => false, 'message' => self::mensajeNoCancelable((string) $cita['estado']), 'cita' => $cita];
        }

        return ['success' => true, 'message' => '', 'cita' => $cita];
    }

    /**
     * Cancela una cita Programada y registra el motivo, que es opcional (T-038).
     * La cita Cancelada deja de ocupar agenda, así que su horario vuelve a ofrecerse.
     *
     * @return array{success: bool, message: string, errors: array<string,string>}
     */
    public function cancelarCita(mixed $id, mixed $motivo = null): array {
        $idCita = self::aEntero($id);
        if ($idCita <= 0) {
            return ['success' => false, 'message' => 'La cita indicada no es válida.', 'errors' => []];
        }

        $errorMotivo = null;
        $motivoLimpio = self::normalizarMotivo($motivo, $errorMotivo);
        if ($errorMotivo !== null) {
            return ['success' => false, 'message' => $errorMotivo, 'errors' => ['motivo' => $errorMotivo]];
        }

        $cita = $this->citaRepo->findDetalleById($idCita);
        if (!$cita) {
            return ['success' => false, 'message' => 'La cita no existe.', 'errors' => []];
        }
        if ($cita['estado'] !== 'Programada') {
            return ['success' => false, 'message' => self::mensajeNoCancelable((string) $cita['estado']), 'errors' => []];
        }

        // El UPDATE vuelve a exigir estado Programada: si alguien más la cambió
        // entre la lectura y la escritura, no se afecta ninguna fila.
        if (!$this->citaRepo->cancelarSiProgramada($idCita, $motivoLimpio)) {
            return [
                'success' => false,
                'message' => 'La cita ya no está Programada; no se realizaron cambios.',
                'errors'  => [],
            ];
        }

        return [
            'success' => true,
            'message' => sprintf(
                'Cita cancelada: %s, %s a las %s con %s. El horario quedó disponible.',
                $cita['mascota_nombre'],
                date('d/m/Y', (int) strtotime((string) $cita['fecha_cita'])),
                substr((string) $cita['hora_cita'], 0, 5),
                $cita['veterinario_nombre']
            ),
            'errors'  => [],
        ];
    }

    private static function mensajeNoCancelable(string $estado): string {
        return sprintf('Solo se pueden cancelar citas Programadas (esta cita está %s).', $estado);
    }

    /**
     * Motivo opcional: sin espacios en los extremos, sin caracteres de control,
     * vacío = null. $error recibe el mensaje si no es válido.
     */
    private static function normalizarMotivo(mixed $motivo, ?string &$error): ?string {
        if ($motivo === null) {
            return null;
        }
        if (!is_string($motivo) || !mb_check_encoding($motivo, 'UTF-8')) {
            $error = 'El motivo no es válido.';
            return null;
        }

        $motivo = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $motivo) ?? '';
        $motivo = trim($motivo);
        if ($motivo === '') {
            return null;
        }
        if (mb_strlen($motivo, 'UTF-8') > self::MOTIVO_MAX) {
            $error = sprintf('El motivo no puede superar los %d caracteres.', self::MOTIVO_MAX);
            return null;
        }
        return $motivo;
    }

    // ────────────────────────────────────────────────────────────────── //
    //  Internos
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Valida mascota, servicio, veterinario y fecha, y devuelve las entidades.
     *
     * @return array{errors: array<string,string>, mascota: ?array, servicio: ?array, veterinario: ?array, fecha: ?string}
     */
    private function resolverSeleccion(array $d, DateTimeImmutable $ahora): array {
        $errors = [];

        // Mascota activa con propietario activo (DAT-01)
        $mascota = null;
        $idMascota = self::aEntero($d['id_mascota'] ?? null);
        if ($idMascota <= 0) {
            $errors['id_mascota'] = 'Debes seleccionar una mascota.';
        } else {
            $mascota = $this->mascotaRepo->findById($idMascota);
            if (!$mascota) {
                $errors['id_mascota'] = 'La mascota seleccionada no existe.';
            } elseif ((int) ($mascota['activo'] ?? 1) !== 1 || (int) ($mascota['propietario_activo'] ?? 1) !== 1) {
                $errors['id_mascota'] = 'La mascota seleccionada no está activa.';
                $mascota = null;
            }
        }
        if (isset($errors['id_mascota'])) {
            $mascota = null;
        }

        // Servicio activo
        $servicio = null;
        $idServicio = self::aEntero($d['id_servicio'] ?? null);
        if ($idServicio <= 0) {
            $errors['id_servicio'] = 'Debes seleccionar un servicio.';
        } else {
            $servicio = $this->servicioRepo->findActivoById($idServicio);
            if (!$servicio) {
                $errors['id_servicio'] = 'El servicio seleccionado no existe o no está activo.';
            }
        }

        // Veterinario activo
        $veterinario = null;
        $idVet = self::aEntero($d['id_veterinario'] ?? null);
        if ($idVet <= 0) {
            $errors['id_veterinario'] = 'Debes seleccionar un veterinario.';
        } else {
            $veterinario = $this->veterinarioRepo->findActivoById($idVet);
            if (!$veterinario) {
                $errors['id_veterinario'] = 'El veterinario seleccionado no existe o no está activo.';
            }
        }

        // Fecha válida y no pasada
        $fecha = trim((string) ($d['fecha'] ?? ''));
        if ($fecha === '') {
            $errors['fecha'] = 'Debes seleccionar una fecha.';
            $fecha = null;
        } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errors['fecha'] = 'La fecha no es válida (formato AAAA-MM-DD).';
            $fecha = null;
        } elseif ($fecha < $ahora->format('Y-m-d')) {
            $errors['fecha'] = 'No se pueden agendar citas en fechas pasadas.';
            $fecha = null;
        }

        return [
            'errors'      => $errors,
            'mascota'     => $mascota,
            'servicio'    => $servicio,
            'veterinario' => $veterinario,
            'fecha'       => $fecha,
        ];
    }

    /** @param array<array{0:int,1:int}> $ocupadas */
    private function haySolape(int $ini, int $fin, array $ocupadas): bool {
        foreach ($ocupadas as [$oIni, $oFin]) {
            if ($ini < $oFin && $fin > $oIni) {
                return true;
            }
        }
        return false;
    }

    private static function aMinutos(string $hora): int {
        [$h, $m] = array_map('intval', explode(':', $hora));
        return $h * 60 + $m;
    }

    private static function formatear(int $minutos): string {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /** Entero estricto: solo enteros o cadenas de dígitos; cualquier otra cosa es 0. */
    private static function aEntero(mixed $valor): int {
        if (is_int($valor)) {
            return $valor;
        }
        return (is_string($valor) && ctype_digit($valor)) ? (int) $valor : 0;
    }

    /** @param array<string,string> $errors */
    private static function fallo(string $mensaje, array $errors): array {
        return ['success' => false, 'message' => $mensaje, 'errors' => $errors, 'id' => null];
    }
}
