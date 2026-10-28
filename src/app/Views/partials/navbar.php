<?php
/**
 * Menú principal según el rol de la sesión — ACC-01, UI-01, SEC-01
 * Fragmento compartido por todas las pantallas privadas: lee el rol y el nombre de
 * la sesión y muestra solo las opciones de ese rol (ver App\Config\Navegacion).
 *
 * @var string $navActivo  Sección actual, para resaltarla (opcional): dashboard, agenda,
 *                         propietarios, mascotas, citas, servicios, horarios.
 */
$navE      = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$navRol    = (string) ($_SESSION['rol'] ?? '');
$navNombre = (string) ($_SESSION['nombre'] ?? '');
$navActivo = $navActivo ?? '';
?>
<nav class="navbar" aria-label="Menú principal">
    <div class="navbar-brand">🐾 Clínica Veterinaria</div>
    <div class="navbar-info">
        <?php foreach (\App\Config\Navegacion::enlaces($navRol) as $navEnlace): ?>
            <a href="<?= $navE($navEnlace['url']) ?>" class="btn btn-sm btn-outline"<?= $navEnlace['id'] === $navActivo ? ' aria-current="page"' : '' ?>><?= $navE($navEnlace['etiqueta']) ?></a>
        <?php endforeach; ?>
        <span class="navbar-user">
            <?= $navE($navNombre) ?> &middot; <span class="badge badge-<?= $navE(\App\Config\Navegacion::claseInsignia($navRol)) ?>"><?= $navE(\App\Config\Navegacion::etiquetaRol($navRol)) ?></span>
        </span>
        <a href="/logout" class="btn btn-sm btn-outline">Cerrar sesión</a>
    </div>
</nav>
