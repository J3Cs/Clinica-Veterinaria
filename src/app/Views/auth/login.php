<?php
/** @var string $csrfToken */
/** @var string|null $flashError */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clínica Veterinaria — Sistema de gestión clínica">
    <title>Iniciar sesión — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="login-page">

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-header">
            <div class="login-logo" aria-hidden="true">🐾</div>
            <h1>Clínica Veterinaria</h1>
            <p class="login-subtitle">Acceso exclusivo para personal autorizado</p>
        </div>

        <?php if ($flashError): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    required
                    autocomplete="username"
                    placeholder="Ingresa tu usuario"
                >
            </div>

            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    required
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña"
                >
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Ingresar
            </button>
        </form>

    </div>
</div>

</body>
</html>
