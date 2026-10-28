<?php
/**
 * Formulario de Alta / Edición de Servicio — US-10 (T-069), UI-01, ACC-01, SEC-01
 *
 * @var string                   $csrfToken
 * @var string|null              $flashError
 * @var array<string,string>     $fieldErrors
 * @var array<string,string>     $oldData
 * @var bool                     $modoEdicion
 * @var array<string,mixed>|null $servicio
 * @var string                   $nombreUsuario
 * @var string                   $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$valNombre = $oldData['nombre_servicio']       ?? ($servicio['nombre_servicio']       ?? '');
$valDesc   = $oldData['descripcion']           ?? ($servicio['descripcion']           ?? '');
$valDur    = $oldData['duracion_estimada_min'] ?? ($servicio['duracion_estimada_min'] ?? '');

$tituloPagina = $modoEdicion ? 'Editar Servicio' : 'Registrar Nuevo Servicio';
$subtitulo    = $modoEdicion
    ? 'Modifica los datos del servicio veterinario seleccionado.'
    : 'Define el nombre, la descripción y la duración estimada del servicio para el agendamiento de citas.';
$formAction   = $modoEdicion ? '/servicios/editar' : '/servicios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $e($tituloPagina) ?> — Catálogo de Servicios">
    <title><?= $e($tituloPagina) ?> — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/servicios.css">
</head>
<body class="dashboard-page">

<?php $navActivo = 'servicios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card">
        <div class="card-header-actions">
            <div>
                <h2><?= $e($tituloPagina) ?></h2>
                <p class="text-muted"><?= $e($subtitulo) ?></p>
            </div>
            <div>
                <a href="/servicios" class="btn btn-sm btn-outline-dark">&larr; Volver al catálogo</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error" role="alert" id="server-error-msg">
                    <span aria-hidden="true">⚠️</span>
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>

            <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
                <span aria-hidden="true">ℹ️</span>
                <span id="js-error-text"></span>
            </div>
        </div>

        <form id="form-servicio" method="POST" action="<?= $e($formAction) ?>" novalidate autocomplete="off">
            <!-- Token CSRF obligatorio (SEC-01) -->
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

            <?php if ($modoEdicion && $servicio !== null): ?>
                <input type="hidden" name="id_servicio" value="<?= (int) $servicio['id_servicio'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <!-- Nombre del Servicio -->
                <div class="form-group">
                    <label for="nombre_servicio">Nombre del Servicio <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="nombre_servicio"
                        name="nombre_servicio"
                        class="form-control <?= isset($fieldErrors['nombre_servicio']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. Consulta General, Vacunación, Limpieza Dental..."
                        value="<?= $e($valNombre) ?>"
                        required
                        minlength="3"
                        maxlength="100"
                        autofocus
                        aria-required="true"
                        aria-describedby="nombre-hint <?= isset($fieldErrors['nombre_servicio']) ? 'nombre-error' : '' ?>"
                    >
                    <?php if (isset($fieldErrors['nombre_servicio'])): ?>
                        <span id="nombre-error" class="invalid-feedback"><?= $e($fieldErrors['nombre_servicio']) ?></span>
                    <?php else: ?>
                        <small id="nombre-hint" class="form-hint">Nombre único identificador del servicio (3 a 100 caracteres).</small>
                    <?php endif; ?>
                </div>

                <!-- Duración Estimada (minutos) -->
                <div class="form-group">
                    <label for="duracion_estimada_min">Duración Estimada (minutos) <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        id="duracion_estimada_min"
                        name="duracion_estimada_min"
                        class="form-control <?= isset($fieldErrors['duracion_estimada_min']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. 30"
                        value="<?= $e($valDur) ?>"
                        required
                        min="1"
                        step="1"
                        inputmode="numeric"
                        aria-required="true"
                        aria-describedby="duracion-hint <?= isset($fieldErrors['duracion_estimada_min']) ? 'duracion-error' : '' ?>"
                    >
                    <?php if (isset($fieldErrors['duracion_estimada_min'])): ?>
                        <span id="duracion-error" class="invalid-feedback"><?= $e($fieldErrors['duracion_estimada_min']) ?></span>
                    <?php else: ?>
                        <small id="duracion-hint" class="form-hint">Tiempo estimado en minutos enteros positivos (ej. 15, 30, 45, 60).</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Descripción (opcional) -->
            <div class="form-group">
                <label for="descripcion">Descripción del Servicio <span class="text-muted">(opcional)</span></label>
                <textarea
                    id="descripcion"
                    name="descripcion"
                    class="form-control <?= isset($fieldErrors['descripcion']) ? 'is-invalid' : '' ?>"
                    rows="3"
                    maxlength="500"
                    placeholder="Detalle breve sobre qué incluye el servicio o indicaciones generales..."
                    aria-describedby="desc-hint <?= isset($fieldErrors['descripcion']) ? 'desc-error' : '' ?>"
                ><?= $e($valDesc) ?></textarea>
                <?php if (isset($fieldErrors['descripcion'])): ?>
                    <span id="desc-error" class="invalid-feedback"><?= $e($fieldErrors['descripcion']) ?></span>
                <?php else: ?>
                    <small id="desc-hint" class="form-hint">Máximo 500 caracteres.</small>
                <?php endif; ?>
            </div>

            <div class="form-actions mt-2">
                <button type="submit" id="btn-submit-servicio" class="btn btn-primary">
                    <?= $modoEdicion ? 'Guardar Cambios' : 'Guardar Servicio' ?>
                </button>
                <a href="/servicios" class="btn btn-outline-dark">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<script src="/assets/js/servicios.js"></script>
</body>
</html>
