<?php
/**
 * Confirmación de cancelación de una cita — US-05 (T-040), UI-01, SEC-01
 * Homologado con el diseño modular de Horarios, Propietarios y Mascotas.
 *
 * @var array<string,mixed> $cita        Cita con detalle (siempre Programada aquí)
 * @var string              $csrfToken
 * @var string|null         $flashError
 * @var string              $nombreUsuario
 * @var string              $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

[$h, $m] = array_map('intval', explode(':', substr((string) $cita['hora_cita'], 0, 5)));
$fin = $h * 60 + $m + (int) $cita['duracion_estimada_min'];
$rango = sprintf('%02d:%02d – %02d:%02d', $h, $m, intdiv($fin, 60) % 24, $fin % 60);
$motivoMax = \App\Services\CitaService::MOTIVO_MAX;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Confirmar la cancelación de una cita">
    <title>Cancelar Cita — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/citas.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'citas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo card-confirmacion">
        <div class="card-header-actions">
            <div>
                <h2>
                    Confirmar Cancelación de Cita
                    <span class="estado-badge estado-programada"><?= mb_strtoupper($e($cita['estado']), 'UTF-8') ?></span>
                </h2>
                <p class="text-muted">Revisa los detalles antes de confirmar. Al cancelar esta cita, la franja horaria quedará libre inmediatamente en la agenda del veterinario.</p>
            </div>
            <div>
                <a href="/citas#listado" class="btn btn-sm btn-outline-dark">&larr; Volver al listado</a>
            </div>
        </div>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error mt-1" role="alert">
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>

        <div class="detail-grid mt-1">
            <div class="detail-stat-card">
                <div class="detail-stat-label">Paciente y Propietario</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;">
                    <?= $e($cita['mascota_nombre']) ?> <small class="text-muted">(<?= $e($cita['mascota_especie']) ?>)</small>
                </div>
                <div class="vet-spec" style="margin-top: 0.25rem;"><?= $e($cita['propietario_nombre']) ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Servicio Clínico</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;"><?= $e($cita['nombre_servicio']) ?></div>
                <div class="vet-spec" style="margin-top: 0.25rem;">Duración: <?= (int) $cita['duracion_estimada_min'] ?> min</div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Médico Veterinario</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;"><?= $e($cita['veterinario_nombre']) ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Fecha y Horario</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;">
                    <?= $e(date('d/m/Y', (int) strtotime((string) $cita['fecha_cita']))) ?>
                </div>
                <div class="vet-spec" style="margin-top: 0.25rem;"><?= $e($rango) ?></div>
            </div>
        </div>

        <form id="form-cancelar" method="POST" action="/citas/cancelar">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_cita" value="<?= (int) $cita['id_cita'] ?>">

            <div class="form-group">
                <label for="motivo">Motivo de la cancelación <span class="text-muted">(opcional)</span></label>
                <textarea id="motivo" name="motivo" rows="3" maxlength="<?= (int) $motivoMax ?>"
                          class="form-control" placeholder="Por ejemplo: el propietario solicitó reprogramar o no puede asistir..."></textarea>
                <small class="form-hint">Máximo <?= (int) $motivoMax ?> caracteres. Quedará registrado en el historial de citas canceladas.</small>
            </div>

            <div class="form-actions mt-2">
                <button type="submit" class="btn btn-danger">Sí, Cancelar Cita</button>
                <a href="/citas#listado" class="btn btn-outline-dark">No, Conservar Cita</a>
            </div>
        </form>
    </div>
</main>

<script src="/assets/js/citas.js?v=<?= time() ?>"></script>
</body>
</html>
