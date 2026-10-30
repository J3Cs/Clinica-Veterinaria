<?php
/**
 * Catálogo de Servicios Clínicos con Búsqueda, Filtro y Selección Interactiva
 * US-10 (T-069), US-12 — Cumple UI-01, ACC-01, SEC-01, BAJA-01
 * Homologado con el diseño de Horarios, Propietarios, Mascotas y Veterinarios.
 *
 * @var array<array<string,mixed>> $servicios
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
$estadoFiltro = $estadoFiltro ?? 'todos';
$csrfToken    = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catálogo de servicios clínicos veterinarios">
    <title>Catálogo de Servicios — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/servicios.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'servicios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
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
            <form method="GET" action="/servicios" class="directory-search-form" role="search">
                <div class="directory-search-input-wrap">
                    <input
                        type="search"
                        id="service-search-input"
                        name="q"
                        class="directory-search-input"
                        placeholder="Buscar por nombre del servicio, descripción o duración (min)..."
                        value="<?= $e($busqueda) ?>"
                        autocomplete="off"
                        aria-label="Buscar servicios por nombre, descripción o duración"
                    >
                </div>

                <select name="estado" class="directory-filter-select" aria-label="Filtrar por estado" onchange="this.form.submit()">
                    <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>

                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if ($busqueda !== '' || $estadoFiltro !== 'todos'): ?>
                    <a href="/servicios" class="btn btn-outline-dark">Limpiar</a>
                <?php endif; ?>
            </form>

            <div class="directory-meta">
                <span id="service-search-count" aria-live="polite">
                    Mostrando <strong><?= count($servicios) ?></strong> servicio(s)
                    <?php if ($busqueda !== ''): ?>
                        para la búsqueda «<strong><?= $e($busqueda) ?></strong>»
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (empty($servicios)): ?>
            <div class="empty-state" role="status">
                <?php if ($busqueda !== '' || $estadoFiltro !== 'todos'): ?>
                    <h3>No se encontraron servicios</h3>
                    <p>Ningún servicio clínico coincide con los criterios seleccionados.</p>
                    <a href="/servicios" class="btn btn-outline-dark">Ver todos los servicios</a>
                <?php else: ?>
                    <h3>No hay servicios registrados</h3>
                    <p>Actualmente no existe ningún servicio en el catálogo de la clínica.</p>
                    <a href="/servicios/crear" class="btn btn-primary">+ Registrar el Primer Servicio</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <p class="table-helper-text">
                Selecciona cualquier servicio para editar su información o cambiar su disponibilidad en agenda.
            </p>

            <!-- Barra de acción contextual que aparece al hacer clic en cualquier tarjeta -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionado</span>
                    <span id="selection-summary">Servicio seleccionado</span>
                    <span id="selection-policy-hint" class="selection-policy-hint"></span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-edit-selected" class="btn-action-edit">
                        Editar Servicio
                    </button>
                    <button type="button" id="btn-agendar-servicio-selected" class="btn-action-secondary" style="display: none;">
                        Agendar con este Servicio
                    </button>
                    <button type="button" id="btn-deactivate-selected" class="btn-action-warning" style="display: none;">
                        Desactivar
                    </button>
                    <button type="button" id="btn-activate-selected" class="btn-action-reactivate" style="display: none;">
                        Reactivar
                    </button>
                    <button type="button" id="btn-cancel-selected" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <!-- Formulario oculto para cambiar estado (Activar / Desactivar) vía POST con CSRF -->
            <form id="form-estado-seleccion" method="POST" action="/servicios/estado" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="id_servicio" id="input-estado-id" value="">
                <input type="hidden" name="accion" id="input-estado-accion" value="">
            </form>

            <!-- Vista de Tarjetas Seleccionables Responsivas -->
            <div class="table-container-center">
                <table class="data-table-horarios tabla-escritorio" id="servicios-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Servicio Clínico</th>
                            <th>Descripción</th>
                            <th>Duración Estimada</th>
                            <th>Disponibilidad en Citas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="services-table-body">
                        <?php foreach ($servicios as $s): ?>
                            <?php
                                $idServicio = (int) $s['id_servicio'];
                                $activo     = (int) ($s['activo'] ?? 0) === 1;
                                $durMin     = (int) ($s['duracion_estimada_min'] ?? 30);
                                $desc       = trim((string) ($s['descripcion'] ?? ''));
                            ?>
                            <tr class="horario-row service-row <?= $activo ? '' : 'row-inactivo' ?>"
                                data-id="<?= $idServicio ?>"
                                data-nombre="<?= $e($s['nombre_servicio']) ?>"
                                data-descripcion="<?= $e($desc) ?>"
                                data-duracion="<?= $durMin ?>"
                                data-activo="<?= $activo ? '1' : '0' ?>"
                                data-resumen="<?= $e($s['nombre_servicio'] . ' (' . $durMin . ' min)') ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td data-label="Servicio Clínico">
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($s['nombre_servicio']) ?></span>
                                        <span class="vet-spec">Código #<?= $idServicio ?></span>
                                    </div>
                                </td>
                                <td data-label="Descripción">
                                    <div class="vet-info-centered">
                                        <?php if ($desc !== ''): ?>
                                            <span class="service-desc-text" title="<?= $e($desc) ?>">
                                                <?= $e(mb_strimwidth($desc, 0, 68, '...')) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="vet-spec">Sin descripción registrada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Duración Estimada">
                                    <span class="hora-text"><?= $durMin ?> min</span>
                                </td>
                                <td data-label="Disponibilidad">
                                    <div class="relations-group">
                                        <?php if ($activo): ?>
                                            <span class="badge-relation badge-relation-history">
                                                Habilitado en agenda
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-relation badge-relation-none">
                                                Oculto en agendamiento
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Estado">
                                    <span class="badge <?= $activo ? 'badge-vet' : 'badge-inactivo' ?>">
                                        <?= $activo ? 'ACTIVO' : 'INACTIVO' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="services-no-matches-row" style="display: none;">
                            <td colspan="6">
                                <div class="empty-state" style="margin: 0.5rem 0; padding: 1.5rem;" aria-live="polite">
                                    <strong>Ningún servicio coincide con tu filtro rápido.</strong>
                                    <p class="text-muted" style="margin: 0.25rem 0 0;">Intenta buscar por otro nombre, palabra clave o duración.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/servicios.js?v=<?= time() ?>"></script>
</body>
</html>
