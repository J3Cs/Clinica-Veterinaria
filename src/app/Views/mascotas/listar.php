<?php
/**
 * Directorio de Mascotas con Búsqueda, Relaciones Clínicas y Selección Interactiva
 * US-03, US-12 (T-079, T-080, T-090) — Cumple UI-01, TEC-01, BAJA-01, ACC-01, SEC-01
 *
 * @var array<array<string,mixed>> $mascotas
 * @var string                     $csrfToken
 * @var string                     $busqueda
 * @var string                     $estadoFiltro
 * @var int|null                   $filtroPropietario
 * @var array<string,mixed>|null   $propietarioFiltrado
 * @var string|null                $flashSuccess
 * @var string|null                $flashError
 * @var string                     $nombreUsuario
 * @var string                     $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$busqueda            = $busqueda ?? '';
$estadoFiltro        = $estadoFiltro ?? 'activos';
$filtroPropietario   = $filtroPropietario ?? null;
$propietarioFiltrado = $propietarioFiltrado ?? null;
$csrfToken           = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Mascotas — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'mascotas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>Directorio de Mascotas</h2>
                <p class="text-muted">Consulta de pacientes registrados, propietarios responsables e historial clínico.</p>
            </div>
            <?php if ($rolUsuario === 'administrador'): ?>
                <div>
                    <a href="/mascotas/crear<?= $filtroPropietario ? '?propietario_id=' . (int)$filtroPropietario : '' ?>" class="btn btn-primary">+ Registrar Mascota</a>
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

        <?php if ($propietarioFiltrado): ?>
            <div class="owner-summary-box mt-1">
                <div>
                    <strong>Mostrando mascotas de:</strong>
                    <?= $e($propietarioFiltrado['nombre_completo']) ?>
                    (DUI: <code><?= $e($propietarioFiltrado['dui']) ?></code>)
                </div>
                <div class="directory-actions">
                    <?php if ($rolUsuario === 'administrador'): ?>
                        <a href="/propietarios/editar?id=<?= (int)$propietarioFiltrado['id_propietario'] ?>" class="btn btn-sm btn-outline-dark">Ver Propietario</a>
                    <?php endif; ?>
                    <a href="/mascotas" class="btn btn-sm btn-outline-dark">Quitar filtro</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Buscador y Filtro de Estado ── -->
        <div class="directory-toolbar mt-1">
            <form method="GET" action="/mascotas" class="directory-search-form" role="search">
                <?php if ($filtroPropietario): ?>
                    <input type="hidden" name="id_propietario" value="<?= (int)$filtroPropietario ?>">
                <?php endif; ?>

                <div class="directory-search-input-wrap">
                    <input
                        type="search"
                        id="pet-search-input"
                        name="q"
                        class="directory-search-input"
                        placeholder="Buscar por nombre de mascota, especie, raza, propietario o DUI..."
                        value="<?= $e($busqueda) ?>"
                        autocomplete="off"
                        aria-label="Buscar mascotas por nombre, especie, raza, propietario o DUI"
                    >
                </div>

                <?php if ($rolUsuario === 'administrador'): ?>
                    <select name="estado" class="directory-filter-select" aria-label="Filtrar por estado" onchange="this.form.submit()">
                        <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>Activas</option>
                        <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>Todas</option>
                        <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>Inactivas</option>
                    </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if ($busqueda !== '' || $estadoFiltro !== 'activos' || $filtroPropietario !== null): ?>
                    <a href="/mascotas" class="btn btn-outline-dark">Limpiar</a>
                <?php endif; ?>
            </form>

            <div class="directory-meta">
                <span id="pet-search-count" aria-live="polite">
                    Mostrando <strong><?= count($mascotas) ?></strong> mascota(s)
                    <?php if ($busqueda !== ''): ?>
                        para la búsqueda «<strong><?= $e($busqueda) ?></strong>»
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (empty($mascotas)): ?>
            <div class="empty-state" role="status">
                <?php if ($busqueda !== '' || $estadoFiltro !== 'activos' || $filtroPropietario !== null): ?>
                    <h3>No se encontraron coincidencias</h3>
                    <p>Ninguna mascota coincide con los criterios de búsqueda indicados.</p>
                    <a href="/mascotas" class="btn btn-outline-dark">Ver todas las mascotas activas</a>
                <?php else: ?>
                    <h3>No hay mascotas registradas</h3>
                    <p>Actualmente no se encuentra ninguna mascota activa registrada en el sistema.</p>
                    <?php if ($rolUsuario === 'administrador'): ?>
                        <a href="/mascotas/crear" class="btn btn-primary">+ Registrar la Primera Mascota</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <p class="table-helper-text">
                Selecciona cualquier tarjeta de mascota para ver las acciones disponibles.
            </p>

            <!-- Barra de acción contextual que aparece al hacer clic en cualquier tarjeta -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionada</span>
                    <span id="selection-summary">Mascota seleccionada</span>
                    <span id="selection-policy-hint" class="selection-policy-hint"></span>
                </div>
                <div class="selection-buttons">
                    <?php if ($rolUsuario === 'administrador'): ?>
                        <button type="button" id="btn-edit-selected" class="btn-action-edit">
                            Ver Expediente / Editar
                        </button>
                        <button type="button" id="btn-ver-propietario-selected" class="btn-action-secondary">
                            Ver Propietario
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
                    <?php else: ?>
                        <button type="button" id="btn-filtrar-prop-selected" class="btn-action-secondary">
                            Ver Mascotas del Propietario
                        </button>
                        <button type="button" id="btn-ir-agenda-selected" class="btn-action-edit">
                            Ir a Mi Agenda
                        </button>
                    <?php endif; ?>

                    <button type="button" id="btn-cancel-selected" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <?php if ($rolUsuario === 'administrador'): ?>
                <form id="form-baja-seleccion" method="POST" action="/mascotas/baja" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="id_mascota" id="input-baja-id" value="">
                    <input type="hidden" name="accion" id="input-baja-accion" value="baja">
                </form>
            <?php endif; ?>

            <!-- Vista de Tarjetas Seleccionables Responsivas -->
            <div class="table-container-center">
                <table class="data-table-horarios" id="pets-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Mascota</th>
                            <th>Especie / Raza</th>
                            <th>Sexo</th>
                            <th>Propietario Responsable</th>
                            <th>Historial Registrado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="pets-table-body">
                        <?php foreach ($mascotas as $m): ?>
                            <?php
                                $idMascota      = (int) $m['id_mascota'];
                                $idProp         = (int) ($m['id_propietario'] ?? 0);
                                $activa         = (int) ($m['activo'] ?? 1) === 1;
                                $citasTotal     = (int) ($m['citas_total'] ?? 0);
                                $consultasTotal = (int) ($m['consultas_total'] ?? 0);
                                $historialTotal = (int) ($m['historial_total'] ?? 0);
                                $tieneDep       = ($citasTotal > 0 || $consultasTotal > 0 || $historialTotal > 0);
                            ?>
                            <tr class="horario-row pet-row <?= $activa ? '' : 'row-inactivo' ?>"
                                data-id="<?= $idMascota ?>"
                                data-id-propietario="<?= $idProp ?>"
                                data-resumen="<?= $e($m['nombre'] . ' (' . $m['especie'] . ') — Propietario: ' . ($m['propietario_nombre'] ?? 'Sin asignar')) ?>"
                                data-nombre="<?= $e($m['nombre']) ?>"
                                data-propietario="<?= $e($m['propietario_nombre'] ?? '') ?>"
                                data-dui="<?= $e($m['propietario_dui'] ?? '') ?>"
                                data-especie="<?= $e($m['especie']) ?>"
                                data-raza="<?= $e($m['raza'] ?? '') ?>"
                                data-activo="<?= $activa ? '1' : '0' ?>"
                                data-dependencias="<?= $tieneDep ? '1' : '0' ?>"
                                data-citas="<?= $citasTotal ?>"
                                data-consultas="<?= $consultasTotal ?>"
                                data-historial="<?= $historialTotal ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td data-label="Mascota">
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($m['nombre']) ?></span>
                                        <?php if (!empty($m['fecha_nacimiento'])): ?>
                                            <span class="vet-spec">Nac.: <?= $e($m['fecha_nacimiento']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($m['caracteristicas_relevantes'])): ?>
                                            <span class="vet-spec" title="<?= $e($m['caracteristicas_relevantes']) ?>">
                                                <?= $e(mb_strimwidth((string)$m['caracteristicas_relevantes'], 0, 38, '...')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Especie / Raza">
                                    <div class="vet-info-centered">
                                        <span class="hora-text"><?= $e($m['especie']) ?></span>
                                        <span class="vet-spec"><?= $e($m['raza'] ?: 'No especificada') ?></span>
                                    </div>
                                </td>
                                <td data-label="Sexo">
                                    <span class="badge <?= ($m['sexo'] ?? '') === 'Macho' ? 'badge-macho' : 'badge-hembra' ?>">
                                        <?= mb_strtoupper($e($m['sexo']), 'UTF-8') ?>
                                    </span>
                                </td>
                                <td data-label="Propietario">
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($m['propietario_nombre'] ?? 'Sin asignar') ?></span>
                                        <span class="vet-spec">DUI: <?= $e($m['propietario_dui'] ?? '—') ?></span>
                                    </div>
                                </td>
                                <td data-label="Historial">
                                    <div class="relations-group">
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

                                        <?php if ($historialTotal > 0): ?>
                                            <span class="badge-relation">
                                                <?= $historialTotal ?> antecedente(s)
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!$tieneDep): ?>
                                            <span class="badge-relation badge-relation-none">Sin registros previos</span>
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-outline-dark" href="/mascotas/historial?id=<?= (int) $m['id_mascota'] ?>">Ver historial médico</a>
                                    </div>
                                </td>
                                <td data-label="Estado">
                                    <span class="badge <?= $activa ? 'badge-vet' : 'badge-inactivo' ?>">
                                        <?= $activa ? 'ACTIVA' : 'INACTIVA' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="pets-no-matches-row" style="display:none;">
                            <td colspan="7">
                                <div class="search-no-results" aria-live="polite">
                                    Ninguna mascota coincide con tu búsqueda.
                                    <br><small>Intenta con el nombre del propietario, el DUI o la especie.</small>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/mascotas.js?v=<?= time() ?>"></script>
</body>
</html>
