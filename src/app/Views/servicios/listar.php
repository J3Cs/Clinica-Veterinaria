<?php
/**
 * Listado de Catálogo de Servicios — US-10 (T-069), UI-01, ACC-01, SEC-01, BAJA-01
 *
 * @var array<array<string,mixed>> $servicios
 * @var string                     $csrfToken
 * @var string|null                $flashSuccess
 * @var string|null                $flashError
 * @var string                     $nombreUsuario
 * @var string                     $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catálogo de servicios clínicos veterinarios">
    <title>Catálogo de Servicios — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/servicios.css">
</head>
<body class="dashboard-page">

<?php $navActivo = 'servicios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card">
        <div class="card-header-actions">
            <div>
                <h2>Catálogo de Servicios Clínicos</h2>
                <p class="text-muted">Administración de servicios veterinarios, duración estimada y disponibilidad para agendamiento de citas.</p>
            </div>
            <div>
                <a href="/servicios/crear" class="btn btn-primary">+ Nuevo Servicio</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success mt-1" role="status">
                    <span aria-hidden="true">✅</span>
                    <span><?= $e($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error mt-1" role="alert">
                    <span aria-hidden="true">⚠️</span>
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($servicios)): ?>
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">🩺</div>
                <h3>No hay servicios registrados</h3>
                <p>Actualmente no existe ningún servicio en el catálogo de la clínica.</p>
                <a href="/servicios/crear" class="btn btn-primary">+ Registrar el Primer Servicio</a>
            </div>
        <?php else: ?>
            <div class="table-responsive mt-2">
                <table class="table" id="servicios-table">
                    <thead>
                        <tr>
                            <th>Nombre del Servicio</th>
                            <th>Descripción</th>
                            <th>Duración Estimada</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicios as $s): ?>
                            <?php $activo = (int) ($s['activo'] ?? 0) === 1; ?>
                            <tr class="<?= $activo ? '' : 'servicio-inactivo-row' ?>">
                                <td>
                                    <strong><?= $e($s['nombre_servicio']) ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($s['descripcion'])): ?>
                                        <?= $e($s['descripcion']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="duracion-badge">⏱️ <?= (int) $s['duracion_estimada_min'] ?> min</span>
                                </td>
                                <td>
                                    <span class="badge <?= $activo ? 'badge-vet' : 'badge-inactivo' ?>">
                                        <?= $activo ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones-servicio">
                                        <a href="/servicios/editar?id=<?= (int) $s['id_servicio'] ?>" class="btn btn-sm btn-outline-dark">
                                            Editar
                                        </a>
                                        <form method="POST" action="/servicios/estado" class="form-estado-servicio" data-nombre="<?= $e($s['nombre_servicio']) ?>" data-accion="<?= $activo ? 'desactivar' : 'activar' ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                            <input type="hidden" name="id_servicio" value="<?= (int) $s['id_servicio'] ?>">
                                            <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                                            <?php if ($activo): ?>
                                                <button type="submit" class="btn btn-sm btn-danger">Desactivar</button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-primary">Activar</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/servicios.js"></script>
</body>
</html>
