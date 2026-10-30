<?php
namespace App\Services;

use App\Repositories\HorarioAtencionRepository;
use App\Repositories\VeterinarioRepository;
use App\Models\CitaModel;
use DateTimeImmutable;

/**
 * HorariosService — US-11 (T-072), ARQ-01, ACC-01, SEC-01, CIT-01, BAJA-01
 * Lógica de negocio y validación estricta para franjas horarias de atención médica.
 */
class HorariosService {

    private HorarioAtencionRepository $horarioRepo;
    private VeterinarioRepository $vetRepo;
    private CitaModel $citaModel;

    public const DIAS_SEMANA = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function __construct(
        ?HorarioAtencionRepository $horarioRepo = null,
        ?VeterinarioRepository $vetRepo = null,
        ?CitaModel $citaModel = null
    ) {
        $this->horarioRepo = $horarioRepo ?? new HorarioAtencionRepository();
        $this->vetRepo     = $vetRepo     ?? new VeterinarioRepository();
        $this->citaModel   = $citaModel   ?? new CitaModel();
    }

    /**
     * Lista todos los horarios registrados.
     *
     * @return array<array<string,mixed>>
     */
    public function listarTodos(): array {
        return $this->horarioRepo->listAll();
    }

    /**
     * Obtiene una franja por su ID.
     *
     * @return array<string,mixed>|null
     */
    public function obtenerPorId(int $idHorario): ?array {
        return $this->horarioRepo->findById($idHorario);
    }

    /**
     * Lista horarios de un veterinario específico.
     *
     * @return array<array<string,mixed>>
     */
    public function listarPorVeterinario(int $idVeterinario): array {
        return $this->horarioRepo->listByVeterinario($idVeterinario);
    }

    /**
     * Crea una nueva franja de atención.
     *
     * @param array{id_veterinario: mixed, dia_semana: mixed, hora_inicio: mixed, hora_fin: mixed} $datos
     * @return array{ok: bool, id_horario?: int, error?: string, field_errors?: array<string,string>}
     */
    public function crearHorario(array $datos): array {
        $val = $this->validarDatos($datos);
        if (!$val['ok']) {
            return $val;
        }

        $idVeterinario = (int) $datos['id_veterinario'];
        $diaSemana     = (int) $datos['dia_semana'];
        $horaInicio    = $val['hora_inicio'];
        $horaFin       = $val['hora_fin'];

        $solapadas = $this->horarioRepo->findSolapadas($idVeterinario, $diaSemana, $horaInicio, $horaFin);
        if (!empty($solapadas)) {
            $f = $solapadas[0];
            $msg = sprintf(
                'El horario se solapa con una franja existente (%s - %s).',
                substr($f['hora_inicio'], 0, 5),
                substr($f['hora_fin'], 0, 5)
            );
            return [
                'ok'           => false,
                'error'        => $msg,
                'field_errors' => ['hora_inicio' => $msg],
            ];
        }

        $nuevoId = $this->horarioRepo->create($idVeterinario, $diaSemana, $horaInicio, $horaFin);
        return [
            'ok'         => true,
            'id_horario' => $nuevoId,
        ];
    }

    /**
     * Actualiza una franja horaria existente verificando solapamientos y citas afectadas.
     *
     * @param int $idHorario
     * @param array{dia_semana: mixed, hora_inicio: mixed, hora_fin: mixed} $datos
     * @return array{ok: bool, error?: string, field_errors?: array<string,string>, citas_afectadas?: array}
     */
    public function actualizarHorario(int $idHorario, array $datos): array {
        $horarioActual = $this->horarioRepo->findById($idHorario);
        if (!$horarioActual) {
            return ['ok' => false, 'error' => 'La franja horaria especificada no existe.'];
        }

        $idVeterinario = (int) $horarioActual['id_veterinario'];
        $datos['id_veterinario'] = $idVeterinario;

        $val = $this->validarDatos($datos);
        if (!$val['ok']) {
            return $val;
        }

        $diaSemana  = (int) $datos['dia_semana'];
        $horaInicio = $val['hora_inicio'];
        $horaFin    = $val['hora_fin'];

        $solapadas = $this->horarioRepo->findSolapadas($idVeterinario, $diaSemana, $horaInicio, $horaFin, $idHorario);
        if (!empty($solapadas)) {
            $f = $solapadas[0];
            $msg = sprintf(
                'El nuevo horario se solapa con otra franja existente (%s - %s).',
                substr($f['hora_inicio'], 0, 5),
                substr($f['hora_fin'], 0, 5)
            );
            return [
                'ok'           => false,
                'error'        => $msg,
                'field_errors' => ['hora_inicio' => $msg],
            ];
        }

        $afectadas = $this->comprobarCitasAfectadasPorModificacion($horarioActual, $diaSemana, $horaInicio, $horaFin);
        if (!empty($afectadas)) {
            $detalles = array_map(function($c) {
                return sprintf(
                    'Cita #%d el %s a las %s (%s - %s)',
                    $c['id_cita'],
                    $c['fecha_cita'],
                    substr($c['hora_cita'], 0, 5),
                    $c['mascota_nombre'],
                    $c['servicio_nombre']
                );
            }, $afectadas);

            return [
                'ok'              => false,
                'error'           => 'No se puede modificar la franja porque existen citas futuras programadas que quedarían fuera del horario: ' . implode('; ', $detalles),
                'citas_afectadas' => $afectadas,
            ];
        }

        $this->horarioRepo->update($idHorario, $diaSemana, $horaInicio, $horaFin);
        return ['ok' => true];
    }

    /**
     * Elimina una franja horaria controlada, bloqueando si hay citas programadas futuras afectadas (CIT-01, BAJA-01).
     *
     * @return array{ok: bool, error?: string, citas_afectadas?: array}
     */
    public function eliminarHorario(int $idHorario): array {
        $horarioActual = $this->horarioRepo->findById($idHorario);
        if (!$horarioActual) {
            return ['ok' => false, 'error' => 'La franja horaria no existe.'];
        }

        $idVeterinario = (int) $horarioActual['id_veterinario'];
        $diaSemana     = (int) $horarioActual['dia_semana'];
        $horaInicio    = $horarioActual['hora_inicio'];
        $horaFin       = $horarioActual['hora_fin'];

        $citasFuturas = $this->citaModel->findProgramadasFuturasByVeterinario($idVeterinario);
        $afectadas = [];

        foreach ($citasFuturas as $cita) {
            $diaCita = (int) (new DateTimeImmutable($cita['fecha_cita']))->format('N');
            if ($diaCita !== $diaSemana) {
                continue;
            }

            $inicioCita = $cita['hora_cita'];
            $finCitaSec = strtotime($inicioCita) + (((int)$cita['duracion_estimada_min']) * 60);
            $finCita    = date('H:i:s', $finCitaSec);

            if ($inicioCita >= $horaInicio && $finCita <= $horaFin) {
                $otrasFranjas = $this->horarioRepo->listByVeterinarioYDia($idVeterinario, $diaSemana);
                $cubiertaPorOtra = false;
                foreach ($otrasFranjas as $otra) {
                    if ((int)$otra['id_horario'] === $idHorario) {
                        continue;
                    }
                    if ($inicioCita >= $otra['hora_inicio'] && $finCita <= $otra['hora_fin']) {
                        $cubiertaPorOtra = true;
                        break;
                    }
                }

                if (!$cubiertaPorOtra) {
                    $afectadas[] = $cita;
                }
            }
        }

        if (!empty($afectadas)) {
            $detalles = array_map(function($c) {
                return sprintf(
                    'Cita #%d el %s a las %s (%s - %s)',
                    $c['id_cita'],
                    $c['fecha_cita'],
                    substr($c['hora_cita'], 0, 5),
                    $c['mascota_nombre'],
                    $c['servicio_nombre']
                );
            }, $afectadas);

            return [
                'ok'              => false,
                'error'           => 'No se puede eliminar la franja porque existen citas futuras programadas en este horario: ' . implode('; ', $detalles),
                'citas_afectadas' => $afectadas,
            ];
        }

        $this->horarioRepo->delete($idHorario);
        return ['ok' => true];
    }

    /**
     * Comprueba qué citas futuras se verían afectadas si se cambia el horario.
     *
     * @return array<array<string,mixed>>
     */
    public function comprobarCitasAfectadasPorModificacion(
        array $horarioActual,
        int $nuevoDia,
        string $nuevaHoraInicio,
        string $nuevaHoraFin
    ): array {
        $idHorario     = (int) $horarioActual['id_horario'];
        $idVeterinario = (int) $horarioActual['id_veterinario'];
        $diaActual     = (int) $horarioActual['dia_semana'];
        $horaInicioAnt = $horarioActual['hora_inicio'];
        $horaFinAnt    = $horarioActual['hora_fin'];

        $citasFuturas = $this->citaModel->findProgramadasFuturasByVeterinario($idVeterinario);
        $afectadas = [];

        foreach ($citasFuturas as $cita) {
            $diaCita = (int) (new DateTimeImmutable($cita['fecha_cita']))->format('N');
            if ($diaCita !== $diaActual) {
                continue;
            }

            $inicioCita = $cita['hora_cita'];
            $finCitaSec = strtotime($inicioCita) + (((int)$cita['duracion_estimada_min']) * 60);
            $finCita    = date('H:i:s', $finCitaSec);

            if ($inicioCita >= $horaInicioAnt && $finCita <= $horaFinAnt) {
                $sigueCubierta = false;
                if ($nuevoDia === $diaActual && $inicioCita >= $nuevaHoraInicio && $finCita <= $nuevaHoraFin) {
                    $sigueCubierta = true;
                } else {
                    $otrasFranjas = $this->horarioRepo->listByVeterinarioYDia($idVeterinario, $diaActual);
                    foreach ($otrasFranjas as $otra) {
                        if ((int)$otra['id_horario'] === $idHorario) {
                            continue;
                        }
                        if ($inicioCita >= $otra['hora_inicio'] && $finCita <= $otra['hora_fin']) {
                            $sigueCubierta = true;
                            break;
                        }
                    }
                }

                if (!$sigueCubierta) {
                    $afectadas[] = $cita;
                }
            }
        }

        return $afectadas;
    }

    /**
     * Valida los datos primitivos de una franja de horario.
     *
     * @param array<string,mixed> $datos
     * @return array{ok: bool, hora_inicio?: string, hora_fin?: string, error?: string, field_errors?: array<string,string>}
     */
    public function validarDatos(array $datos): array {
        $fieldErrors = [];

        $idVeterinario = filter_var($datos['id_veterinario'] ?? null, FILTER_VALIDATE_INT);
        if (!$idVeterinario || $idVeterinario <= 0) {
            $fieldErrors['id_veterinario'] = 'Seleccione un veterinario válido.';
        } else {
            $vet = $this->vetRepo->findActivoById($idVeterinario);
            if (!$vet) {
                $fieldErrors['id_veterinario'] = 'El veterinario seleccionado no existe o no está activo.';
            }
        }

        $diaSemana = filter_var($datos['dia_semana'] ?? null, FILTER_VALIDATE_INT);
        if ($diaSemana === false || $diaSemana < 1 || $diaSemana > 7) {
            $fieldErrors['dia_semana'] = 'El día de la semana debe ser un valor entre 1 (Lunes) y 7 (Domingo).';
        }

        $horaInicioRaw = trim((string)($datos['hora_inicio'] ?? ''));
        $horaFinRaw    = trim((string)($datos['hora_fin'] ?? ''));

        $normInicio = $this->normalizarHora($horaInicioRaw);
        $normFin    = $this->normalizarHora($horaFinRaw);

        if (!$normInicio) {
            $fieldErrors['hora_inicio'] = 'Ingrese una hora de inicio válida (formato HH:MM).';
        }
        if (!$normFin) {
            $fieldErrors['hora_fin'] = 'Ingrese una hora de fin válida (formato HH:MM).';
        }

        if ($normInicio && $normFin) {
            if ($normInicio >= $normFin) {
                $fieldErrors['hora_inicio'] = 'La hora de inicio debe preceder a la hora de fin.';
            } else {
                $secDiff = strtotime($normFin) - strtotime($normInicio);
                if ($secDiff < 900) {
                    $fieldErrors['hora_fin'] = 'La franja debe tener una duración mínima de al menos 15 minutos.';
                }
            }
        }

        if (!empty($fieldErrors)) {
            return [
                'ok'           => false,
                'error'        => reset($fieldErrors),
                'field_errors' => $fieldErrors,
            ];
        }

        return [
            'ok'          => true,
            'hora_inicio' => $normInicio,
            'hora_fin'    => $normFin,
        ];
    }

    /**
     * Normaliza y valida una cadena de hora a formato HH:MM:00.
     */
    private function normalizarHora(string $hora): ?string {
        if (!preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $hora)) {
            return null;
        }
        $partes = explode(':', $hora);
        $h = str_pad($partes[0], 2, '0', STR_PAD_LEFT);
        $m = str_pad($partes[1], 2, '0', STR_PAD_LEFT);
        return "$h:$m:00";
    }
}
