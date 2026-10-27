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
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/horarios.css">
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
    <div class="card">
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
            <div class="alert alert-danger" role="alert"><?= $e($flashError) ?></div>
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
                    <a href="/horarios" class="btn btn-sm btn-outline">Limpiar filtro</a>
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

            <!-- Vista Tabla Semanal de Franjas -->
            <div class="table-responsive mt-2">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Veterinario</th>
                            <th>Día</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Duración Franja</th>
                            <th>Acciones</th>
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
                                $durTxt = ($horas > 0 ? $horas . ' h ' : '') . ($mins > 0 ? $mins . ' min' : '');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $e($h['veterinario_nombre']) ?></strong>
                                    <?php if (!empty($h['especialidad'])): ?>
                                        <div class="text-muted small"><?= $e($h['especialidad']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-dia badge-dia-<?= (int)$h['dia_semana'] ?>">
                                        <?= $e($diasNombres[(int)$h['dia_semana']] ?? 'Día ' . $h['dia_semana']) ?>
                                    </span>
                                </td>
                                <td><span class="hora-tag"><?= $e($inicio) ?></span></td>
                                <td><span class="hora-tag"><?= $e($fin) ?></span></td>
                                <td><span class="text-muted"><?= $e($durTxt) ?></span></td>
                                <td class="actions-cell">
                                    <a href="/horarios/editar?id=<?= (int)$h['id_horario'] ?>" class="btn btn-sm btn-outline">Editar</a>
                                    <form method="POST" action="/horarios/eliminar" class="form-inline form-eliminar-horario" onsubmit="return confirm('¿Está seguro de eliminar esta franja horaria? El sistema verificará que no afecte citas programadas.');">
                                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                        <input type="hidden" name="id_horario" value="<?= (int)$h['id_horario'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>
</main>

<script src="/assets/js/horarios.js"></script>
</body>
</html>
