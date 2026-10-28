<?php
/**
 * Formulario de Alta / Edición de Servicio — US-10 (T-069), UI-01, ACC-01, SEC-01
 * Homologado con el diseño de Horarios, Propietarios, Mascotas y Veterinarios.
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
$activo    = (int) ($servicio['activo'] ?? 1) === 1;

$tituloPagina = $modoEdicion ? 'Editar Servicio Clínico' : 'Registrar Nuevo Servicio';
$subtitulo    = $modoEdicion
    ? 'Actualiza el nombre, la descripción o la duración estimada del servicio veterinario seleccionado.'
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
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/servicios.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'servicios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>
                    <?= $e($tituloPagina) ?>
                    <?php if ($modoEdicion && $servicio !== null): ?>
                        <span class="badge <?= $activo ? 'badge-vet' : 'badge-inactivo' ?>">
                            <?= $activo ? 'ACTIVO' : 'INACTIVO' ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <p class="text-muted"><?= $e($subtitulo) ?></p>
            </div>
            <div>
                <a href="/servicios" class="btn btn-sm btn-outline-dark">&larr; Volver al catálogo</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error mt-1" role="alert" id="server-error-msg">
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>

            <div id="js-error-container" class="alert alert-warning mt-1" role="alert" style="display: none;">
                <span id="js-error-text"></span>
            </div>
        </div>

        <?php if ($modoEdicion && $servicio !== null): ?>
            <div class="detail-grid mt-1">
                <div class="detail-stat-card">
                    <div class="detail-stat-label">Identificador</div>
                    <div class="detail-stat-value">#<?= (int) $servicio['id_servicio'] ?></div>
                </div>
                <div class="detail-stat-card">
                    <div class="detail-stat-label">Duración Actual</div>
                    <div class="detail-stat-value"><?= (int) $servicio['duracion_estimada_min'] ?> <small class="text-muted">min</small></div>
                </div>
                <div class="detail-stat-card">
                    <div class="detail-stat-label">Disponibilidad en Citas</div>
                    <div class="detail-stat-value" style="font-size: 1.05rem;">
                        <?= $activo ? 'Habilitado en agenda' : 'Oculto (Inactivo)' ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form id="form-servicio" method="POST" action="<?= $e($formAction) ?>" novalidate autocomplete="off">
            <!-- Token CSRF obligatorio (SEC-01) -->
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

            <?php if ($modoEdicion && $servicio !== null): ?>
                <input type="hidden" name="id_servicio" value="<?= (int) $servicio['id_servicio'] ?>">
            <?php endif; ?>

            <h3 class="clinical-section-title" style="margin-top: 0.5rem;">Datos del Servicio Clínico</h3>
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

        <?php if ($modoEdicion && $servicio !== null): ?>
            <div class="baja-policy-card <?= $activo ? 'baja-logica-mode' : '' ?>">
                <div class="baja-policy-header">
                    <div>
                        <?php if ($activo): ?>
                            <h4>Desactivar Servicio del Catálogo</h4>
                            <p class="text-muted">
                                Al desactivar este servicio dejará de ofrecerse al agendar nuevas citas, pero se conservarán todas las citas y consultas históricas asociadas (`BAJA-01`).
                            </p>
                        <?php else: ?>
                            <h4>Servicio Inactivo</h4>
                            <p class="text-muted">
                                Este servicio se encuentra inactivo y no aparece como opción al agendar citas. Puedes reactivarlo en cualquier momento.
                            </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <form method="POST"
                              action="/servicios/estado"
                              class="form-estado-servicio"
                              data-nombre="<?= $e($servicio['nombre_servicio']) ?>"
                              data-accion="<?= $activo ? 'desactivar' : 'activar' ?>"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_servicio" value="<?= (int) $servicio['id_servicio'] ?>">
                            <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                            <?php if ($activo): ?>
                                <button type="submit" class="btn btn-warning-outline">Desactivar Servicio</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary">Reactivar Servicio</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/servicios.js?v=<?= time() ?>"></script>
</body>
</html>
