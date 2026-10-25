<?php
/**
 * Confirmación de cancelación de una cita — US-05 (T-040), UI-01, SEC-01
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
    <title>Cancelar cita — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/citas.css">
</head>
<body class="dashboard-page">

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <a href="/admin/dashboard" class="btn btn-sm btn-outline">Dashboard</a>
        <a href="/propietarios" class="btn btn-sm btn-outline">Propietarios</a>
        <a href="/mascotas" class="btn btn-sm btn-outline">Mascotas</a>
        <a href="/citas" class="btn btn-sm btn-outline" aria-current="page">Citas</a>
        <span class="navbar-user">
            <?= $nombreUsuario ?> &middot; <span class="badge badge-admin"><?= ucfirst($rolUsuario) ?></span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">
    <div class="card card-confirmacion">
        <h2>Cancelar cita</h2>
        <p class="text-muted">Revisa los datos. Al cancelar, el horario quedará libre para otra cita.</p>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error" role="alert">
                <span aria-hidden="true">⚠️</span>
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>

        <dl class="resumen-cita">
            <dt>Estado actual</dt>
            <dd><span class="estado-badge estado-programada"><?= $e($cita['estado']) ?></span></dd>
            <dt>Mascota</dt>
            <dd><?= $e($cita['mascota_nombre']) ?> (<?= $e($cita['mascota_especie']) ?>) — <?= $e($cita['propietario_nombre']) ?></dd>
            <dt>Servicio</dt>
            <dd><?= $e($cita['nombre_servicio']) ?></dd>
            <dt>Veterinario</dt>
            <dd><?= $e($cita['veterinario_nombre']) ?></dd>
            <dt>Fecha</dt>
            <dd><?= $e(date('d/m/Y', (int) strtotime((string) $cita['fecha_cita']))) ?></dd>
            <dt>Hora</dt>
            <dd><?= $e($rango) ?></dd>
        </dl>

        <form id="form-cancelar" method="POST" action="/citas/cancelar">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_cita" value="<?= (int) $cita['id_cita'] ?>">

            <div class="form-group">
                <label for="motivo">Motivo de la cancelación <span class="text-muted">(opcional)</span></label>
                <textarea id="motivo" name="motivo" rows="3" maxlength="<?= (int) $motivoMax ?>"
                          class="form-control" placeholder="Por ejemplo: el propietario no puede asistir"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Sí, cancelar cita</button>
                <a href="/citas" class="btn btn-outline-dark">No, volver al listado</a>
            </div>
        </form>
    </div>
</main>

<script src="/assets/js/citas.js"></script>
</body>
</html>
