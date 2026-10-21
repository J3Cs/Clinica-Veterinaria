<?php
/**
 * Panel del Administrador — ACC-01, ARQ-01
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
    <meta name="description" content="Panel de administración de la Clínica Veterinaria">
    <title>Panel de Administración — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="dashboard-page">

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <span class="navbar-user">
            <?= $nombre ?> &middot; <span class="badge badge-admin">Administrador</span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">
    <div class="card">
        <h2>Panel de Administración</h2>
        <p>Bienvenido, <strong><?= $nombre ?></strong> (<?= $correo ?>).</p>
        <p>Has iniciado sesión con el rol de <strong>Administrador</strong>.</p>
        <div class="alert alert-info mt-1">
            Módulo administrativo: Gestión de usuarios, configuración clínica y auditoría de accesos.
        </div>
    </div>
</main>

</body>
</html>

