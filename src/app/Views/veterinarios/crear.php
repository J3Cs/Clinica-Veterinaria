<?php
/**
 * Formulario de Alta de Médico Veterinario y Cuenta de Usuario Inicial
 * US-09 (T-063, T-064) — Cumple UI-01, ACC-01, SEC-01
 *
 * @var string               $csrfToken
 * @var string|null          $flashError
 * @var string|null          $flashSuccess
 * @var array<string,string> $fieldErrors
 * @var array<string,string> $oldData
 * @var string               $nombreUsuario
 * @var string               $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Veterinario — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'veterinarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>Registrar Nuevo Médico Veterinario</h2>
                <p class="text-muted">Crea en un solo paso la ficha profesional del veterinario y su cuenta inicial de acceso al sistema.</p>
            </div>
            <div>
                <a href="/veterinarios" class="btn btn-sm btn-outline-dark">&larr; Volver al directorio</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error" role="alert" id="server-error-msg">
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success" role="status" id="server-success-msg">
                    <span><?= $e($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
                <span id="js-error-text"></span>
            </div>
        </div>

        <form id="form-veterinario" method="POST" action="/veterinarios" data-modo="crear" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

            <h3 class="clinical-section-title" style="margin-top: 0.5rem;">1. Datos Profesionales del Veterinario</h3>
            <div class="form-grid">
                <!-- Nombre Completo -->
                <div class="form-group">
                    <label for="nombre">Nombre Completo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        class="form-control <?= isset($fieldErrors['nombre']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. Dra. Laura Beatriz Mendoza"
                        maxlength="100"
                        value="<?= $e($oldData['nombre'] ?? '') ?>"
                        required
                        autofocus
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['nombre'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['nombre']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Nombre completo del médico veterinario (3 a 100 caracteres).</small>
                    <?php endif; ?>
                </div>

                <!-- Especialidad -->
                <div class="form-group">
                    <label for="especialidad">Especialidad Médica <span class="text-muted">(opcional)</span></label>
                    <input
                        type="text"
                        id="especialidad"
                        name="especialidad"
                        class="form-control <?= isset($fieldErrors['especialidad']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. Medicina General, Cirugía, Dermatología..."
                        maxlength="100"
                        value="<?= $e($oldData['especialidad'] ?? '') ?>"
                    >
                    <?php if (isset($fieldErrors['especialidad'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['especialidad']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Área clínica principal del profesional.</small>
                    <?php endif; ?>
                </div>

                <!-- Licencia Médica -->
                <div class="form-group">
                    <label for="licencia_medica">Licencia Médica / Junta de Vigilancia <span class="text-muted">(opcional)</span></label>
                    <input
                        type="text"
                        id="licencia_medica"
                        name="licencia_medica"
                        class="form-control <?= isset($fieldErrors['licencia_medica']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. JVPMV-2026"
                        maxlength="50"
                        value="<?= $e($oldData['licencia_medica'] ?? '') ?>"
                    >
                    <?php if (isset($fieldErrors['licencia_medica'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['licencia_medica']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Número de colegiado o licencia profesional única.</small>
                    <?php endif; ?>
                </div>

                <!-- Teléfono -->
                <div class="form-group">
                    <label for="telefono">Teléfono de Contacto <span class="text-muted">(opcional)</span></label>
                    <input
                        type="tel"
                        id="telefono"
                        name="telefono"
                        class="form-control <?= isset($fieldErrors['telefono']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. 7890-1234"
                        maxlength="20"
                        value="<?= $e($oldData['telefono'] ?? '') ?>"
                    >
                    <?php if (isset($fieldErrors['telefono'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['telefono']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Teléfono directo o de emergencias clínicas.</small>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="clinical-section-title">2. Cuenta de Usuario Inicial (Acceso al Sistema)</h3>
            <p class="text-muted" style="margin-bottom: 1rem;">
                La cuenta se creará activa con el rol <strong>Veterinario</strong> en la misma transacción.
            </p>

            <div class="form-grid">
                <!-- Usuario -->
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        class="form-control <?= isset($fieldErrors['usuario']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. dra.mendoza"
                        maxlength="50"
                        value="<?= $e($oldData['usuario'] ?? '') ?>"
                        required
                        autocomplete="off"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['usuario'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['usuario']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Identificador único para iniciar sesión (letras, números, puntos o guiones).</small>
                    <?php endif; ?>
                </div>

                <!-- Correo -->
                <div class="form-group">
                    <label for="correo">Correo Electrónico Institucional <span class="text-danger">*</span></label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        class="form-control <?= isset($fieldErrors['correo']) ? 'is-invalid' : '' ?>"
                        placeholder="veterinario@clinica.sv"
                        maxlength="150"
                        value="<?= $e($oldData['correo'] ?? '') ?>"
                        required
                        autocomplete="off"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['correo'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['correo']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Correo único asociado a la cuenta del médico.</small>
                    <?php endif; ?>
                </div>

                <!-- Contraseña Inicial (obligatoria solo al crear — T-063) -->
                <div class="form-group">
                    <label for="contrasena">Contraseña Inicial <span class="text-danger">*</span></label>
                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        class="form-control <?= isset($fieldErrors['contrasena']) ? 'is-invalid' : '' ?>"
                        placeholder="Mínimo 8 caracteres"
                        minlength="8"
                        maxlength="128"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['contrasena'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['contrasena']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Mínimo 8 caracteres. Se almacena cifrada con Bcrypt.</small>
                    <?php endif; ?>
                </div>

                <!-- Confirmar Contraseña -->
                <div class="form-group">
                    <label for="confirmar_contrasena">Confirmar Contraseña Inicial <span class="text-danger">*</span></label>
                    <input
                        type="password"
                        id="confirmar_contrasena"
                        name="confirmar_contrasena"
                        class="form-control <?= isset($fieldErrors['confirmar_contrasena']) ? 'is-invalid' : '' ?>"
                        placeholder="Repite la contraseña inicial"
                        minlength="8"
                        maxlength="128"
                        required
                        autocomplete="new-password"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['confirmar_contrasena'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['confirmar_contrasena']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Debe coincidir exactamente con la contraseña inicial.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions mt-2">
                <button type="submit" id="btn-submit" class="btn btn-primary">
                    Registrar Veterinario y Crear Cuenta
                </button>
                <a href="/veterinarios" class="btn btn-outline-dark">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<script src="/assets/js/veterinarios.js?v=<?= time() ?>"></script>
</body>
</html>
