<?php
/** @var string $nombre */
/** @var string $rol */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panel principal — Clínica Veterinaria">
    <title>Dashboard — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <span class="navbar-user">
            <?= $nombre ?> &middot; <span class="badge badge-<?= $rol === 'administrador' ? 'admin' : 'vet' ?>"><?= ucfirst($rol) ?></span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">
    <div class="card">
        <h2>Bienvenido, <?= $nombre ?></h2>
        <p>Has iniciado sesión como <strong><?= ucfirst($rol) ?></strong>.</p>
        <p class="text-muted">Los módulos del sistema estarán disponibles en los próximos incrementos.</p>
    </div>
</main>

</body>
</html>
