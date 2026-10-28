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

<?php $navActivo = ''; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card">
        <h2>Bienvenido, <?= $nombre ?></h2>
        <p>Has iniciado sesión como <strong><?= ucfirst($rol) ?></strong>.</p>
        <p class="text-muted">Los módulos del sistema estarán disponibles en los próximos incrementos.</p>
    </div>
</main>

</body>
</html>
