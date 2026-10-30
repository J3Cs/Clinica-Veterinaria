<?php
/**
 * Directorio de Personal Médico Veterinario y Cuentas de Usuario
 * US-09 (T-063, T-064), US-12 — Cumple UI-01, BAJA-01, ACC-01, SEC-01
 *
 * @var array<array<string,mixed>> $veterinarios
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
    <title>Personal Veterinario — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'veterinarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>Directorio de Personal Veterinario</h2>
                <p class="text-muted">Gestión de médicos veterinarios, sus especialidades, licencias médicas y cuentas de acceso al sistema.</p>
            </div>
            <div>
                <a href="/veterinarios/crear" class="btn btn-primary">+ Registrar Veterinario</a>
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

        <!-- ── Buscador y Filtro de Estado Activo/Inactivo (T-063) ── -->
        <div class="directory-toolbar mt-1">
            <form method="GET" action="/veterinarios" class="directory-search-form" role="search">
                <div class="directory-search-input-wrap">
                    <input
                        type="search"
                        id="vet-search-input"
                        name="q"
                        class="directory-search-input"
                        placeholder="Buscar por nombre, usuario, correo, especialidad o licencia..."
                        value="<?= $e($busqueda) ?>"
                        aria-label="Buscar veterinarios por nombre, usuario, correo, especialidad o licencia médica"
                    >
                </div>

                <select name="estado" class="directory-filter-select" aria-label="Filtrar por estado" onchange="this.form.submit()">
                    <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>

                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if ($busqueda !== '' || $estadoFiltro !== 'activos'): ?>
                    <a href="/veterinarios" class="btn btn-outline-dark">Limpiar</a>
                <?php endif; ?>
            </form>

            <div class="directory-meta">
                <span>
                    Mostrando <strong><?= count($veterinarios) ?></strong> veterinario(s)
                    <?php if ($busqueda !== ''): ?>
                        para la búsqueda «<strong><?= $e($busqueda) ?></strong>»
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (empty($veterinarios)): ?>
            <div class="empty-state" role="status">
                <?php if ($busqueda !== '' || $estadoFiltro !== 'activos'): ?>
                    <h3>No se encontraron coincidencias</h3>
                    <p>Ningún médico veterinario coincide con los criterios seleccionados.</p>
                    <a href="/veterinarios" class="btn btn-outline-dark">Ver veterinarios activos</a>
                <?php else: ?>
                    <h3>No hay médicos veterinarios activos</h3>
                    <p>Registra el primer médico veterinario junto con su cuenta de usuario inicial para habilitar agendas y citas.</p>
                    <a href="/veterinarios/crear" class="btn btn-primary">+ Registrar Primer Veterinario</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <p class="table-helper-text">
                Selecciona cualquier tarjeta de médico veterinario para editar su ficha, consultar sus horarios o gestionar el estado de su cuenta.
            </p>

            <!-- Barra de acción contextual al seleccionar una tarjeta -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionado</span>
                    <span id="selection-summary">Veterinario seleccionado</span>
                    <span id="selection-policy-hint" class="selection-policy-hint"></span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-edit-selected" class="btn-action-edit">
                        Editar / Ver Detalle
                    </button>
                    <button type="button" id="btn-horarios-selected" class="btn-action-secondary">
                        Ver Horarios
                    </button>
                    <button type="button" id="btn-add-horario-selected" class="btn-action-secondary">
                        + Asignar Horario
                    </button>
                    <button type="button" id="btn-baja-logica-selected" class="btn-action-warning" style="display: none;">
                        Desactivar Cuenta
                    </button>
                    <button type="button" id="btn-delete-selected" class="btn-action-delete" style="display: none;">
                        Eliminar
                    </button>
                    <button type="button" id="btn-reactivate-selected" class="btn-action-reactivate" style="display: none;">
                        Reactivar Cuenta
                    </button>
                    <button type="button" id="btn-cancel-selected" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <form id="form-baja-seleccion" method="POST" action="/veterinarios/baja" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="id_veterinario" id="input-baja-id" value="">
                <input type="hidden" name="accion" id="input-baja-accion" value="baja">
            </form>

            <div class="table-container-center">
                <table class="data-table-horarios" id="vets-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Médico Veterinario</th>
                            <th>Especialidad / Licencia</th>
                            <th>Cuenta de Usuario</th>
                            <th>Relaciones Clínicas</th>
                            <th>Estado de Cuenta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veterinarios as $v): ?>
                            <?php
                                $idVet            = (int) $v['id_veterinario'];
                                $activo           = (int) ($v['activo'] ?? 1) === 1;
                                $citasTotal       = (int) ($v['citas_total'] ?? 0);
                                $citasProgramadas = (int) ($v['citas_programadas'] ?? 0);
                                $consultasTotal   = (int) ($v['consultas_total'] ?? 0);
                                $horariosTotal    = (int) ($v['horarios_total'] ?? 0);
                                $tieneDep         = ($citasTotal > 0 || $consultasTotal > 0);
                            ?>
                            <tr class="horario-row vet-row <?= $activo ? '' : 'row-inactivo' ?>"
                                data-id="<?= $idVet ?>"
                                data-resumen="<?= $e($v['nombre'] . ' (@' . $v['usuario'] . ')') ?>"
                                data-nombre="<?= $e($v['nombre']) ?>"
                                data-usuario="<?= $e($v['usuario']) ?>"
                                data-correo="<?= $e($v['correo']) ?>"
                                data-especialidad="<?= $e($v['especialidad'] ?? '') ?>"
                                data-licencia="<?= $e($v['licencia_medica'] ?? '') ?>"
                                data-telefono="<?= $e($v['telefono'] ?? '') ?>"
                                data-activo="<?= $activo ? '1' : '0' ?>"
                                data-dependencias="<?= $tieneDep ? '1' : '0' ?>"
                                data-citas="<?= $citasTotal ?>"
                                data-citas-programadas="<?= $citasProgramadas ?>"
                                data-consultas="<?= $consultasTotal ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td data-label="Médico Veterinario">
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($v['nombre']) ?></span>
                                        <span class="vet-spec"><?= $e($v['telefono'] ?: 'Sin teléfono registrado') ?></span>
                                    </div>
                                </td>
                                <td data-label="Especialidad / Licencia">
                                    <div class="vet-info-centered">
                                        <span class="vet-name" style="font-size: 0.92rem; font-weight: 600;">
                                            <?= $e($v['especialidad'] ?: 'Medicina General') ?>
                                        </span>
                                        <span class="vet-spec">
                                            Licencia: <?= $e($v['licencia_medica'] ?: 'No registrada') ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Cuenta de Usuario">
                                    <div class="vet-info-centered">
                                        <span class="hora-text">@<?= $e($v['usuario']) ?></span>
                                        <span class="vet-spec"><?= $e($v['correo']) ?></span>
                                    </div>
                                </td>
                                <td data-label="Relaciones Clínicas">
                                    <div class="relations-group">
                                        <?php if ($citasTotal > 0): ?>
                                            <span class="badge-relation badge-relation-clinical">
                                                <?= $citasTotal ?> cita(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-relation badge-relation-none">Sin citas</span>
                                        <?php endif; ?>

                                        <?php if ($consultasTotal > 0): ?>
                                            <span class="badge-relation badge-relation-history">
                                                <?= $consultasTotal ?> consulta(s)
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($horariosTotal > 0): ?>
                                            <span class="badge-relation">
                                                <?= $horariosTotal ?> horario(s)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Estado de Cuenta">
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

<script src="/assets/js/veterinarios.js?v=<?= time() ?>"></script>
</body>
</html>
