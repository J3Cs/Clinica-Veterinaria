<?php
/**
 * Vista de Inicio de Sesión — T-013, UI-01, SEC-01
 *
 * @var string $csrfToken
 * @var string|null $flashError
 * @var string|null $flashSuccess
 * @var string $oldIdentifier
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Acceso al sistema de gestión de la Clínica Veterinaria">
    <title>Iniciar Sesión — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="login-page">

<main class="login-wrapper" role="main">
    <section class="login-card" aria-labelledby="login-title">

        <header class="login-header">
            <div class="login-logo" aria-hidden="true">🐾</div>
            <h1 id="login-title">Clínica Veterinaria</h1>
            <p class="login-subtitle">Sistema de Gestión Clínica — Acceso Autorizado</p>
        </header>

        <!-- Contenedor accesible para notificaciones y alertas -->
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

            <!-- Alerta dinámica gestionada por JavaScript -->
            <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
                <span aria-hidden="true">ℹ️</span>
                <span id="js-error-text"></span>
            </div>
        </div>

        <form id="form-login" method="POST" action="/login" novalidate autocomplete="on">
            <!-- Token CSRF obligatorio (SEC-01) -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="identifier">Usuario o Correo Electrónico</label>
                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    class="form-control"
                    placeholder="Ingresa tu usuario o correo"
                    value="<?= htmlspecialchars($oldIdentifier ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="username"
                    aria-required="true"
                    aria-describedby="identifier-desc"
                >
                <small id="identifier-desc" class="form-hint">Ingresa tu nombre de usuario o correo institucional.</small>
            </div>

            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    class="form-control"
                    placeholder="Ingresa tu contraseña"
                    required
                    tabindex="2"
                    autocomplete="current-password"
                    aria-required="true"
                    aria-describedby="contrasena-desc"
                >
                <small id="contrasena-desc" class="form-hint">Ingresa tu contraseña.</small>
            </div>

            <button type="submit" id="btn-submit" class="btn btn-primary btn-block" tabindex="3">
                Iniciar Sesión
            </button>
        </form>

        <footer class="login-footer">
            <p class="login-disclaimer">
                Acceso restringido a personal veterinario y administrativo autorizado.
            </p>
        </footer>

    </section>
</main>

<script src="/js/main.js"></script>
</body>
</html>
