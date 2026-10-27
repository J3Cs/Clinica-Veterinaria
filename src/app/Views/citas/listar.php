<?php
/**
 * Pestaña «Citas»: agendar + listado — US-04 y US-05 (T-040), UI-01, SEC-01, ACC-01
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
 * @var string                      $nombreUsuario
 * @var string                      $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$horaRango = static function (string $hora, int $min): string {
    [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));
    $fin = $h * 60 + $m + $min;
    return sprintf('%02d:%02d – %02d:%02d', $h, $m, intdiv($fin, 60) % 24, $fin % 60);
};
$claseEstado = static fn(string $estado): string => 'estado-' . strtolower($estado);

$filtrosEstado = ['Programada' => 'Programadas', 'Atendida' => 'Atendidas', 'Cancelada' => 'Canceladas', 'Todas' => 'Todas'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Agenda de citas veterinarias">
    <title>Citas — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/citas.css">
</head>
<body class="dashboard-page">

<?php $navActivo = 'citas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">

    <div class="citas-cabecera">
        <h1>Citas</h1>
        <nav class="citas-atajos" aria-label="Secciones de la página">
            <a href="#agendar">Agendar</a>
            <a href="#listado">Listado</a>
        </nav>
    </div>

    <div id="alert-container" aria-live="polite">
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error" role="alert">
                <span aria-hidden="true">⚠️</span>
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success" role="status">
                <span aria-hidden="true">✅</span>
                <span><?= $e($flashSuccess) ?></span>
            </div>
        <?php endif; ?>
        <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
            <span aria-hidden="true">ℹ️</span>
            <span id="js-error-text"></span>
        </div>
    </div>

    <?php include __DIR__ . '/crear.php'; ?>

    <section class="card" id="listado" aria-labelledby="titulo-listado">
        <div class="card-header-actions">
            <div>
                <h2 id="titulo-listado">Listado de citas</h2>
                <p class="text-muted">Ordenadas de la más próxima a la más lejana. Solo las citas Programadas pueden cancelarse.</p>
            </div>

            <form method="GET" action="/citas" class="filtro-estado">
                <?php if ($consulta !== null): ?>
                    <input type="hidden" name="consultar" value="1">
                    <?php foreach ($filtros as $campo => $valor): ?>
                        <input type="hidden" name="<?= $e($campo) ?>" value="<?= $e($valor) ?>">
                    <?php endforeach; ?>
                <?php endif; ?>
                <label for="estado" class="sr-only">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <?php foreach ($filtrosEstado as $valor => $etiqueta): ?>
                        <option value="<?= $e($valor) ?>" <?= $estadoFiltro === $valor ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-dark">Filtrar</button>
            </form>
        </div>

        <?php if (empty($citas)): ?>
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">📅</div>
                <h3>No hay citas <?= $estadoFiltro === 'Todas' ? '' : $e(strtolower($filtrosEstado[$estadoFiltro] ?? '')) ?></h3>
                <p>Cuando agendes una cita aparecerá aquí.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive mt-2">
                <table class="table" id="citas-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Mascota</th>
                            <th>Servicio</th>
                            <th>Veterinario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $c): ?>
                            <tr>
                                <td><?= $e(date('d/m/Y', (int) strtotime((string) $c['fecha_cita']))) ?></td>
                                <td><?= $e($horaRango((string) $c['hora_cita'], (int) $c['duracion_estimada_min'])) ?></td>
                                <td>
                                    <strong>🐾 <?= $e($c['mascota_nombre']) ?></strong>
                                    <br><small class="text-muted"><?= $e($c['propietario_nombre']) ?></small>
                                </td>
                                <td><?= $e($c['nombre_servicio']) ?></td>
                                <td><?= $e($c['veterinario_nombre']) ?></td>
                                <td>
                                    <span class="estado-badge <?= $e($claseEstado((string) $c['estado'])) ?>"><?= $e($c['estado']) ?></span>
                                    <?php if ($c['estado'] === 'Cancelada' && !empty($c['motivo_cancelacion'])): ?>
                                        <br><small class="text-muted motivo-cancelacion">Motivo: <?= $e($c['motivo_cancelacion']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['estado'] === 'Programada'): ?>
                                        <a href="/citas/cancelar?id=<?= (int) $c['id_cita'] ?>" class="btn btn-sm btn-danger">Cancelar</a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($listado['truncado'])): ?>
                <p class="text-muted mt-2">Se muestran las primeras <?= (int) $listado['limite'] ?> citas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/citas.js"></script>
</body>
</html>
