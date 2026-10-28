<?php
/**
 * Listado y Matriz de Horarios de Atención — US-11 (T-074), UI-01, ACC-01, SEC-01
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
    <link rel="stylesheet" href="/assets/css/horarios.css?v=<?= time() ?>">
    <style>
        /* Estilos garantizados anti-caché para Horarios de Atención */
        .card-horarios {
            max-width: 1060px;
            margin: 0 auto;
        }
        .table-helper-text {
            font-size: 0.9rem;
            color: #475569;
            margin: 1rem 0 1.25rem 0;
            text-align: center;
            background: #f8fafc;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            border: 1px dashed #cbd5e1;
        }
        .table-container-center {
            width: 100%;
            display: flex;
            justify-content: center;
            margin: 1rem 0 2rem 0;
            overflow-x: auto;
        }
        .data-table-horarios {
            width: 100%;
            max-width: 940px;
            border-collapse: separate;
            border-spacing: 0 12px;
            margin: 0 auto;
        }
        .data-table-horarios thead th {
            padding: 0.85rem 1.75rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e293b;
            text-align: center;
            background: transparent;
            border-bottom: 2px solid #cbd5e1;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .horario-row {
            background-color: #ffffff;
            cursor: pointer;
            transition: all 0.18s ease;
            user-select: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
        }
        .horario-row:hover {
            background-color: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.08);
        }
        .data-table-horarios tbody td {
            padding: 1.15rem 1.75rem;
            vertical-align: middle;
            text-align: center;
            font-size: 0.95rem;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table-horarios tbody td:first-child {
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            border-left: 1px solid #e2e8f0;
            width: 56px;
            padding-left: 1.25rem;
            padding-right: 0.5rem;
        }
        .data-table-horarios tbody td:last-child {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            border-right: 1px solid #e2e8f0;
        }
        /* Estado cuando el usuario hace clic en una fila */
        .horario-row.is-selected {
            background-color: #ecfdf5 !important;
            box-shadow: 0 4px 14px rgba(44, 107, 79, 0.16);
        }
        .horario-row.is-selected td {
            border-top-color: #34d399;
            border-bottom-color: #34d399;
        }
        .horario-row.is-selected td:first-child {
            border-left: 5px solid #2c6b4f;
        }
        .horario-row.is-selected td:last-child {
            border-right-color: #34d399;
        }
        /* Radio / Check visual en cada fila */
        .custom-radio-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #94a3b8;
            background-color: #ffffff;
            transition: all 0.2s ease;
            vertical-align: middle;
            position: relative;
        }
        .horario-row:hover .custom-radio-indicator {
            border-color: #2c6b4f;
        }
        .horario-row.is-selected .custom-radio-indicator {
            border-color: #2c6b4f;
            background-color: #2c6b4f;
            box-shadow: inset 0 0 0 4px #ffffff;
        }
        /* Veterinario y Especialidad bien separados y centrados */
        .vet-info-centered {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }
        .vet-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 1rem;
            display: block;
        }
        .vet-spec {
            font-size: 0.84rem;
            color: #64748b;
            display: block;
        }
        /* Nueva tipografía moderna y limpia para la hora (No monospace) */
        .hora-text {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Helvetica, Arial, sans-serif;
            font-size: 0.96rem;
            font-weight: 600;
            color: #1e293b;
            padding: 0.4rem 1rem;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            letter-spacing: 0.4px;
            min-width: 78px;
        }
        .horario-row.is-selected .hora-text {
            background-color: #d1fae5;
            border-color: #6ee7b7;
            color: #065f46;
        }
        .duracion-text {
            display: inline-block;
            color: #475569;
            font-weight: 600;
            font-size: 0.92rem;
            background: #f8fafc;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
        }
        /* Barra flotante de acción al seleccionar */
        .selection-action-bar {
            position: sticky;
            top: 1rem;
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e293b;
            color: #ffffff;
            padding: 0.85rem 1.5rem;
            border-radius: 10px;
            margin: 0 auto 1.25rem auto;
            max-width: 940px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
        }
        .selection-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .selection-badge {
            background-color: #10b981;
            color: #ffffff;
            font-weight: 700;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.85rem;
        }
        .selection-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .btn-action-edit {
            background-color: #334155;
            color: #ffffff;
            border: 1px solid #475569;
            padding: 0.45rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.88rem;
        }
        .btn-action-edit:hover {
            background-color: #475569;
        }
        .btn-action-delete {
            background-color: #ef4444;
            color: #ffffff;
            border: 1px solid #dc2626;
            padding: 0.45rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.88rem;
        }
        .btn-action-delete:hover {
            background-color: #dc2626;
        }
        .btn-action-cancel {
            background-color: transparent;
            color: #cbd5e1;
            border: 1px solid #64748b;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            font-size: 0.88rem;
        }
        .btn-action-cancel:hover {
            background-color: rgba(255,255,255,0.1);
            color: #ffffff;
        }
    </style>
</head>
<body class="dashboard-page">

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <a href="/admin/dashboard" class="btn btn-sm btn-outline">Dashboard</a>
        <a href="/propietarios" class="btn btn-sm btn-outline">Propietarios</a>
        <a href="/mascotas" class="btn btn-sm btn-outline">Mascotas</a>
        <a href="/citas" class="btn btn-sm btn-outline">Citas</a>
        <a href="/servicios" class="btn btn-sm btn-outline">Servicios</a>
        <a href="/horarios" class="btn btn-sm btn-outline" aria-current="page">Horarios</a>
        <span class="navbar-user">
            <?= $nombreUsuario ?> &middot; <span class="badge badge-admin"><?= ucfirst($rolUsuario) ?></span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">
    <div class="card card-horarios">
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

            <!-- Barra de acción contextual que aparece al hacer clic en cualquier fila -->
            <div id="selection-action-bar" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Seleccionado</span>
                    <span id="selection-summary">Franja horaria seleccionada</span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-edit-selected" class="btn-action-edit">
                        ✏️ Editar Horario
                    </button>
                    <button type="button" id="btn-delete-selected" class="btn-action-delete">
                        🗑️ Eliminar Horario
                    </button>
                    <button type="button" id="btn-cancel-selected" class="btn-action-cancel">
                        ✕ Deseleccionar
                    </button>
                </div>
            </div>

            <!-- Formulario oculto para enviar eliminación vía POST seguro CSRF -->
            <form id="form-eliminar-seleccion" method="POST" action="/horarios/eliminar" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="id_horario" id="input-delete-id" value="">
            </form>

            <!-- Vista Tabla Centrada y Justificada -->
            <div class="table-container-center">
                <table class="data-table-horarios" id="tabla-horarios">
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
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="vet-name"><?= $e($h['veterinario_nombre']) ?></span>
                                        <?php if (!empty($h['especialidad'])): ?>
                                            <span class="vet-spec"><?= $e($h['especialidad']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-dia badge-dia-<?= (int)$h['dia_semana'] ?>">
                                        <?= mb_strtoupper($e($diaNombre), 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="hora-text"><?= $e($inicio) ?></span>
                                </td>
                                <td>
                                    <span class="hora-text"><?= $e($fin) ?></span>
                                </td>
                                <td>
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
