<?php
/**
 * Formulario de Consulta Veterinaria — US-06 (T-046), UI-01, SEC-01, ACC-01
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
    'diagnostico'           => ['Diagnóstico',            true,  4, 'Describe el diagnóstico clínico'],
    'observaciones'         => ['Observaciones',          false, 3, 'Hallazgos, evolución, recomendaciones (opcional)'],
    'tratamiento'           => ['Tratamiento',            true,  4, 'Medicamentos, dosis, indicaciones'],
    'servicios_adicionales' => ['Servicios adicionales aplicados', false, 3, 'Procedimientos o servicios extra (opcional)'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro de consulta veterinaria">
    <title>Registrar consulta — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/consultas.css">
</head>
<body class="dashboard-page">

<?php $navActivo = 'agenda'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">

    <div id="alert-container" aria-live="polite">
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error" role="alert">
                <span aria-hidden="true">⚠️</span>
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Registrar consulta</h2>
        <p class="text-muted">Al guardar, la cita pasa a <strong>Atendida</strong> y ya no podrá registrarse otra consulta para ella.</p>

        <!-- Datos de la cita y de la mascota: solo lectura -->
        <div class="lectura-grid">
            <section class="lectura-bloque" aria-labelledby="titulo-cita">
                <h3 id="titulo-cita">Cita</h3>
                <dl class="lectura">
                    <dt>Estado</dt><dd><span class="estado-badge estado-programada"><?= $e($cita['estado']) ?></span></dd>
                    <dt>Fecha</dt><dd><?= $e(date('d/m/Y', (int) strtotime((string) $cita['fecha_cita']))) ?></dd>
                    <dt>Hora</dt><dd><?= $e($rango) ?></dd>
                    <dt>Servicio</dt><dd><?= $e($cita['nombre_servicio']) ?></dd>
                    <dt>Veterinario</dt><dd><?= $e($cita['veterinario_nombre']) ?></dd>
                </dl>
            </section>

            <section class="lectura-bloque" aria-labelledby="titulo-mascota">
                <h3 id="titulo-mascota">Mascota</h3>
                <dl class="lectura">
                    <dt>Nombre</dt><dd>🐾 <?= $e($cita['mascota_nombre']) ?></dd>
                    <dt>Especie</dt><dd><?= $e($cita['mascota_especie']) ?><?= !empty($cita['mascota_raza']) ? ' · ' . $e($cita['mascota_raza']) : '' ?></dd>
                    <dt>Sexo</dt><dd><?= $e($cita['mascota_sexo']) ?></dd>
                    <?php if ($edad !== ''): ?>
                        <dt>Edad</dt><dd><?= $e($edad) ?></dd>
                    <?php endif; ?>
                    <dt>Propietario</dt><dd><?= $e($cita['propietario_nombre']) ?><?= !empty($cita['propietario_telefono']) ? ' · ' . $e($cita['propietario_telefono']) : '' ?></dd>
                    <?php if (!empty($cita['mascota_caracteristicas'])): ?>
                        <dt>Características</dt><dd class="texto-clinico"><?= $e($cita['mascota_caracteristicas']) ?></dd>
                    <?php endif; ?>
                </dl>
            </section>
        </div>

        <hr class="consultas-separador">

        <form id="form-consulta" method="POST" action="/consultas"<?= !empty($old) ? ' data-restaurado="1"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_cita" value="<?= (int) $cita['id_cita'] ?>">

            <?php foreach ($campos as $nombre => [$etiqueta, $obligatorio, $filas, $ayuda]): ?>
                <div class="form-group">
                    <label for="<?= $e($nombre) ?>">
                        <?= $e($etiqueta) ?>
                        <?php if ($obligatorio): ?><span class="text-danger">*</span><?php endif; ?>
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

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar consulta</button>
                <a href="/veterinario/agenda" class="btn btn-outline-dark" id="enlace-volver">Volver a mi agenda</a>
            </div>
        </form>
    </div>

    <section class="card mt-2" aria-labelledby="titulo-historial">
        <h2 id="titulo-historial">Historial clínico de <?= $e($cita['mascota_nombre']) ?></h2>
        <p class="text-muted">Consultas anteriores y antecedentes médicos, de lo más reciente a lo más antiguo.</p>

        <?php if (empty($historial)): ?>
            <p class="text-muted">Aún no hay consultas ni antecedentes registrados para esta mascota.</p>
        <?php else: ?>
            <ul class="historial-lista">
                <?php foreach ($historial as $ev): ?>
                    <li class="historial-evento">
                        <div class="historial-cabecera">
                            <span class="evento-badge <?= $ev['tipo'] === 'Consulta' ? 'evento-consulta' : 'evento-antecedente' ?>"><?= $e($ev['tipo']) ?></span>
                            <?php if ($ev['fecha'] !== ''): ?>
                                <time><?= $e($ev['tipo'] === 'Consulta'
                                    ? date('d/m/Y H:i', (int) strtotime($ev['fecha']))
                                    : date('d/m/Y', (int) strtotime($ev['fecha']))) ?></time>
                            <?php else: ?>
                                <span class="text-muted">Sin fecha</span>
                            <?php endif; ?>
                            <?php if ($ev['tipo'] === 'Consulta' && $ev['veterinario'] !== ''): ?>
                                <span class="text-muted">· <?= $e($ev['veterinario']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="historial-titulo"><strong><?= $ev['tipo'] === 'Consulta' ? 'Diagnóstico: ' : '' ?></strong><?= $e($ev['titulo']) ?></p>
                        <?php if ($ev['tipo'] === 'Consulta'): ?>
                            <?php if ($ev['tratamiento'] !== ''): ?><p class="texto-clinico"><strong>Tratamiento:</strong> <?= $e($ev['tratamiento']) ?></p><?php endif; ?>
                            <?php if (!empty($ev['observaciones'])): ?><p class="texto-clinico"><strong>Observaciones:</strong> <?= $e($ev['observaciones']) ?></p><?php endif; ?>
                            <?php if (!empty($ev['servicios_adicionales'])): ?><p class="texto-clinico"><strong>Servicios adicionales:</strong> <?= $e($ev['servicios_adicionales']) ?></p><?php endif; ?>
                        <?php else: ?>
                            <?php if (!empty($ev['detalle'])): ?><p class="texto-clinico"><?= $e($ev['detalle']) ?></p><?php endif; ?>
                            <?php if (!empty($ev['alergias'])): ?><p class="texto-clinico"><strong>Alergias:</strong> <?= $e($ev['alergias']) ?></p><?php endif; ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/consultas.js"></script>
</body>
</html>
