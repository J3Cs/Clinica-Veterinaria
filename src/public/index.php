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

// ── 2b. Zona horaria (las citas dependen de la hora local de la clínica) ── //
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/El_Salvador');

// ── 3. Sesión ───────────────────────────────────────────────────── //
session_start();

// ── 4. Definición de rutas ──────────────────────────────────────── //
use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PropietarioController;
use App\Controllers\MascotaController;
use App\Controllers\CitaController;
use App\Controllers\ConsultaController;
use App\Controllers\ServicioController;
use App\Controllers\HorarioAtencionControlador;

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

// Pestaña de citas: agendar (US-04), listar y cancelar (US-05)
$router->get('/citas',              CitaController::class, 'index');
$router->get('/citas/crear',        CitaController::class, 'create');   // redirige a /citas
$router->post('/citas',             CitaController::class, 'store');
$router->get('/citas/cancelar',     CitaController::class, 'confirmarCancelacion');
$router->post('/citas/cancelar',    CitaController::class, 'cancelar');

// Consultas veterinarias — solo rol Veterinario (US-06)
$router->get('/consultas/crear',    ConsultaController::class, 'crear');
$router->post('/consultas',         ConsultaController::class, 'store');
// Rutas de gestión del catálogo de servicios (US-10)
$router->get('/servicios',          ServicioController::class, 'index');
$router->get('/servicios/crear',    ServicioController::class, 'create');
$router->post('/servicios',         ServicioController::class, 'store');
$router->get('/servicios/editar',   ServicioController::class, 'edit');
$router->post('/servicios/editar',  ServicioController::class, 'update');
$router->post('/servicios/estado',  ServicioController::class, 'toggleEstado');

// Rutas de gestión de horarios de atención (US-11)
$router->get('/horarios',          HorarioAtencionControlador::class, 'index');
$router->get('/horarios/crear',    HorarioAtencionControlador::class, 'create');
$router->post('/horarios',         HorarioAtencionControlador::class, 'store');
$router->get('/horarios/editar',   HorarioAtencionControlador::class, 'edit');
$router->post('/horarios/editar',  HorarioAtencionControlador::class, 'update');
$router->post('/horarios/eliminar', HorarioAtencionControlador::class, 'delete');

// ── 5. Despacho ─────────────────────────────────────────────────── //
$router->dispatch();
