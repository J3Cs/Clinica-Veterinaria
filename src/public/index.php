<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Middlewares\ErrorHandlerMiddleware;
use App\Controllers\HomeController;

ErrorHandlerMiddleware::register();

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