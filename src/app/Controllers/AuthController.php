<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Middlewares\CsrfMiddleware;

/**
 * AuthController — ARQ-01, SEC-01
 * Coordina login y logout. No contiene lógica de negocio.
 */
class AuthController {

    private AuthService $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    /**
     * GET /login — muestra el formulario de ingreso.
     * Redirige al dashboard si ya hay sesión activa.
     */
    public function showLogin(): void {
        if (!empty($_SESSION['id_usuario'])) {
            header('Location: /dashboard');
            exit;
        }
        $csrfToken  = CsrfMiddleware::generateToken();
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        require_once __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * POST /login — procesa credenciales.
     */
    public function handleLogin(): void {
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $usuario  = trim($_POST['usuario'] ?? '');
        $password = $_POST['contrasena'] ?? '';

        if ($this->authService->login($usuario, $password)) {
            header('Location: /dashboard');
            exit;
        }

        // Mensaje genérico — no revela si el usuario existe (SEC-01)
        $_SESSION['flash_error'] = 'Usuario o contraseña incorrectos.';
        header('Location: /login');
        exit;
    }

    /**
     * GET /logout — cierra la sesión y redirige al login.
     */
    public function logout(): void {
        $this->authService->logout();
        header('Location: /login');
        exit;
    }
}
