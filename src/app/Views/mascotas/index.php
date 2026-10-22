<?php
/**
 * Listado de Mascotas con Buscador Dinámico — US-03, UI-01, TEC-01
 *
 * @var array<array<string,mixed>> $mascotas
 * @var string|null $flashSuccess
 * @var string|null $flashError
 * @var string $nombreUsuario
 * @var string $rolUsuario
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mascotas — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="dashboard-page">

<nav class="navbar">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <a href="/admin/dashboard" class="btn btn-sm btn-outline">Dashboard</a>
        <a href="/propietarios" class="btn btn-sm btn-outline">Propietarios</a>
        <a href="/mascotas" class="btn btn-sm btn-outline">Mascotas</a>
        <span class="navbar-user">
            <?= $nombreUsuario ?> &middot; <span class="badge badge-<?= $rolUsuario === 'administrador' ? 'admin' : 'vet' ?>"><?= ucfirst($rolUsuario) ?></span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>

<main class="container mt-2">

    <?php if (!empty($mascotas)): ?>
    <!-- ── Componente Buscador Reutilizable ────────────────────────── -->
    <?php
    $searchId          = 'pet-search';
    $placeholder       = 'Buscar por nombre de mascota o propietario...';
    $ariaLabel         = 'Buscar mascotas por nombre o propietario';
    $showLiveDropdown  = true;
    $showCount         = true;
    require __DIR__ . '/../components/search_bar.php';
    ?>
    <?php endif; ?>

    <!-- ── Card Principal: Tabla de Expedientes ────────────────────── -->
    <div class="card">
        <div class="card-header-actions">
            <div>
                <h2>Expedientes de Mascotas</h2>
                <p class="text-muted">Registro y control de pacientes veterinarios asociados a sus respectivos propietarios.</p>
            </div>
            <?php if ($rolUsuario === 'administrador'): ?>
                <div>
                    <a href="/mascotas/crear" class="btn btn-primary">+ Registrar Nueva Mascota</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success mt-1" role="status">
                <span aria-hidden="true">✅</span>
                <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error mt-1" role="alert">
                <span aria-hidden="true">⚠️</span>
                <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($mascotas)): ?>
            <!-- Estado vacío (empty state) -->
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">🐾</div>
                <h3>No hay mascotas registradas</h3>
                <p>Actualmente no se encuentra ningún paciente registrado en la clínica veterinaria.</p>
                <?php if ($rolUsuario === 'administrador'): ?>
                    <a href="/mascotas/crear" class="btn btn-primary">+ Registrar la Primera Mascota</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive mt-2">
                <table class="table" id="pets-table" aria-describedby="pet-search-count">
                    <thead>
                        <tr>
                            <th scope="col">Mascota</th>
                            <th scope="col">Especie / Raza</th>
                            <th scope="col">Sexo</th>
                            <th scope="col">Propietario Titular</th>
                            <th scope="col">DUI Propietario</th>
                            <th scope="col">F. Nacimiento</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="pets-table-body">
                        <?php foreach ($mascotas as $m): ?>
                            <tr
                                class="pet-row"
                                data-id="<?= (int)$m['id_mascota'] ?>"
                                data-nombre="<?= htmlspecialchars(mb_strtolower($m['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-propietario="<?= htmlspecialchars(mb_strtolower($m['propietario_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-especie="<?= htmlspecialchars(mb_strtolower($m['especie'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-raza="<?= htmlspecialchars(mb_strtolower($m['raza'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-dui="<?= htmlspecialchars($m['propietario_dui'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <td>
                                    <strong><?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($m['caracteristicas_relevantes'])): ?>
                                        <small class="text-muted d-block" title="<?= htmlspecialchars($m['caracteristicas_relevantes'], ENT_QUOTES, 'UTF-8') ?>">
                                            📝 <?= htmlspecialchars(mb_strimwidth($m['caracteristicas_relevantes'], 0, 35, '...'), ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($m['especie'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($m['raza'])): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($m['raza'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="sexo-tag sexo-<?= mb_strtolower($m['sexo'] ?? '') ?>">
                                        <?= $m['sexo'] === 'Macho' ? '♂ Macho' : '♀ Hembra' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($m['propietario_nombre'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars($m['propietario_dui'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= !empty($m['fecha_nacimiento']) ? htmlspecialchars($m['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">No registrada</span>' ?></td>
                                <td>
                                    <span class="badge badge-<?= (int)($m['activo'] ?? 1) === 1 ? 'vet' : 'admin' ?>">
                                        <?= (int)($m['activo'] ?? 1) === 1 ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Fila que aparece cuando el buscador no encuentra resultados -->
                        <tr id="pets-no-matches-row" style="display:none;">
                            <td colspan="7">
                                <div class="search-no-results" aria-live="polite">
                                    <span style="font-size:1.8rem; display:block; margin-bottom:.5rem;">🔍</span>
                                    Ninguna mascota coincide con tu búsqueda.
                                    <br><small>Intenta con el nombre del propietario, el DUI o la especie.</small>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="/js/mascotas.js"></script>
<script src="/js/mascotas-buscador.js"></script>
</body>
</html>
