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

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <a href="/admin/dashboard" class="btn btn-sm btn-outline">Dashboard</a>
        <a href="/propietarios" class="btn btn-sm btn-outline">Propietarios</a>
        <a href="/mascotas" class="btn btn-sm btn-outline">Mascotas</a>
        <?php if (($rolUsuario ?? '') === 'administrador'): ?>
        <a href="/citas" class="btn btn-sm btn-outline">Citas</a>
        <a href="/servicios" class="btn btn-sm btn-outline">Servicios</a>
        <?php endif; ?>
        <span class="navbar-user">
            <?= $nombreUsuario ?> &middot; <span class="badge badge-<?= $rolUsuario === 'administrador' ? 'admin' : 'vet' ?>"><?= ucfirst($rolUsuario) ?></span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">

    <?php if (!empty($propietarios)): ?>
    <!-- ── Componente Buscador Reutilizable ────────────────────────── -->
    <?php
    $searchId         = 'propietario-search';
    $placeholder      = 'Buscar por nombre, DUI, teléfono o correo...';
    $ariaLabel        = 'Buscar propietarios';
    $showLiveDropdown = true;
    $showCount        = true;
    require __DIR__ . '/../components/search_bar.php';
    ?>
    <?php endif; ?>

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
                <table class="table" id="propietarios-table">
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
                    <tbody id="propietarios-table-body">
                        <?php foreach ($propietarios as $p): ?>
                            <tr class="propietario-row">
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
                        <!-- Fila que aparece cuando el buscador no encuentra resultados -->
                        <tr id="propietarios-no-matches-row" style="display:none;">
                            <td colspan="6">
                                <div class="search-no-results" aria-live="polite">
                                    <span style="font-size:1.8rem; display:block; margin-bottom:.5rem;">🔍</span>
                                    Ningún propietario coincide con tu búsqueda.
                                    <br><small>Intenta con el nombre, DUI, teléfono o correo.</small>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/js/search-component.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    initTableSearch({
        searchId: 'propietario-search',
        tableBodyId: 'propietarios-table-body',
        rowSelector: 'tr.propietario-row',
        noMatchesRowId: 'propietarios-no-matches-row',
        entityNameSingular: 'propietario',
        entityNamePlural: 'propietarios'
    });
});
</script>
</body>
</html>
