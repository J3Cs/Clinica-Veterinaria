<?php
/**
 * Suite de Pruebas Automatizadas: ACC-01 — Menú y funciones según el rol
 * Cumple con TEC-01: PHP nativo con assert(), sin Composer ni dependencias externas.
 *
 * Comprueba la tabla de opciones por rol (App\Config\Navegacion), el menú compartido
 * (Views/partials/navbar.php) y que ninguna pantalla vuelva a traer su propio menú
 * copiado. El acceso real lo siguen imponiendo los controladores; la coincidencia
 * entre ambos se verifica de punta a punta en la matriz manual (ACC-roles-y-menu.md).
 *
 * Ejecución local:
 *   php -d zend.assertions=1 pruebas/test_navegacion.php
 */

declare(strict_types=1);

ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: ACC-01\n";
echo " Modulo: Menu y funciones segun el rol\n";
echo "========================================================\n\n";

$srcBase = is_dir(__DIR__ . '/../src/app') ? __DIR__ . '/../src' : '/var/www/html';

require $srcBase . '/app/Config/Navegacion.php';

use App\Config\Navegacion;

$totalTests = 0;
$passedTests = 0;

function runTest(string $description, callable $testFn): void {
    global $totalTests, $passedTests;
    $totalTests++;
    echo sprintf("[%02d] %s ... ", $totalTests, $description);
    try {
        $testFn();
        $passedTests++;
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "FALLO\n";
        echo "     Error: " . $e->getMessage() . "\n";
        echo "     En: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

/** URLs del menú de un rol, en orden. */
function urls(?string $rol): array {
    return array_column(Navegacion::enlaces($rol), 'url');
}

/** Dibuja el menú compartido para una sesión dada y devuelve el HTML. */
function dibujarMenu(array $sesion, string $activo = ''): string {
    global $srcBase;
    $_SESSION = $sesion;
    $navActivo = $activo;
    ob_start();
    include $srcBase . '/app/Views/partials/navbar.php';
    return (string) ob_get_clean();
}

/** Enlaces (href) que aparecen en un HTML. */
function hrefs(string $html): array {
    preg_match_all('/<a href="([^"]+)"/', $html, $m);
    return $m[1];
}

// Rutas que SOLO puede usar el administrador (ver los requireRole de los controladores).
const SOLO_ADMIN = [
    '/admin/dashboard',
    '/propietarios/crear', '/propietarios/editar',
    '/mascotas/crear', '/mascotas/editar',
    '/veterinarios', '/veterinarios/crear', '/veterinarios/editar',
    '/citas', '/citas/crear', '/citas/cancelar',
    '/servicios', '/servicios/crear', '/servicios/editar',
    '/horarios', '/horarios/crear', '/horarios/editar',
];

// ────────────────────────────────────────────────────────────────── //

runTest('Administrador: ve Dashboard, Propietarios, Mascotas, Veterinarios, Citas, Servicios y Horarios, en ese orden', function () {
    assert(urls('administrador') === ['/admin/dashboard', '/propietarios', '/mascotas', '/veterinarios', '/citas', '/servicios', '/horarios'], json_encode(urls('administrador')));
    assert(array_column(Navegacion::enlaces('administrador'), 'etiqueta') === ['Dashboard', 'Propietarios', 'Mascotas', 'Veterinarios', 'Citas', 'Servicios', 'Horarios'], 'Etiquetas');
});

runTest('Veterinario: ve Mi agenda, Mascotas y Propietarios, en ese orden', function () {
    assert(urls('veterinario') === ['/veterinario/agenda', '/mascotas', '/propietarios'], json_encode(urls('veterinario')));
    assert(array_column(Navegacion::enlaces('veterinario'), 'etiqueta') === ['Mi agenda', 'Mascotas', 'Propietarios'], 'Etiquetas');
});

runTest('Veterinario: ninguna opcion del menu es exclusiva del administrador (no hay enlaces que den 403)', function () {
    foreach (urls('veterinario') as $url) {
        assert(!in_array($url, SOLO_ADMIN, true), "El veterinario no debe ver $url");
    }
});

runTest('Administrador: el menu no ofrece las pantallas clinicas del veterinario', function () {
    foreach (urls('administrador') as $url) {
        assert(!str_starts_with($url, '/veterinario/') && !str_starts_with($url, '/consultas'), "El administrador no debe ver $url");
    }
});

runTest('Rol desconocido, vacio o nulo: sin opciones de menu y el inicio lo decide /dashboard', function () {
    foreach (['recepcionista', '', null, 'ADMINISTRADOR', ' administrador'] as $rol) {
        assert(Navegacion::enlaces($rol) === [], 'Sin menu para ' . json_encode($rol));
        assert(Navegacion::inicio($rol) === '/dashboard', 'Inicio generico para ' . json_encode($rol));
    }
});

runTest('Pantalla de inicio por rol', function () {
    assert(Navegacion::inicio('administrador') === '/admin/dashboard', 'Administrador');
    assert(Navegacion::inicio('veterinario') === '/veterinario/agenda', 'Veterinario');
});

runTest('Nombre del rol e insignia', function () {
    assert(Navegacion::etiquetaRol('administrador') === 'Administrador' && Navegacion::claseInsignia('administrador') === 'admin', 'Administrador');
    assert(Navegacion::etiquetaRol('veterinario') === 'Veterinario' && Navegacion::claseInsignia('veterinario') === 'vet', 'Veterinario');
    assert(Navegacion::etiquetaRol('recepcionista') === 'Recepcionista', 'Un rol nuevo se muestra capitalizado');
});

runTest('Cada rol conocido tiene menu, inicio y sin enlaces repetidos', function () {
    assert(Navegacion::roles() === ['administrador', 'veterinario'], 'Roles conocidos: ' . json_encode(Navegacion::roles()));
    foreach (Navegacion::roles() as $rol) {
        $enlaces = Navegacion::enlaces($rol);
        assert($enlaces !== [], "$rol tiene menu");
        assert(count(array_unique(array_column($enlaces, 'url'))) === count($enlaces), "$rol: URLs unicas");
        assert(count(array_unique(array_column($enlaces, 'id'))) === count($enlaces), "$rol: ids unicos");
        foreach ($enlaces as $e) {
            assert(str_starts_with($e['url'], '/') && $e['etiqueta'] !== '' && $e['id'] !== '', "$rol: enlace completo");
        }
        assert(str_starts_with(Navegacion::inicio($rol), '/') && Navegacion::inicio($rol) !== '/dashboard', "$rol tiene inicio propio");
    }
});

runTest('Todas las opciones del menu y los inicios existen como rutas GET en index.php', function () use ($srcBase) {
    $index = (string) file_get_contents($srcBase . '/public/index.php');
    preg_match_all('/\$router->get\(\'([^\']+)\'/', $index, $m);
    $rutas = $m[1];
    assert(count($rutas) > 10, 'Se leyeron las rutas');
    foreach (Navegacion::roles() as $rol) {
        foreach (urls($rol) as $url) {
            assert(in_array($url, $rutas, true), "El menu de $rol enlaza a $url, que no es una ruta GET registrada");
        }
        assert(in_array(Navegacion::inicio($rol), $rutas, true), "El inicio de $rol no es una ruta GET registrada");
    }
});

runTest('Menu compartido, administrador: sus 7 enlaces, nombre, insignia y cerrar sesion', function () {
    $html = dibujarMenu(['rol' => 'administrador', 'nombre' => 'Ana Admin']);
    assert(hrefs($html) === ['/admin/dashboard', '/propietarios', '/mascotas', '/veterinarios', '/citas', '/servicios', '/horarios', '/logout'], json_encode(hrefs($html)));
    assert(str_contains($html, 'Ana Admin') && str_contains($html, 'badge-admin') && str_contains($html, 'Administrador'), 'Nombre e insignia');
});

runTest('Menu compartido, veterinario: sus 3 enlaces; sin Dashboard de admin ni Citas', function () {
    $html = dibujarMenu(['rol' => 'veterinario', 'nombre' => 'Dra. Laura']);
    assert(hrefs($html) === ['/veterinario/agenda', '/mascotas', '/propietarios', '/logout'], json_encode(hrefs($html)));
    assert(!str_contains($html, '/admin/dashboard') && !str_contains($html, '"/citas"'), 'Sin enlaces de administrador');
    assert(str_contains($html, 'badge-vet') && str_contains($html, 'Veterinario'), 'Insignia');
});

runTest('Menu compartido: resalta solo la seccion activa', function () {
    $html = dibujarMenu(['rol' => 'veterinario', 'nombre' => 'X'], 'mascotas');
    assert(substr_count($html, 'aria-current="page"') === 1, 'Exactamente una seccion activa');
    assert(preg_match('/href="\/mascotas"[^>]*aria-current="page"/', $html) === 1, 'Es Mascotas');
    assert(substr_count(dibujarMenu(['rol' => 'administrador', 'nombre' => 'X'], ''), 'aria-current') === 0, 'Sin activa si no se indica');
    assert(substr_count(dibujarMenu(['rol' => 'administrador', 'nombre' => 'X'], 'inexistente'), 'aria-current') === 0, 'Seccion inexistente: ninguna');
});

runTest('Menu compartido: el nombre se escapa (sin HTML inyectado) y un rol sin menu solo ve cerrar sesion', function () {
    $html = dibujarMenu(['rol' => 'administrador', 'nombre' => '<script>alert(1)</script> "O\'Neil" & Co']);
    assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Nombre escapado');
    assert(str_contains($html, '&quot;O&#039;Neil&quot; &amp; Co'), 'Comillas y ampersand escapados');

    $sin = dibujarMenu(['rol' => 'recepcionista', 'nombre' => 'R']);
    assert(hrefs($sin) === ['/logout'], 'Sin menu: solo cerrar sesion');
    $vacio = dibujarMenu([]);
    assert(hrefs($vacio) === ['/logout'], 'Sin sesion: solo cerrar sesion');
});

runTest('Ninguna pantalla trae su propio menu: todas usan el parcial compartido', function () use ($srcBase) {
    $raiz = $srcBase . '/app/Views';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    $conMenuPropio = [];
    $usanParcial = [];
    foreach ($it as $archivo) {
        if ($archivo->getExtension() !== 'php') { continue; }
        $ruta = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
        $contenido = (string) file_get_contents($archivo->getPathname());
        if (str_contains($contenido, 'class="navbar"') && $ruta !== 'partials/navbar.php') {
            $conMenuPropio[] = $ruta;
        }
        if (str_contains($contenido, "partials/navbar.php'")) {
            $usanParcial[] = $ruta;
        }
    }
    assert($conMenuPropio === [], 'Vistas con menu copiado: ' . implode(', ', $conMenuPropio));

    sort($usanParcial);
    $esperadas = ['citas/confirmar_cancelacion.php', 'citas/listar.php', 'consultas/crear.php', 'dashboard/admin.php',
                  'dashboard/index.php', 'dashboard/veterinario.php', 'historial/detalle.php', 'horarios/formulario.php', 'horarios/listar.php',
                  'mascotas/crear.php', 'mascotas/editar.php', 'mascotas/listar.php',
                  'propietarios/crear.php', 'propietarios/editar.php', 'propietarios/listar.php',
                  'servicios/formulario.php', 'servicios/listar.php',
                  'veterinarios/crear.php', 'veterinarios/editar.php', 'veterinarios/listar.php'];
    assert($usanParcial === $esperadas, 'Usan el parcial: ' . implode(', ', $usanParcial));
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
