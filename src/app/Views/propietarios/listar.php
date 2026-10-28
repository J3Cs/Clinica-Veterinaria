<?php
/**
 * Directorio de Propietarios con Búsqueda, Relaciones y Selección Interactiva
 * T-019, T-079, T-080, T-090 — Cumple UI-01, BAJA-01, ACC-01, SEC-01
 *
 * @var array<array<string,mixed>> $propietarios
 * @var string                     $csrfToken
 * @var string                     $busqueda
 * @var string                     $estadoFiltro
 * @var string|null                $flashSuccess
 * @var string|null                $flashError
 * @var string                     $nombreUsuario
 * @var string                     $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$busqueda     = $busqueda ?? '';
$estadoFiltro = $estadoFiltro ?? 'activos';
$csrfToken    = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Propietarios — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'propietarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>Directorio de Propietarios</h2>
                <p class="text-muted">Consulta de propietarios registrados, sus mascotas asociadas y datos de contacto.</p>
            </div>
            <?php if ($rolUsuario === 'administrador'): ?>
                <div>
                    <a href="/propietarios/crear" class="btn btn-primary">+ Registrar Propietario</a>
                </div>
            <?php endif; ?>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success mt-1" role="status">
                    <span><?= $e($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error mt-1" role="alert">
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Buscador y Filtro de Estado ── -->
        <div class="directory-toolbar mt-1">
            <form method="GET" action="/propietarios" class="directory-search-form" role="search">
                <div class="directory-search-input-wrap">
                    <input
                        type="search"
                        id="owner-search-input"
                        name="q"
                        class="directory-search-input"
                        placeholder="Buscar por nombre, DUI (########-#), teléfono o correo..."
                        value="<?= $e($busqueda) ?>"
                        aria-label="Buscar propietarios por nombre, DUI, teléfono o correo"
                    >
                </div>

                <?php if ($rolUsuario === 'administrador'): ?>
                    <select name="estado" class="directory-filter-select" aria-label="Filtrar por estado" onchange="this.form.submit()">
                        <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>Activos</option>
                        <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if ($busqueda !== '' || $estadoFiltro !== 'activos'): ?>
                    <a href="/propietarios" class="btn btn-outline-dark">Limpiar</a>
                <?php endif; ?>
            </form>

            <div class="directory-meta">
                <span>
                    Mostrando <strong><?= count($propietarios) ?></strong> propietario(s)
                    <?php if ($busqueda !== ''): ?>
                        para la búsqueda «<strong><?= $e($busqueda) ?></strong>»
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (empty($propietarios)): ?>
            <div class="empty-state" role="status">
                <?php if ($busqueda !== '' || $estadoFiltro !== 'activos'): ?>
                    <h3>No se encontraron coincidencias</h3>
                    <p>Ningún propietario coincide con los criterios de búsqueda («<?= $e($busqueda) ?>»).</p>
                    <a href="/propietarios" class="btn btn-outline-dark">Ver todos los propietarios activos</a>
                <?php else: ?>
                    <h3>No hay propietarios registrados</h3>
                    <p>Actualmente no se encuentra ningún propietario activo registrado en el sistema.</p>
                    <?php if ($rolUsuario === 'administrador'): ?>
                        <a href="/propietarios/crear" class="btn btn-primary">+ Registrar el Primer Propietario</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <p class="table-helper-text">
                Selecciona cualquier propietario de la tabla para ver las acciones disponibles.
            </p>

            <!-- Barra de acción contextual que aparece al hacer clic en cualquier fila -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionado</span>
                    <span id="selection-summary">Propietario seleccionado</span>
                    <span id="selection-policy-hint" class="selection-policy-hint"></span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-ver-mascotas-selected" class="btn-action-secondary">
                        Ver Mascotas
                    </button>

                    <?php if ($rolUsuario === 'administrador'): ?>
                        <button type="button" id="btn-edit-selected" class="btn-action-edit">
                            Editar / Ver Detalle
                        </button>
                        <button type="button" id="btn-add-pet-selected" class="btn-action-secondary">
                            + Registrar Mascota
                        </button>
                        <button type="button" id="btn-baja-logica-selected" class="btn-action-warning" style="display: none;">
                            Desactivar
                        </button>
                        <button type="button" id="btn-delete-selected" class="btn-action-delete" style="display: none;">
                            Eliminar
                        </button>
                        <button type="button" id="btn-reactivate-selected" class="btn-action-reactivate" style="display: none;">
                            Reactivar
                        </button>
                    <?php endif; ?>

                    <button type="button" id="btn-cancel-selected" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <?php if ($rolUsuario === 'administrador'): ?>
                <form id="form-baja-seleccion" method="POST" action="/propietarios/baja" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="id_propietario" id="input-baja-id" value="">
                    <input type="hidden" name="accion" id="input-baja-accion" value="baja">
                </form>
            <?php endif; ?>

            <!-- Vista Tabla Centrada y Justificada -->
            <div class="table-container-center">
                <table class="data-table-horarios" id="owners-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>DUI</th>
                            <th>Propietario</th>
                            <th>Contacto</th>
                            <th>Registros Asociados</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($propietarios as $p): ?>
                            <?php
                                $idProp          = (int) $p['id_propietario'];
                                $activo          = (int) ($p['activo'] ?? 1) === 1;
                                $mascotasTotal   = (int) ($p['mascotas_total'] ?? 0);
                                $mascotasActivas = (int) ($p['mascotas_activas'] ?? 0);
                                $citasTotal      = (int) ($p['citas_total'] ?? 0);
                                $consultasTotal  = (int) ($p['consultas_total'] ?? 0);
                                $tieneDep        = ($mascotasTotal > 0 || $citasTotal > 0 || $consultasTotal > 0);
                            ?>
                            <tr class="horario-row owner-row <?= $activo ? '' : 'row-inactivo' ?>"
                                data-id="<?= $idProp ?>"
                                data-resumen="<?= $e($p['nombre_completo'] . ' — DUI: ' . $p['dui']) ?>"
                                data-nombre="<?= $e($p['nombre_completo']) ?>"
                                data-dui="<?= $e($p['dui']) ?>"
                                data-telefono="<?= $e($p['telefono'] ?? '') ?>"
                                data-correo="<?= $e($p['correo'] ?? '') ?>"
                                data-activo="<?= $activo ? '1' : '0' ?>"
                                data-dependencias="<?= $tieneDep ? '1' : '0' ?>"
                                data-mascotas="<?= $mascotasTotal ?>"
                                data-citas="<?= $citasTotal ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td>
                                    <span class="hora-text"><?= $e($p['dui']) ?></span>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($p['nombre_completo']) ?></span>
                                        <?php if (!empty($p['direccion'])): ?>
                                            <span class="vet-spec"><?= $e(mb_strimwidth((string)$p['direccion'], 0, 48, '...')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="vet-name" style="font-size: 0.92rem; font-weight: 600;">
                                            <?= $e($p['telefono'] ?: 'Sin teléfono') ?>
                                        </span>
                                        <?php if (!empty($p['correo'])): ?>
                                            <span class="vet-spec"><?= $e($p['correo']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="relations-group">
                                        <?php if ($mascotasTotal > 0): ?>
                                            <span class="badge-relation">
                                                <?= $mascotasTotal ?> mascota(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-relation badge-relation-none">Sin mascotas</span>
                                        <?php endif; ?>

                                        <?php if ($citasTotal > 0): ?>
                                            <span class="badge-relation badge-relation-clinical">
                                                <?= $citasTotal ?> cita(s)
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($consultasTotal > 0): ?>
                                            <span class="badge-relation badge-relation-history">
                                                <?= $consultasTotal ?> consulta(s)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $activo ? 'badge-vet' : 'badge-inactivo' ?>">
                                        <?= $activo ? 'ACTIVO' : 'INACTIVO' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/propietarios.js?v=<?= time() ?>"></script>
</body>
</html>
