<?php
/**
 * Formulario de Consulta Veterinaria — US-06 (T-046), UI-01, SEC-01, ACC-01
 * Homologado con el diseño de tarjetas y expedientes de Propietarios, Mascotas y Veterinarios.
 *
 * @var array<string,mixed>         $cita          Cita con detalle (siempre Programada y del veterinario)
 * @var array<array<string,mixed>>  $historial     Historial clínico consolidado de la mascota
 * @var string                      $csrfToken
 * @var string|null                 $flashError
 * @var array<string,string>        $fieldErrors
 * @var array<string,mixed>         $old           Lo escrito en un intento anterior
 * @var string                      $nombreUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$max = \App\Services\ConsultaService::CAMPO_MAX;

$valor = static fn(string $campo): string => (string) ($old[$campo] ?? '');

// Hora de la cita con su rango
[$h, $m] = array_map('intval', explode(':', substr((string) $cita['hora_cita'], 0, 5)));
$fin   = $h * 60 + $m + (int) $cita['duracion_estimada_min'];
$rango = sprintf('%02d:%02d – %02d:%02d', $h, $m, intdiv($fin, 60) % 24, $fin % 60);

// Edad aproximada (solo para mostrar)
$edad = '';
if (!empty($cita['mascota_fecha_nacimiento'])) {
    try {
        $dif  = (new DateTimeImmutable((string) $cita['mascota_fecha_nacimiento']))->diff(new DateTimeImmutable('today'));
        $edad = $dif->y > 0
            ? $dif->y . ($dif->y === 1 ? ' año' : ' años')
            : $dif->m . ($dif->m === 1 ? ' mes' : ' meses');
    } catch (Exception $ex) {
        $edad = '';
    }
}

$campos = [
    'diagnostico'           => ['Diagnóstico Clínico',            true,  4, 'Describe el diagnóstico clínico definitivo o presuntivo'],
    'tratamiento'           => ['Tratamiento e Indicaciones',     true,  4, 'Medicamentos, dosis, frecuencia e indicaciones para el propietario'],
    'observaciones'         => ['Observaciones y Signos Vitales', false, 3, 'Peso, temperatura, hallazgos físicos, evolución (opcional)'],
    'servicios_adicionales' => ['Servicios Adicionales Aplicados',false, 3, 'Procedimientos o insumos complementarios aplicados en consulta (opcional)'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro de consulta veterinaria">
    <title>Registrar Consulta — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/consultas.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'agenda'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">

    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>
                    Registrar Consulta Veterinaria: <?= $e($cita['mascota_nombre']) ?>
                    <span class="estado-badge estado-programada"><?= mb_strtoupper($e($cita['estado']), 'UTF-8') ?></span>
                </h2>
                <p class="text-muted">Al guardar la consulta, la cita pasará automáticamente al estado <strong>Atendida</strong> y quedará integrada en el expediente clínico.</p>
            </div>
            <div>
                <a href="/veterinario/agenda" class="btn btn-sm btn-outline-dark" id="enlace-volver">&larr; Volver a mi agenda</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error mt-1" role="alert">
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resumen superior homologado con detail-grid -->
        <div class="detail-grid mt-1">
            <div class="detail-stat-card">
                <div class="detail-stat-label">Paciente</div>
                <div class="detail-stat-value" style="font-size: 1.1rem;">
                    <?= $e($cita['mascota_nombre']) ?>
                </div>
                <div class="vet-spec" style="margin-top: 0.2rem;">
                    <?= $e($cita['mascota_especie']) ?><?= !empty($cita['mascota_raza']) ? ' · ' . $e($cita['mascota_raza']) : '' ?>
                    (<?= $e($cita['mascota_sexo']) ?><?= $edad !== '' ? ', ' . $e($edad) : '' ?>)
                </div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Propietario Responsable</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;">
                    <?= $e($cita['propietario_nombre']) ?>
                </div>
                <div class="vet-spec" style="margin-top: 0.2rem;">
                    Tel.: <?= $e($cita['propietario_telefono'] ?: 'No registrado') ?>
                </div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Servicio Agendado</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;">
                    <?= $e($cita['nombre_servicio']) ?>
                </div>
                <div class="vet-spec" style="margin-top: 0.2rem;">
                    Médico: <?= $e($cita['veterinario_nombre']) ?>
                </div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Fecha y Horario</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;">
                    <?= $e(date('d/m/Y', (int) strtotime((string) $cita['fecha_cita']))) ?>
                </div>
                <div class="vet-spec" style="margin-top: 0.2rem;">
                    <?= $e($rango) ?> (<?= (int) $cita['duracion_estimada_min'] ?> min)
                </div>
            </div>
        </div>

        <?php if (!empty($cita['mascota_caracteristicas'])): ?>
            <div class="owner-summary-box" style="margin-bottom: 1.25rem;">
                <div>
                    <strong>Características y notas relevantes del paciente:</strong>
                    <span class="texto-clinico"><?= $e($cita['mascota_caracteristicas']) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <h3 class="clinical-section-title" style="margin-top: 0.5rem;">1. Evaluación y Registro Clínico</h3>

        <form id="form-consulta" method="POST" action="/consultas"<?= !empty($old) ? ' data-restaurado="1"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_cita" value="<?= (int) $cita['id_cita'] ?>">

            <div class="form-grid">
                <?php foreach ($campos as $nombre => [$etiqueta, $obligatorio, $filas, $ayuda]): ?>
                    <div class="form-group">
                        <label for="<?= $e($nombre) ?>">
                            <?= $e($etiqueta) ?>
                            <?php if ($obligatorio): ?>
                                <span class="text-danger">*</span>
                            <?php else: ?>
                                <span class="text-muted">(opcional)</span>
                            <?php endif; ?>
                        </label>
                        <textarea id="<?= $e($nombre) ?>" name="<?= $e($nombre) ?>" rows="<?= (int) $filas ?>"
                                  maxlength="<?= (int) $max ?>" placeholder="<?= $e($ayuda) ?>"
                                  data-contador="cont-<?= $e($nombre) ?>" data-max="<?= (int) $max ?>"
                                  <?= $obligatorio ? 'required' : '' ?>
                                  class="form-control <?= isset($fieldErrors[$nombre]) ? 'is-invalid' : '' ?>"><?= $e($valor($nombre)) ?></textarea>
                        <div class="campo-pie">
                            <span class="invalid-feedback" style="display:<?= isset($fieldErrors[$nombre]) ? 'block' : 'none' ?>">
                                <?= $e($fieldErrors[$nombre] ?? '') ?>
                            </span>
                            <span class="contador" id="cont-<?= $e($nombre) ?>" aria-live="off"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions mt-1">
                <button type="submit" class="btn btn-primary">Guardar Consulta y Marcar Atendida</button>
                <a href="/veterinario/agenda" class="btn btn-outline-dark">Cancelar</a>
            </div>
        </form>
    </div>

    <section class="card card-modulo mt-2" aria-labelledby="titulo-historial">
        <div class="card-header-actions">
            <div>
                <h2 id="titulo-historial">Historial Clínico Consolidado de <?= $e($cita['mascota_nombre']) ?></h2>
                <p class="text-muted">Consultas anteriores y antecedentes médicos previos, ordenados del más reciente al más antiguo.</p>
            </div>
            <div>
                <span class="badge badge-vet"><?= count($historial) ?> registro(s)</span>
            </div>
        </div>

        <?php if (empty($historial)): ?>
            <div class="empty-state" role="status">
                <h3>Sin antecedentes ni consultas previas</h3>
                <p>Aún no existen consultas atendidas ni condiciones médicas previas registradas para <?= $e($cita['mascota_nombre']) ?>.</p>
            </div>
        <?php else: ?>
            <ul class="historial-lista">
                <?php foreach ($historial as $ev): ?>
                    <li class="historial-evento">
                        <div class="historial-cabecera">
                            <span class="evento-badge <?= $ev['tipo'] === 'Consulta' ? 'evento-consulta' : 'evento-antecedente' ?>">
                                <?= mb_strtoupper($e($ev['tipo']), 'UTF-8') ?>
                            </span>
                            <?php if ($ev['fecha'] !== ''): ?>
                                <span class="hora-text" style="font-size: 0.82rem; padding: 0.2rem 0.65rem;">
                                    <?= $e($ev['tipo'] === 'Consulta'
                                        ? date('d/m/Y H:i', (int) strtotime($ev['fecha']))
                                        : date('d/m/Y', (int) strtotime($ev['fecha']))) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Sin fecha registrada</span>
                            <?php endif; ?>
                            <?php if ($ev['tipo'] === 'Consulta' && $ev['veterinario'] !== ''): ?>
                                <span class="badge-relation badge-relation-clinical"><?= $e($ev['veterinario']) ?></span>
                            <?php endif; ?>
                            <?php if ($ev['tipo'] === 'Consulta' && !empty($ev['servicio'])): ?>
                                <span class="badge-relation"><?= $e($ev['servicio']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="historial-titulo">
                            <strong><?= $ev['tipo'] === 'Consulta' ? 'Diagnóstico: ' : 'Condición médica: ' ?></strong><?= $e($ev['titulo']) ?>
                        </p>
                        <?php if ($ev['tipo'] === 'Consulta'): ?>
                            <?php if ($ev['tratamiento'] !== ''): ?>
                                <p class="texto-clinico"><strong>Tratamiento:</strong> <?= $e($ev['tratamiento']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ev['observaciones'])): ?>
                                <p class="texto-clinico"><strong>Observaciones:</strong> <?= $e($ev['observaciones']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ev['servicios_adicionales'])): ?>
                                <p class="texto-clinico"><strong>Servicios adicionales:</strong> <?= $e($ev['servicios_adicionales']) ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if (!empty($ev['detalle'])): ?>
                                <p class="texto-clinico"><strong>Detalle:</strong> <?= $e($ev['detalle']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ev['alergias'])): ?>
                                <p class="texto-clinico"><strong>Alergias conocidas:</strong> <?= $e($ev['alergias']) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/consultas.js?v=<?= time() ?>"></script>
</body>
</html>
