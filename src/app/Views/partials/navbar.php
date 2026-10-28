<?php
/**
 * Menú principal según el rol de la sesión — ACC-01, UI-01, SEC-01
 * Fragmento compartido por todas las pantallas privadas: lee el rol y el nombre de
 * la sesión y muestra solo las opciones de ese rol (ver App\Config\Navegacion).
 * Incluye soporte responsivo con botón de menú hamburguesa para dispositivos móviles.
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
    <div class="navbar-top">
        <div class="navbar-brand">🐾 Clínica Veterinaria</div>
        <button type="button"
                class="navbar-toggle"
                id="navbar-toggle"
                aria-expanded="false"
                aria-controls="navbar-menu"
                aria-label="Abrir o cerrar menú de navegación"
                onclick="var nav=this.closest('.navbar'),open=nav.classList.toggle('is-open');document.getElementById('navbar-menu').classList.toggle('is-open',open);this.setAttribute('aria-expanded',open?'true':'false');">
            <span class="hamburger-box" aria-hidden="true">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </span>
        </button>
    </div>
    <div class="navbar-info" id="navbar-menu">
        <div class="navbar-links">
            <?php foreach (\App\Config\Navegacion::enlaces($navRol) as $navEnlace): ?>
                <a href="<?= $navE($navEnlace['url']) ?>" class="btn btn-sm btn-outline nav-link-item"<?= $navEnlace['id'] === $navActivo ? ' aria-current="page"' : '' ?>><?= $navE($navEnlace['etiqueta']) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="navbar-session">
            <span class="navbar-user">
                <span class="navbar-user-name"><?= $navE($navNombre) ?></span> &middot; <span class="badge badge-<?= $navE(\App\Config\Navegacion::claseInsignia($navRol)) ?>"><?= $navE(\App\Config\Navegacion::etiquetaRol($navRol)) ?></span>
            </span>
            <a href="/logout" class="btn btn-sm btn-outline nav-logout-btn">Cerrar sesión</a>
        </div>
    </div>
</nav>
