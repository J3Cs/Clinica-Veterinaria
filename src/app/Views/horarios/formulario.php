<?php
/**
 * Formulario de Creación / Edición de Horarios — US-11 (T-074), UI-01, ACC-01, SEC-01
 *
 * @var array<array<string,mixed>> $veterinarios
 * @var bool                       $modoEdicion
 * @var array<string,mixed>|null   $horario
 * @var string                     $csrfToken
 * @var string|null                $flashError
 * @var array<string,string>       $fieldErrors
 * @var array<string,mixed>        $oldData
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

$selectedVet = $oldData['id_veterinario'] ?? ($horario['id_veterinario'] ?? '');
$selectedDia = $oldData['dia_semana']     ?? ($horario['dia_semana']     ?? 1);
$valInicio   = $oldData['hora_inicio']    ?? (isset($horario['hora_inicio']) ? substr($horario['hora_inicio'], 0, 5) : '08:00');
$valFin      = $oldData['hora_fin']       ?? (isset($horario['hora_fin']) ? substr($horario['hora_fin'], 0, 5) : '12:00');

$actionUrl = $modoEdicion ? '/horarios/editar' : '/horarios';
$titulo    = $modoEdicion ? 'Editar Franja Horaria' : 'Asignar Franja Horaria';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $titulo ?> de atención médica">
    <title><?= $titulo ?> — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/horarios.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'horarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo card-form">
        <div class="card-header-actions">
            <div>
                <h2><?= $titulo ?></h2>
                <p class="text-muted">Configure los días y el rango horario en que el médico veterinario atenderá citas clínicas.</p>
            </div>
            <div>
                <a href="/horarios" class="btn btn-sm btn-outline-dark">&larr; Volver al listado</a>
            </div>
        </div>

        <?php if ($flashError): ?>
            <div class="alert alert-error" role="alert">
                <span aria-hidden="true">⚠️</span>
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($veterinarios) && !$modoEdicion): ?>
            <div class="empty-state">
                <p class="empty-icon">⚠️</p>
                <h3>No hay veterinarios disponibles</h3>
                <p class="text-muted">No se puede asignar un horario porque no existen veterinarios activos en la base de datos.</p>
                <a href="/admin/dashboard" class="btn btn-primary mt-1">Ir al Dashboard</a>
            </div>
        <?php else: ?>

            <form method="POST" action="<?= $actionUrl ?>" id="form-horario" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <?php if ($modoEdicion): ?>
                    <input type="hidden" name="id_horario" value="<?= (int)$horario['id_horario'] ?>">
                <?php endif; ?>

                <div class="form-grid">

                    <!-- Veterinario -->
                    <div class="form-group">
                        <label for="id_veterinario" class="form-label required">Médico Veterinario <span class="text-danger">*</span></label>
                        <?php if ($modoEdicion): ?>
                            <input type="text" class="form-control" value="<?= $e($horario['veterinario_nombre'] . (!empty($horario['especialidad']) ? ' (' . $horario['especialidad'] . ')' : '')) ?>" disabled>
                            <input type="hidden" name="id_veterinario" value="<?= (int)$horario['id_veterinario'] ?>">
                            <small class="form-hint">El médico asociado no puede cambiarse en edición (elimine o cree una nueva franja si corresponde).</small>
                        <?php else: ?>
                            <select name="id_veterinario" id="id_veterinario" class="form-control <?= isset($fieldErrors['id_veterinario']) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Seleccionar veterinario --</option>
                                <?php foreach ($veterinarios as $v): ?>
                                    <option value="<?= (int)$v['id_veterinario'] ?>" <?= ((string)$selectedVet === (string)$v['id_veterinario']) ? 'selected' : '' ?>>
                                        <?= $e($v['nombre']) ?><?= !empty($v['especialidad']) ? ' (' . $e($v['especialidad']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($fieldErrors['id_veterinario'])): ?>
                                <div class="invalid-feedback"><?= $e($fieldErrors['id_veterinario']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Día de la semana -->
                    <div class="form-group">
                        <label for="dia_semana" class="form-label required">Día de la Semana <span class="text-danger">*</span></label>
                        <select name="dia_semana" id="dia_semana" class="form-control <?= isset($fieldErrors['dia_semana']) ? 'is-invalid' : '' ?>" required>
                            <?php foreach ($diasNombres as $num => $nom): ?>
                                <option value="<?= $num ?>" <?= ((int)$selectedDia === $num) ? 'selected' : '' ?>>
                                    <?= $e($nom) ?> (Día <?= $num ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($fieldErrors['dia_semana'])): ?>
                            <div class="invalid-feedback"><?= $e($fieldErrors['dia_semana']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Hora de Inicio -->
                    <div class="form-group">
                        <label for="hora_inicio" class="form-label required">Hora de Inicio <span class="text-danger">*</span></label>
                        <input type="time" name="hora_inicio" id="hora_inicio" class="form-control <?= isset($fieldErrors['hora_inicio']) ? 'is-invalid' : '' ?>" value="<?= $e($valInicio) ?>" required step="300">
                        <?php if (isset($fieldErrors['hora_inicio'])): ?>
                            <div class="invalid-feedback"><?= $e($fieldErrors['hora_inicio']) ?></div>
                        <?php endif; ?>
                        <small class="form-hint">Ejemplo: 08:00 AM</small>
                    </div>

                    <!-- Hora de Fin -->
                    <div class="form-group">
                        <label for="hora_fin" class="form-label required">Hora de Fin <span class="text-danger">*</span></label>
                        <input type="time" name="hora_fin" id="hora_fin" class="form-control <?= isset($fieldErrors['hora_fin']) ? 'is-invalid' : '' ?>" value="<?= $e($valFin) ?>" required step="300">
                        <?php if (isset($fieldErrors['hora_fin'])): ?>
                            <div class="invalid-feedback"><?= $e($fieldErrors['hora_fin']) ?></div>
                        <?php endif; ?>
                        <small class="form-hint">Debe ser posterior a la hora de inicio.</small>
                    </div>

                </div>

                <div id="client-error" class="alert alert-error mt-1" style="display: none;" role="alert"></div>

                <div class="form-actions mt-2">
                    <button type="submit" class="btn btn-primary" id="btn-submit">
                        <?= $modoEdicion ? 'Actualizar Franja' : 'Guardar Franja' ?>
                    </button>
                    <a href="/horarios" class="btn btn-outline-dark">Cancelar y Salir</a>
                </div>

            </form>

        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/horarios.js?v=<?= time() ?>"></script>
</body>
</html>
