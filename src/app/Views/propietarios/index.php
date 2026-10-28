<?php
/**
 * Listado de Propietarios — T-019, UI-01
 *
 * @var array<array<string,mixed>> $propietarios
 * @var string|null $flashSuccess
 * @var string $nombreUsuario
 * @var string $rolUsuario
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Propietarios — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="dashboard-page">

<?php $navActivo = 'propietarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card">
        <div class="card-header-actions">
            <div>
                <h2>Directorio de Propietarios</h2>
                <p class="text-muted">Lista de propietarios registrados para asociación de mascotas y consultas.</p>
            </div>
            <?php if ($rolUsuario === 'administrador'): ?>
                <div>
                    <a href="/propietarios/crear" class="btn btn-primary">+ Registrar Propietario</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success mt-1" role="status">
                <span aria-hidden="true">✅</span>
                <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($propietarios)): ?>
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">📋</div>
                <h3>No hay propietarios disponibles</h3>
                <p>Actualmente no se encuentra ningún propietario registrado en el sistema.</p>
                <?php if ($rolUsuario === 'administrador'): ?>
                    <a href="/propietarios/crear" class="btn btn-primary">+ Registrar el Primer Propietario</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive mt-2">
                <table class="table">
                    <thead>
                        <tr>
                            <th>DUI</th>
                            <th>Nombre Completo</th>
                            <th>Teléfono</th>
                            <th>Correo Electrónico</th>
                            <th>Dirección</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($propietarios as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['dui'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars($p['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['correo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['direccion'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge badge-<?= (int)$p['activo'] === 1 ? 'vet' : 'admin' ?>">
                                        <?= (int)$p['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>

