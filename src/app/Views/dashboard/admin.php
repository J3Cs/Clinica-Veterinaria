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

<?php $navActivo = 'dashboard'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card">
        <h2>Panel de Administración</h2>
        <p>Bienvenido, <strong><?= $nombre ?></strong> (<?= $correo ?>).</p>
        <p>Has iniciado sesión con el rol de <strong>Administrador</strong>.</p>
        <div class="alert alert-info mt-1">
            Módulo administrativo: Gestión de usuarios, configuración clínica y auditoría de accesos.
        </div>

        <div class="mt-2">
            <h3>Módulos de Gestión</h3>
            <div class="form-actions mt-1">
                <a href="/propietarios" class="btn btn-primary">Directorio de Propietarios</a>
                <a href="/propietarios/crear" class="btn btn-outline-dark">+ Registrar Nuevo Propietario</a>
                <a href="/mascotas" class="btn btn-primary">Expedientes de Mascotas</a>
                <a href="/mascotas/crear" class="btn btn-outline-dark">+ Registrar Nueva Mascota</a>
                <a href="/citas" class="btn btn-primary">Agendar Cita</a>
            </div>
        </div>
    </div>
</main>

</body>
</html>

