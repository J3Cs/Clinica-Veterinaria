<?php
/**
 * Panel del Veterinario — ACC-01, ARQ-01
 * @var string $nombre
 * @var string $rol
 * @var string $correo
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Agenda y panel de atención veterinaria">
    <title>Agenda y Atención Veterinaria — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="dashboard-page">

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <span class="navbar-user">
            <?= $nombre ?> &middot; <span class="badge badge-vet">Veterinario</span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">
    <div class="card">
        <h2>Panel de Atención Médica Veterinaria</h2>
        <p>Bienvenido, Dr(a). <strong><?= $nombre ?></strong> (<?= $correo ?>).</p>
        <p>Has iniciado sesión con el rol de <strong>Veterinario</strong>.</p>
        <div class="alert alert-info mt-1">
            Módulo de atención: Agenda de citas programadas, consultas clínicas e historial médico de mascotas.
        </div>
    </div>
</main>

</body>
</html>

