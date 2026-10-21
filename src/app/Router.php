<?php
namespace App;

/**
 * Router — ARQ-01
 * Responsabilidad: resolver la URI a un controlador y método.
 * Las vistas no consultan la BD; los controladores coordinan el flujo.
 */
class Router {

    /** @var array<array{method:string, path:string, controller:string, action:string}> */
    private array $routes = [];

    // ------------------------------------------------------------------ //
    //  Registro de rutas
    // ------------------------------------------------------------------ //

    public function get(string $path, string $controller, string $action): void {
        $this->routes[] = ['method' => 'GET', 'path' => $path, 'controller' => $controller, 'action' => $action];
    }

    public function post(string $path, string $controller, string $action): void {
        $this->routes[] = ['method' => 'POST', 'path' => $path, 'controller' => $controller, 'action' => $action];
    }

    // ------------------------------------------------------------------ //
    //  Despacho
    // ------------------------------------------------------------------ //

    public function dispatch(): void {
        $method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri        = '/' . ltrim(substr($requestUri, strlen($scriptName)), '/');
        $uri        = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                $controllerClass = $route['controller'];
                $controllerObj   = new $controllerClass();
                $action          = $route['action'];
                $controllerObj->$action();
                return;
            }
        }

        // 404 — nunca exponer rutas internas (SEC-01)
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404</title></head>';
        echo '<body style="font-family:sans-serif;padding:2rem"><h1>404 — Página no encontrada</h1>';
        echo '<p><a href="/">Volver al inicio</a></p></body></html>';
    }
}
