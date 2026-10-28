<?php
/** US-07: expediente clínico consolidado, de más reciente a más antiguo. */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial médico — <?= $e($mascota['nombre']) ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/modulos.css">
    <link rel="stylesheet" href="/assets/css/consultas.css">
</head>
<body class="dashboard-page">
<?php $navActivo = 'mascotas'; include __DIR__ . '/../partials/navbar.php'; ?>
<main class="container mt-2">
    <section class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>Historial médico de <?= $e($mascota['nombre']) ?></h2>
                <p class="text-muted">Expediente cronológico individual. Las consultas se obtienen de consultas_veterinarias y los antecedentes de historial_mascota.</p>
            </div>
            <a class="btn btn-outline-dark" href="/mascotas">&larr; Volver a mascotas</a>
        </div>
        <div class="detail-grid mt-1">
            <div class="detail-stat-card"><div class="detail-stat-label">Paciente</div><div class="detail-stat-value"><?= $e($mascota['nombre']) ?></div><div class="vet-spec"><?= $e($mascota['especie'] ?? '') ?><?= !empty($mascota['raza']) ? ' · '.$e($mascota['raza']) : '' ?></div></div>
            <div class="detail-stat-card"><div class="detail-stat-label">Propietario</div><div class="detail-stat-value"><?= $e($mascota['propietario_nombre'] ?? 'Sin asignar') ?></div></div>
            <div class="detail-stat-card"><div class="detail-stat-label">Registros</div><div class="detail-stat-value"><?= count($historial) ?></div></div>
        </div>
        <?php if (!$historial): ?>
            <div class="empty-state" role="status"><h3>Sin historial médico</h3><p>Esta mascota aún no tiene consultas ni antecedentes registrados.</p></div>
        <?php else: ?>
            <ul class="historial-lista">
            <?php foreach ($historial as $ev): ?>
                <li class="historial-evento">
                    <div class="historial-cabecera">
                        <span class="evento-badge <?= $ev['tipo'] === 'Consulta' ? 'evento-consulta' : 'evento-antecedente' ?>"><?= mb_strtoupper($e($ev['tipo']), 'UTF-8') ?></span>
                        <?php if ($ev['fecha'] !== ''): ?><span class="hora-text"><?= $e($ev['tipo'] === 'Consulta' ? date('d/m/Y H:i', strtotime($ev['fecha'])) : date('d/m/Y', strtotime($ev['fecha']))) ?></span><?php else: ?><span class="text-muted">Sin fecha registrada</span><?php endif; ?>
                        <?php if (!empty($ev['veterinario'])): ?><span class="badge-relation badge-relation-clinical"><?= $e($ev['veterinario']) ?></span><?php endif; ?>
                        <?php if (!empty($ev['servicio'])): ?><span class="badge-relation"><?= $e($ev['servicio']) ?></span><?php endif; ?>
                    </div>
                    <p class="historial-titulo"><strong><?= $ev['tipo'] === 'Consulta' ? 'Diagnóstico: ' : 'Condición médica: ' ?></strong><?= $e($ev['titulo']) ?></p>
                    <?php if ($ev['tipo'] === 'Consulta'): ?>
                        <?php if (!empty($ev['tratamiento'])): ?><p class="texto-clinico"><strong>Tratamiento:</strong> <?= $e($ev['tratamiento']) ?></p><?php endif; ?>
                        <?php if (!empty($ev['observaciones'])): ?><p class="texto-clinico"><strong>Observaciones:</strong> <?= $e($ev['observaciones']) ?></p><?php endif; ?>
                        <?php if (!empty($ev['servicios_adicionales'])): ?><p class="texto-clinico"><strong>Servicios adicionales:</strong> <?= $e($ev['servicios_adicionales']) ?></p><?php endif; ?>
                    <?php else: ?>
                        <?php if (!empty($ev['detalle'])): ?><p class="texto-clinico"><strong>Detalle:</strong> <?= $e($ev['detalle']) ?></p><?php endif; ?>
                        <?php if (!empty($ev['alergias'])): ?><p class="texto-clinico"><strong>Alergias conocidas:</strong> <?= $e($ev['alergias']) ?></p><?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
