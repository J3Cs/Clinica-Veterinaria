<?php
/**
 * Punto de entrada de la aplicación — Clínica Veterinaria
 *
 * Responsabilidades de este archivo (únicamente):
 *   1. Registrar el autoloader PSR-4 manual (TEC-01)
 *   2. Registrar el manejador de errores
 *   3. Iniciar sesión PHP
 *   4. Instanciar el Router y definir las rutas
 *   5. Despachar la solicitud
 */

// ── 1. Autoloader manual PSR-4 (sin Composer, TEC-01) ──────────── //
spl_autoload_register(function (string $class) {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ── 2. Manejador global de errores ─────────────────────────────── //
use App\Middlewares\ErrorHandlerMiddleware;
ErrorHandlerMiddleware::register();

// ── 3. Sesión ───────────────────────────────────────────────────── //
session_start();

// ── 4. Definición de rutas ──────────────────────────────────────── //
use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PropietarioController;
use App\Controllers\MascotaController;

$router = new Router();

// Rutas públicas de autenticación (US-01)
$router->get('/',                  AuthController::class, 'showLogin');
$router->get('/login',             AuthController::class, 'showLogin');
$router->post('/login',            AuthController::class, 'handleLogin');
$router->get('/logout',            AuthController::class, 'logout');

// Rutas protegidas — la autenticación y rol se verifican en el controlador (ACC-01)
$router->get('/dashboard',          DashboardController::class, 'index');
$router->get('/admin/dashboard',    DashboardController::class, 'admin');
$router->get('/veterinario/agenda', DashboardController::class, 'veterinario');

// Rutas de gestión de propietarios (US-02)
$router->get('/propietarios',       PropietarioController::class, 'index');
$router->get('/propietarios/crear', PropietarioController::class, 'create');
$router->post('/propietarios',      PropietarioController::class, 'store');

// Rutas de gestión de mascotas (US-03)
$router->get('/mascotas',           MascotaController::class, 'index');
$router->get('/mascotas/crear',     MascotaController::class, 'create');
$router->post('/mascotas',          MascotaController::class, 'store');

// ── 5. Despacho ─────────────────────────────────────────────────── //
$router->dispatch();
