<?php
/**
 * Pestaña «Citas»: agendar + listado — US-04 y US-05 (T-040), UI-01, SEC-01, ACC-01
 * Homologado con el diseño de Horarios, Propietarios, Mascotas y Veterinarios.
 *
 * @var string                      $csrfToken
 * @var string|null                 $flashError
 * @var string|null                 $flashSuccess
 * @var array<string,string>        $filtros       Selección del formulario de agendar
 * @var array<string,array>         $opciones
 * @var array<string,mixed>|null    $consulta
 * @var array<string,string>        $fieldErrors
 * @var array<array<string,mixed>>  $citas         Citas del listado
 * @var string                      $estadoFiltro  Programada | Atendida | Cancelada | Todas
 * @var string                      $busqueda
 * @var string                      $nombreUsuario
 * @var string                      $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$busqueda = $busqueda ?? '';

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
    'Todas'      => 'Todas las citas',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Agenda y administración de citas veterinarias">
    <title>Gestión de Citas — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/citas.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'citas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">

    <div id="alert-container" class="card-modulo" style="margin-bottom: 1rem;" aria-live="polite">
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error" role="alert">
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success" role="status">
                <span><?= $e($flashSuccess) ?></span>
            </div>
        <?php endif; ?>
        <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
            <span id="js-error-text"></span>
        </div>
    </div>

    <?php include __DIR__ . '/crear.php'; ?>

    <section class="card card-modulo" id="listado" aria-labelledby="titulo-listado">
        <div class="card-header-actions">
            <div>
                <h2 id="titulo-listado">Listado y Control de Citas</h2>
                <p class="text-muted">Ordenadas de la más próxima a la más lejana. Solo las citas en estado Programada pueden cancelarse.</p>
            </div>
            <div>
                <a href="#agendar" class="btn btn-primary">+ Agendar Cita &uarr;</a>
            </div>
        </div>

        <!-- ── Buscador y Filtro de Estado (Estilo Directorio) ── -->
        <div class="directory-toolbar mt-1">
            <form method="GET" action="/citas#listado" class="directory-search-form" role="search">
                <?php if ($consulta !== null): ?>
                    <input type="hidden" name="consultar" value="1">
                    <?php foreach ($filtros as $campo => $valor): ?>
                        <input type="hidden" name="<?= $e($campo) ?>" value="<?= $e($valor) ?>">
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="directory-search-input-wrap">
                    <input
                        type="search"
                        id="cita-search-input"
                        name="q"
                        class="directory-search-input"
                        placeholder="Buscar por mascota, propietario, veterinario, servicio o fecha..."
                        value="<?= $e($busqueda) ?>"
                        autocomplete="off"
                        aria-label="Buscar citas por mascota, propietario, veterinario, servicio o fecha"
                    >
                </div>

                <label for="estado" class="sr-only">Estado</label>
                <select id="estado" name="estado" class="directory-filter-select" aria-label="Filtrar por estado de cita" onchange="this.form.submit()">
                    <?php foreach ($filtrosEstado as $valor => $etiqueta): ?>
                        <option value="<?= $e($valor) ?>" <?= $estadoFiltro === $valor ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">Filtrar</button>
                <?php if ($busqueda !== '' || $estadoFiltro !== 'Programada'): ?>
                    <a href="/citas#listado" class="btn btn-outline-dark">Limpiar</a>
                <?php endif; ?>
            </form>

            <div class="directory-meta">
                <span id="cita-search-count" aria-live="polite">
                    Mostrando <strong><?= count($citas) ?></strong> cita(s)
                    <?php if ($busqueda !== ''): ?>
                        para «<strong><?= $e($busqueda) ?></strong>»
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (empty($citas)): ?>
            <div class="empty-state" role="status">
                <h3>No hay citas <?= $estadoFiltro === 'Todas' ? 'registradas' : $e(strtolower($filtrosEstado[$estadoFiltro] ?? '')) ?></h3>
                <p>No se encontraron citas médicas con los filtros actuales.</p>
                <?php if ($busqueda !== '' || $estadoFiltro !== 'Programada'): ?>
                    <a href="/citas#listado" class="btn btn-outline-dark">Ver citas programadas</a>
                <?php else: ?>
                    <a href="#agendar" class="btn btn-primary">+ Agendar Nueva Cita</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <p class="table-helper-text">
                Selecciona cualquier cita de la tabla para ver las acciones disponibles (cancelar cita programada o consultar expediente de la mascota).
            </p>

            <!-- Barra de acción contextual al seleccionar una fila (Estilo Horarios / Propietarios) -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionada</span>
                    <span id="selection-summary">Cita seleccionada</span>
                    <span id="selection-policy-hint" class="selection-policy-hint"></span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-ver-mascota-selected" class="btn-action-secondary">
                        Ver Expediente de Mascota
                    </button>
                    <button type="button" id="btn-cancelar-cita-selected" class="btn-action-delete" style="display: none;">
                        Cancelar Cita
                    </button>
                    <button type="button" id="btn-deselect-cita" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <!-- Vista Tabla Centrada y Justificada -->
            <div class="table-container-center">
                <table class="data-table-horarios" id="citas-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Mascota / Propietario</th>
                            <th>Servicio Clínico</th>
                            <th>Médico Veterinario</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="citas-table-body">
                        <?php foreach ($citas as $c): ?>
                            <?php
                                $idCita     = (int) $c['id_cita'];
                                $idMascota  = (int) ($c['id_mascota'] ?? 0);
                                $estado     = (string) ($c['estado'] ?? 'Programada');
                                $fechaFmt   = date('d/m/Y', (int) strtotime((string) $c['fecha_cita']));
                                $durMin     = (int) ($c['duracion_estimada_min'] ?? 30);
                                $rangoHora  = $horaRango((string) $c['hora_cita'], $durMin);
                                $esCancelada = ($estado === 'Cancelada');
                            ?>
                            <tr class="horario-row cita-row <?= $esCancelada ? 'row-inactivo' : '' ?>"
                                data-id="<?= $idCita ?>"
                                data-id-mascota="<?= $idMascota ?>"
                                data-mascota="<?= $e($c['mascota_nombre']) ?>"
                                data-propietario="<?= $e($c['propietario_nombre']) ?>"
                                data-servicio="<?= $e($c['nombre_servicio']) ?>"
                                data-veterinario="<?= $e($c['veterinario_nombre']) ?>"
                                data-fecha="<?= $e($fechaFmt) ?>"
                                data-hora="<?= $e($rangoHora) ?>"
                                data-estado="<?= $e($estado) ?>"
                                data-resumen="<?= $e($c['mascota_nombre'] . ' — ' . $fechaFmt . ' (' . $rangoHora . ')') ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td>
                                    <span class="hora-text"><?= $e($fechaFmt) ?></span>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="hora-text"><?= $e($rangoHora) ?></span>
                                        <span class="vet-spec"><?= $durMin ?> min</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="vet-name">
                                            <?= $e($c['mascota_nombre']) ?>
                                            <?php if (!empty($c['mascota_especie'])): ?>
                                                <small class="text-muted" style="font-weight: 500;">(<?= $e($c['mascota_especie']) ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                        <span class="vet-spec"><?= $e($c['propietario_nombre']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="vet-name" style="font-size: 0.93rem; font-weight: 600;">
                                            <?= $e($c['nombre_servicio']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="vet-name" style="font-size: 0.93rem;">
                                            <?= $e($c['veterinario_nombre']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="estado-badge <?= $e($claseEstado($estado)) ?>">
                                            <?= mb_strtoupper($e($estado), 'UTF-8') ?>
                                        </span>
                                        <?php if ($esCancelada && !empty($c['motivo_cancelacion'])): ?>
                                            <span class="vet-spec motivo-cancelacion" title="<?= $e($c['motivo_cancelacion']) ?>">
                                                Motivo: <?= $e(mb_strimwidth((string) $c['motivo_cancelacion'], 0, 38, '...')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="citas-no-matches-row" style="display: none;">
                            <td colspan="7">
                                <div class="empty-state" style="margin: 0.5rem 0; padding: 1.5rem;" aria-live="polite">
                                    <strong>Ninguna cita coincide con tu búsqueda rápida.</strong>
                                    <p class="text-muted" style="margin: 0.25rem 0 0;">Prueba con otro nombre de mascota, propietario, veterinario o fecha.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($listado['truncado'])): ?>
                <p class="text-muted mt-2" style="text-align: center;">Se muestran las primeras <?= (int) $listado['limite'] ?> citas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/citas.js?v=<?= time() ?>"></script>
</body>
</html>
