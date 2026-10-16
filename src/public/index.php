<?php
// Autocarga manual basada en Namespaces (Reemplaza a Composer)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Importar clases utilizando su Namespace completo
use App\Controllers\HomeController;

// Cálculo dinámico de la ruta para soportar subcarpetas en XAMPP y raíz en Docker
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remover la base del script si se encuentra en subdirectorio (ej. /mi-app/public)
$uri = '/' . ltrim(substr($requestUri, strlen($scriptName)), '/');
$uri = rtrim($uri, '/');
if ($uri === '') {
    $uri = '/';
}

switch ($uri) {
    case '/':
        $controller = new HomeController();
        $controller->index();
        break;

    default:
        http_response_code(404);
        echo "<h1>404 - Página no encontrada</h1><p>Ruta solicitada: " . htmlspecialchars($uri) . "</p>";
        break;
}