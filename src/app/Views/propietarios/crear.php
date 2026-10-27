<?php
/**
 * Formulario de Alta de Propietario — T-020, UI-01, UNI-01, SEC-01
 *
 * @var string $csrfToken
 * @var string|null $flashError
 * @var string|null $flashSuccess
 * @var array<string,string> $fieldErrors
 * @var array<string,string> $oldData
 * @var string $nombreUsuario
 * @var string $rolUsuario
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro de propietarios de pacientes veterinarios">
    <title>Registrar Propietario — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="dashboard-page">

<!-- Barra de navegación superior -->
<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <a href="/admin/dashboard" class="btn btn-sm btn-outline">Dashboard</a>
        <a href="/propietarios" class="btn btn-sm btn-outline">Propietarios</a>
        <a href="/mascotas" class="btn btn-sm btn-outline">Mascotas</a>
        <a href="/citas" class="btn btn-sm btn-outline">Citas</a>
        <a href="/servicios" class="btn btn-sm btn-outline">Servicios</a>
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
                <h2>Registrar Nuevo Propietario</h2>
                <p class="text-muted">Ingresa los datos generales y de contacto del propietario para asociarle mascotas y citas.</p>
            </div>
            <div>
                <a href="/propietarios" class="btn btn-sm btn-outline-dark">&larr; Volver a la lista</a>
            </div>
        </div>

        <!-- Contenedor accesible para notificaciones -->
        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error" role="alert" id="server-error-msg">
                    <span aria-hidden="true">⚠️</span>
                    <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success" role="status" id="server-success-msg">
                    <span aria-hidden="true">✅</span>
                    <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- Alerta visual interactiva de JavaScript -->
            <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
                <span aria-hidden="true">ℹ️</span>
                <span id="js-error-text"></span>
            </div>
        </div>

        <form id="form-propietario" method="POST" action="/propietarios" novalidate>
            <!-- Token CSRF obligatorio (SEC-01) -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-grid">
                <!-- Nombre Completo -->
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="nombre_completo"
                        name="nombre_completo"
                        class="form-control <?= isset($fieldErrors['nombre_completo']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. María Elena López Rodríguez"
                        value="<?= htmlspecialchars($oldData['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="name"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['nombre_completo'])): ?>
                        <span class="invalid-feedback"><?= htmlspecialchars($fieldErrors['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <small class="form-hint">Nombre y apellidos del titular responsable.</small>
                    <?php endif; ?>
                </div>

                <!-- DUI -->
                <div class="form-group">
                    <label for="dui">DUI (Documento Único de Identidad) <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="dui"
                        name="dui"
                        class="form-control <?= isset($fieldErrors['dui']) ? 'is-invalid' : '' ?>"
                        placeholder="00000000-0"
                        maxlength="10"
                        value="<?= htmlspecialchars($oldData['dui'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                        tabindex="2"
                        inputmode="numeric"
                        autocomplete="off"
                        aria-required="true"
                        aria-describedby="dui-hint"
                    >
                    <?php if (isset($fieldErrors['dui'])): ?>
                        <span class="invalid-feedback" id="dui-hint"><?= htmlspecialchars($fieldErrors['dui'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <small class="form-hint" id="dui-hint">Formato salvadoreño: 8 dígitos, guion y 1 dígito de control (UNI-01).</small>
                    <?php endif; ?>
                </div>

                <!-- Teléfono (opcional) -->
                <div class="form-group">
                    <label for="telefono">Teléfono de Contacto <span class="text-muted">(opcional)</span></label>
                    <input
                        type="tel"
                        id="telefono"
                        name="telefono"
                        class="form-control <?= isset($fieldErrors['telefono']) ? 'is-invalid' : '' ?>"
                        placeholder="Ej. 7890-1234"
                        maxlength="20"
                        value="<?= htmlspecialchars($oldData['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        tabindex="3"
                        autocomplete="tel"
                    >
                    <?php if (isset($fieldErrors['telefono'])): ?>
                        <span class="invalid-feedback"><?= htmlspecialchars($fieldErrors['telefono'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <small class="form-hint">Teléfono móvil o fijo. Puede ser compartido por varios miembros de la familia (UNI-01).</small>
                    <?php endif; ?>
                </div>

                <!-- Correo Electrónico (opcional) -->
                <div class="form-group">
                    <label for="correo">Correo Electrónico <span class="text-muted">(opcional)</span></label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        class="form-control <?= isset($fieldErrors['correo']) ? 'is-invalid' : '' ?>"
                        placeholder="propietario@ejemplo.com"
                        maxlength="150"
                        value="<?= htmlspecialchars($oldData['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        tabindex="4"
                        autocomplete="email"
                    >
                    <?php if (isset($fieldErrors['correo'])): ?>
                        <span class="invalid-feedback"><?= htmlspecialchars($fieldErrors['correo'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <small class="form-hint">Opcional para notificaciones y avisos de citas.</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dirección (opcional) -->
            <div class="form-group">
                <label for="direccion">Dirección de Residencia <span class="text-muted">(opcional)</span></label>
                <textarea
                    id="direccion"
                    name="direccion"
                    class="form-control"
                    rows="3"
                    placeholder="Colonia, calle, pasaje, número de casa, municipio..."
                    tabindex="5"
                ><?= htmlspecialchars($oldData['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <small class="form-hint">Ubicación física para registro en ficha clínica.</small>
            </div>

            <!-- Botones de acción -->
            <div class="form-actions mt-2">
                <button type="submit" id="btn-submit" class="btn btn-primary" tabindex="6">
                    Guardar Propietario
                </button>
                <a href="/propietarios" class="btn btn-outline-dark" tabindex="7">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<script src="/js/propietarios.js"></script>
</body>
</html>

