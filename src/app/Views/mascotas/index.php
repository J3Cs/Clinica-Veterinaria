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

<?php $navActivo = 'mascotas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">

    <?php if (!empty($mascotas)): ?>
    <!-- ── Buscador Dinámico — ENCIMA de la card principal ─────────── -->
    <div class="search-card">
        <div class="search-form-group">
            <div class="search-input-wrapper">
                <span class="search-icon-badge" aria-hidden="true">🔍</span>
                <input
                    type="search"
                    id="pet-search-input"
                    class="search-input-field"
                    placeholder="Buscar por nombre de mascota o propietario..."
                    autocomplete="off"
                    aria-label="Buscar mascotas por nombre o propietario"
                    aria-expanded="false"
                    aria-haspopup="listbox"
                    aria-controls="pet-search-dropdown"
                >
                <button
                    type="button"
                    id="pet-search-clear"
                    class="search-clear-btn"
                    title="Limpiar búsqueda"
                    aria-label="Limpiar búsqueda"
                >✕</button>
            </div>
            <!-- Dropdown de sugerencias en vivo -->
            <div
                id="pet-search-dropdown"
                class="search-live-dropdown"
                role="listbox"
                aria-label="Sugerencias de búsqueda"
            ></div>
        </div>
        <!-- Contador de resultados -->
        <div class="search-result-count" id="pet-search-count" aria-live="polite"></div>
    </div>
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
                    <a href="/mascotas/crear" class="btn btn-primary">+ Registrar Mascota</a>
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
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">🐾</div>
                <h3>No hay mascotas registradas</h3>
                <p>Actualmente no se encuentra ninguna mascota registrada en el sistema clínico.</p>
                <?php if ($rolUsuario === 'administrador'): ?>
                    <a href="/mascotas/crear" class="btn btn-primary">+ Registrar la Primera Mascota</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive mt-2">
                <table class="table" id="pets-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Especie / Raza</th>
                            <th>Sexo</th>
                            <th>Propietario Responsable</th>
                            <th>DUI Propietario</th>
                            <th>Fecha Nacimiento</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="pets-table-body">
                        <?php foreach ($mascotas as $m): ?>
                            <tr class="pet-row"
                                data-nombre="<?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                data-propietario="<?= htmlspecialchars($m['propietario_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-dui="<?= htmlspecialchars($m['propietario_dui'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-especie="<?= htmlspecialchars($m['especie'], ENT_QUOTES, 'UTF-8') ?>"
                                data-raza="<?= htmlspecialchars($m['raza'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <td>
                                    <strong>🐾 <?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($m['caracteristicas_relevantes'])): ?>
                                        <br><small class="text-muted" title="<?= htmlspecialchars($m['caracteristicas_relevantes'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(mb_strimwidth($m['caracteristicas_relevantes'], 0, 45, '...'), ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($m['especie'], ENT_QUOTES, 'UTF-8') ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($m['raza'] ?? 'No especificada', ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?= $m['sexo'] === 'Macho' ? '#2980b9' : '#8e44ad' ?>; color:#fff;">
                                        <?= htmlspecialchars($m['sexo'], ENT_QUOTES, 'UTF-8') ?>
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
