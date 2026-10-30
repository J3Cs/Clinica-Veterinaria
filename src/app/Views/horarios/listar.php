<?php
/**
 * Listado y Matriz de Horarios de Atención — US-11 (T-074), UI-01, ACC-01, SEC-01
 * Homologado con el diseño de tarjetas seleccionables y pantalla completa de modulos.css
 *
 * @var array<array<string,mixed>> $horarios
 * @var array<array<string,mixed>> $veterinarios
 * @var int|null                   $filtroVet
 * @var string                     $csrfToken
 * @var string|null                $flashSuccess
 * @var string|null                $flashError
 * @var string                     $nombreUsuario
 * @var string                     $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$diasNombres = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestión y asignación de horarios de atención médica">
    <title>Horarios de Atención — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/horarios.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'horarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo card-horarios">
        <div class="card-header-actions">
            <div>
                <h2>Horarios de Atención Médica</h2>
                <p class="text-muted">Defina los días y franjas de atención de cada veterinario para habilitar la disponibilidad de citas.</p>
            </div>
            <div>
                <a href="/horarios/crear" class="btn btn-primary">+ Asignar Horario</a>
            </div>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" role="alert"><?= $e($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-error" role="alert"><?= $e($flashError) ?></div>
        <?php endif; ?>

        <!-- Filtro por Veterinario -->
        <div class="filter-section">
            <form method="GET" action="/horarios" class="filter-form">
                <label for="filtro_vet" class="filter-label">Filtrar por veterinario:</label>
                <select name="id_veterinario" id="filtro_vet" class="filter-select" onchange="this.form.submit()">
                    <option value="">-- Todos los veterinarios --</option>
                    <?php foreach ($veterinarios as $v): ?>
                        <option value="<?= (int) $v['id_veterinario'] ?>" <?= ($filtroVet === (int)$v['id_veterinario']) ? 'selected' : '' ?>>
                            <?= $e($v['nombre']) ?><?= !empty($v['especialidad']) ? ' (' . $e($v['especialidad']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filtroVet): ?>
                    <a href="/horarios" class="btn btn-sm btn-outline-dark">Limpiar filtro</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($veterinarios)): ?>
            <div class="empty-state">
                <p class="empty-icon">🩺</p>
                <h3>No hay veterinarios registrados o activos</h3>
                <p class="text-muted">Para asignar horarios, primero deben existir médicos veterinarios con cuenta activa en el sistema.</p>
            </div>
        <?php elseif (empty($horarios)): ?>
            <div class="empty-state">
                <p class="empty-icon">📅</p>
                <h3>No se encontraron franjas de atención</h3>
                <p class="text-muted">No hay horarios configurados <?= $filtroVet ? 'para el veterinario seleccionado' : 'en la clínica' ?>. Utilice el botón "+ Asignar Horario" para crear la primera franja.</p>
                <a href="/horarios/crear" class="btn btn-primary mt-1">+ Asignar Franja Horaria</a>
            </div>
        <?php else: ?>

            <p class="table-helper-text">
                Selecciona cualquier horario para editar o eliminar la franja de atención.
            </p>

            <!-- Barra de acción contextual que aparece al hacer clic en cualquier tarjeta -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionado</span>
                    <span id="selection-summary">Franja horaria seleccionada</span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-edit-selected" class="btn-action-edit">
                        Editar Horario
                    </button>
                    <button type="button" id="btn-delete-selected" class="btn-action-delete">
                        Eliminar Horario
                    </button>
                    <button type="button" id="btn-cancel-selected" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <!-- Formulario oculto para enviar eliminación vía POST seguro CSRF -->
            <form id="form-eliminar-seleccion" method="POST" action="/horarios/eliminar" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="id_horario" id="input-delete-id" value="">
            </form>

            <!-- Vista de Tarjetas Seleccionables Responsivas -->
            <div class="table-container-center">
                <table class="data-table-horarios tabla-escritorio" id="tabla-horarios">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Veterinario</th>
                            <th>Día</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Duración Franja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horarios as $h): ?>
                            <?php
                                $inicio = substr($h['hora_inicio'], 0, 5);
                                $fin    = substr($h['hora_fin'], 0, 5);
                                $minutosTotal = (strtotime($h['hora_fin']) - strtotime($h['hora_inicio'])) / 60;
                                $horas = floor($minutosTotal / 60);
                                $mins  = $minutosTotal % 60;
                                $durTxt = ($horas > 0 ? $horas . ' h' : '') . ($mins > 0 ? ($horas > 0 ? ' ' : '') . $mins . ' min' : '');
                                $diaNombre = $diasNombres[(int)$h['dia_semana']] ?? 'Día ' . $h['dia_semana'];
                            ?>
                            <tr class="horario-row"
                                data-id="<?= (int)$h['id_horario'] ?>"
                                data-resumen="<?= $e($h['veterinario_nombre'] . ' — ' . $diaNombre . ' (' . $inicio . ' a ' . $fin . ')') ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false">
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td data-label="Veterinario">
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($h['veterinario_nombre']) ?></span>
                                        <?php if (!empty($h['especialidad'])): ?>
                                            <span class="vet-spec"><?= $e($h['especialidad']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Día">
                                    <span class="badge badge-dia badge-dia-<?= (int)$h['dia_semana'] ?>">
                                        <?= mb_strtoupper($e($diaNombre), 'UTF-8') ?>
                                    </span>
                                </td>
                                <td data-label="Hora Inicio">
                                    <span class="hora-text"><?= $e($inicio) ?></span>
                                </td>
                                <td data-label="Hora Fin">
                                    <span class="hora-text"><?= $e($fin) ?></span>
                                </td>
                                <td data-label="Duración Franja">
                                    <span class="duracion-text"><?= $e($durTxt) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('.horario-row');
    const actionBar = document.getElementById('selection-action-bar');
    const summarySpan = document.getElementById('selection-summary');
    const btnDelete = document.getElementById('btn-delete-selected');
    const btnEdit = document.getElementById('btn-edit-selected');
    const btnCancel = document.getElementById('btn-cancel-selected');
    const formDelete = document.getElementById('form-eliminar-seleccion');
    const inputDeleteId = document.getElementById('input-delete-id');

    let selectedRow = null;

    function deselectAll() {
        rows.forEach(function (r) {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedRow = null;
        if (actionBar) actionBar.style.display = 'none';
        if (inputDeleteId) inputDeleteId.value = '';
    }

    function selectRow(row) {
        if (selectedRow === row) {
            deselectAll();
            return;
        }

        rows.forEach(function (r) {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedRow = row;

        const id = row.getAttribute('data-id');
        const resumen = row.getAttribute('data-resumen') || 'Franja seleccionada';

        if (inputDeleteId) inputDeleteId.value = id;
        if (summarySpan) summarySpan.textContent = resumen;

        if (actionBar) {
            actionBar.style.display = 'flex';
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            selectRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectRow(this);
            }
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', function () {
            deselectAll();
        });
    }

    if (btnEdit) {
        btnEdit.addEventListener('click', function () {
            if (!selectedRow) return;
            const id = selectedRow.getAttribute('data-id');
            if (id) {
                window.location.href = '/horarios/editar?id=' + encodeURIComponent(id);
            }
        });
    }

    if (btnDelete) {
        btnDelete.addEventListener('click', function () {
            if (!selectedRow || !formDelete) return;
            const resumen = selectedRow.getAttribute('data-resumen') || '';
            const confirmMsg = '¿Está seguro de eliminar el horario seleccionado?\n\n' + resumen + '\n\nEl sistema verificará que no afecte citas médicas programadas.';
            if (window.confirm(confirmMsg)) {
                formDelete.submit();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && selectedRow) {
            deselectAll();
        }
    });
});
</script>
</body>
</html>
