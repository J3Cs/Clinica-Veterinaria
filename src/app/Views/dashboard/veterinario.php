<?php
/**
 * Panel / Agenda del Veterinario — ACC-01, ARQ-01, US-06
 * Homologado con el diseño de Horarios, Propietarios, Mascotas y Citas.
 *
 * @var string                     $nombre
 * @var string                     $rol
 * @var string                     $correo
 * @var array<string,mixed>        $agenda        veterinario, citas, truncado, estado
 * @var array<array<string,mixed>> $citas         Citas del veterinario
 * @var string                     $estadoFiltro
 * @var string                     $busqueda
 * @var string|null                $flashError
 * @var string|null                $flashSuccess
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$estadoFiltro = $estadoFiltro ?? ($agenda['estado'] ?? 'Programada');
$busqueda     = $busqueda ?? '';

$horaRango = static function (string $hora, int $min): string {
    [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));
    $fin = $h * 60 + $m + $min;
    return sprintf('%02d:%02d – %02d:%02d', $h, $m, intdiv($fin, 60) % 24, $fin % 60);
};
$claseEstado = static fn(string $estado): string => 'estado-' . strtolower($estado);

$filtrosEstado = [
    'Programada' => 'Programadas',
    'Atendida'   => 'Atendidas',
    'Cancelada'  => 'Canceladas',
    'Todas'      => 'Todas mis citas',
];

$sinPerfil    = $agenda['veterinario'] === null;
$especialidad = $agenda['veterinario']['especialidad'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Agenda y panel de atención veterinaria">
    <title>Mi Agenda Veterinaria — Clínica Veterinaria</title>
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
                <h2>Mi Agenda de Atención Clínica</h2>
                <p class="text-muted">
                    Dr(a). <strong><?= $nombre ?></strong>
                    <?php if (!empty($especialidad)): ?>
                        &middot; <?= $e($especialidad) ?>
                    <?php endif; ?>
                    (<?= $correo ?>) — Consulta tus citas asignadas y registra las atenciones médicas.
                </p>
            </div>
            <div class="directory-actions">
                <a href="/mascotas" class="btn btn-outline-dark">Directorio de Mascotas</a>
                <a href="/propietarios" class="btn btn-outline-dark">Propietarios</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error mt-1" role="alert">
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success mt-1" role="status">
                    <span><?= $e($flashSuccess) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($sinPerfil): ?>
            <div class="alert alert-warning mt-1" role="status">
                <span>Tu cuenta no tiene un perfil de veterinario activo asociado, por eso no hay citas que mostrar. Consulta con el administrador.</span>
            </div>
        <?php else: ?>

            <!-- ── Buscador y Filtro de Estado en Agenda ── -->
            <div class="directory-toolbar mt-1">
                <form method="GET" action="/veterinario/agenda" class="directory-search-form" role="search">
                    <div class="directory-search-input-wrap">
                        <input
                            type="search"
                            id="agenda-search-input"
                            name="q"
                            class="directory-search-input"
                            placeholder="Buscar en mi agenda por mascota, especie, propietario, servicio o fecha..."
                            value="<?= $e($busqueda) ?>"
                            autocomplete="off"
                            aria-label="Buscar citas en mi agenda por mascota, especie, propietario, servicio o fecha"
                        >
                    </div>

                    <select name="estado" class="directory-filter-select" aria-label="Filtrar por estado de cita" onchange="this.form.submit()">
                        <?php foreach ($filtrosEstado as $valor => $etiqueta): ?>
                            <option value="<?= $e($valor) ?>" <?= $estadoFiltro === $valor ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if ($busqueda !== '' || $estadoFiltro !== 'Programada'): ?>
                        <a href="/veterinario/agenda" class="btn btn-outline-dark">Limpiar</a>
                    <?php endif; ?>
                </form>

                <div class="directory-meta">
                    <span id="agenda-search-count" aria-live="polite">
                        Mostrando <strong><?= count($citas) ?></strong> cita(s)
                        <?php if ($busqueda !== ''): ?>
                            para «<strong><?= $e($busqueda) ?></strong>»
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if (empty($citas)): ?>
                <div class="empty-state" role="status">
                    <?php if ($busqueda !== '' || $estadoFiltro !== 'Programada'): ?>
                        <h3>No se encontraron citas</h3>
                        <p>Ninguna cita en tu agenda coincide con los filtros seleccionados.</p>
                        <a href="/veterinario/agenda" class="btn btn-outline-dark">Ver citas programadas</a>
                    <?php else: ?>
                        <h3>No tienes citas programadas pendientes</h3>
                        <p>Cuando el administrador agende una nueva cita contigo, aparecerá aquí ordenada por fecha y hora.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <p class="table-helper-text">
                    Selecciona cualquier tarjeta de cita para registrar la consulta médica o consultar las mascotas del propietario.
                </p>

                <!-- Barra de acción contextual que aparece al seleccionar cualquier tarjeta -->
                <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                    <div class="selection-info">
                        <span class="selection-badge">Seleccionada</span>
                        <span id="selection-summary">Cita seleccionada</span>
                        <span id="selection-policy-hint" class="selection-policy-hint"></span>
                    </div>
                    <div class="selection-buttons">
                        <button type="button" id="btn-atender-selected" class="btn-action-reactivate" style="display: none;">
                            Registrar Consulta Médica
                        </button>
                        <button type="button" id="btn-ver-mascota-agenda" class="btn-action-secondary">
                            Buscar Mascota en Directorio
                        </button>
                        <button type="button" id="btn-cancel-agenda" class="btn-action-cancel">
                            Deseleccionar
                        </button>
                    </div>
                </div>

                <!-- Vista de Tarjetas Seleccionables Responsivas -->
                <div class="table-container-center">
                    <table class="data-table-horarios" id="agenda-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Fecha</th>
                                <th>Horario</th>
                                <th>Paciente (Mascota)</th>
                                <th>Propietario Responsable</th>
                                <th>Servicio Clínico</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="agenda-table-body">
                            <?php foreach ($citas as $c): ?>
                                <?php
                                    $idCita      = (int) $c['id_cita'];
                                    $estado      = (string) ($c['estado'] ?? 'Programada');
                                    $fechaFmt    = date('d/m/Y', (int) strtotime((string) $c['fecha_cita']));
                                    $durMin      = (int) ($c['duracion_estimada_min'] ?? 30);
                                    $rangoHora   = $horaRango((string) $c['hora_cita'], $durMin);
                                    $esCancelada = ($estado === 'Cancelada');
                                ?>
                                <tr class="horario-row agenda-row <?= $esCancelada ? 'row-inactivo' : '' ?>"
                                    data-id="<?= $idCita ?>"
                                    data-mascota="<?= $e($c['mascota_nombre']) ?>"
                                    data-especie="<?= $e($c['mascota_especie'] ?? '') ?>"
                                    data-propietario="<?= $e($c['propietario_nombre']) ?>"
                                    data-servicio="<?= $e($c['nombre_servicio']) ?>"
                                    data-fecha="<?= $e($fechaFmt) ?>"
                                    data-hora="<?= $e($rangoHora) ?>"
                                    data-estado="<?= $e($estado) ?>"
                                    data-resumen="<?= $e($c['mascota_nombre'] . ' (' . ($c['mascota_especie'] ?? '') . ') — ' . $fechaFmt . ' ' . $rangoHora) ?>"
                                    tabindex="0"
                                    role="button"
                                    aria-pressed="false"
                                >
                                    <td>
                                        <span class="custom-radio-indicator"></span>
                                    </td>
                                    <td data-label="Fecha">
                                        <span class="hora-text"><?= $e($fechaFmt) ?></span>
                                    </td>
                                    <td data-label="Horario">
                                        <div class="vet-info-centered">
                                            <span class="hora-text"><?= $e($rangoHora) ?></span>
                                            <span class="vet-spec"><?= $durMin ?> min</span>
                                        </div>
                                    </td>
                                    <td data-label="Paciente (Mascota)">
                                        <div class="vet-info-centered">
                                            <span class="vet-name"><?= $e($c['mascota_nombre']) ?></span>
                                            <span class="vet-spec">
                                                <?= $e($c['mascota_especie'] ?? '') ?><?= !empty($c['mascota_raza']) ? ' · ' . $e($c['mascota_raza']) : '' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Propietario">
                                        <div class="vet-info-centered">
                                            <span class="vet-name" style="font-size: 0.93rem;"><?= $e($c['propietario_nombre']) ?></span>
                                            <?php if (!empty($c['propietario_telefono'])): ?>
                                                <span class="vet-spec">Tel.: <?= $e($c['propietario_telefono']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Servicio Clínico">
                                        <div class="vet-info-centered">
                                            <span class="vet-name" style="font-size: 0.93rem; font-weight: 600;">
                                                <?= $e($c['nombre_servicio']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Estado">
                                        <div class="vet-info-centered">
                                            <span class="estado-badge <?= $e($claseEstado($estado)) ?>">
                                                <?= mb_strtoupper($e($estado), 'UTF-8') ?>
                                            </span>
                                            <?php if ($esCancelada && !empty($c['motivo_cancelacion'])): ?>
                                                <span class="vet-spec" title="<?= $e($c['motivo_cancelacion']) ?>">
                                                    Motivo: <?= $e(mb_strimwidth((string) $c['motivo_cancelacion'], 0, 34, '...')) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="agenda-no-matches-row" style="display: none;">
                                <td colspan="7">
                                    <div class="empty-state" style="margin: 0.5rem 0; padding: 1.5rem;" aria-live="polite">
                                        <strong>Ninguna cita coincide con tu búsqueda rápida.</strong>
                                        <p class="text-muted" style="margin: 0.25rem 0 0;">Intenta con otro nombre de mascota, propietario, especie o servicio.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($agenda['truncado'])): ?>
                    <p class="text-muted mt-2" style="text-align: center;">Se muestran las primeras <?= (int) \App\Services\ConsultaService::LIMITE_AGENDA ?> citas.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/consultas.js?v=<?= time() ?>"></script>
</body>
</html>
