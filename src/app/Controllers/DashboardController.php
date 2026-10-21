<?php
namespace App\Controllers;

use App\Middlewares\AuthMiddleware;

/**
 * DashboardController — ARQ-01, ACC-01
 * Maneja los paneles privados post-login diferenciados por rol.
 */
class DashboardController {

    /**
     * GET /admin/dashboard — Panel exclusivo de Administrador
     */
    public function admin(): void {
        AuthMiddleware::requireRole('administrador');

        $nombre = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rol    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');
        $correo = htmlspecialchars($_SESSION['correo'] ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/dashboard/admin.php';
    }

    /**
     * GET /veterinario/agenda — Panel / Agenda del Veterinario
     */
    public function veterinario(): void {
        AuthMiddleware::requireRole('veterinario');

        $nombre = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rol    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');
        $correo = htmlspecialchars($_SESSION['correo'] ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/dashboard/veterinario.php';
    }

    /**
     * GET /dashboard — Ruta general que deriva al panel según rol
     */
    public function index(): void {
        AuthMiddleware::requireLogin();

        $rol = $_SESSION['rol'] ?? '';
        if ($rol === 'administrador') {
            header('Location: /admin/dashboard');
            exit;
        }

        if ($rol === 'veterinario') {
            header('Location: /veterinario/agenda');
            exit;
        }

        $nombre = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rol    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');
        require_once __DIR__ . '/../Views/dashboard/index.php';
    }
}
