<?php
namespace App\Controllers;

use App\Middlewares\AuthMiddleware;

/**
 * DashboardController — ARQ-01, ACC-01
 * Panel de bienvenida post-login. Stub para Increment 0.
 * Los incrementos futuros agregarán paneles diferenciados por rol.
 */
class DashboardController {

    /**
     * GET /dashboard
     */
    public function index(): void {
        AuthMiddleware::requireLogin();

        $nombre = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rol    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');
        require_once __DIR__ . '/../Views/dashboard/index.php';
    }
}
