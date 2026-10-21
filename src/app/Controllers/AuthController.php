<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Middlewares\CsrfMiddleware;

/**
 * AuthController — T-012, ARQ-01, SEC-01, ACC-01
 * Coordina las acciones del flujo web de autenticación:
 * showLogin, handleLogin y logout, con soporte de redirección según rol.
 */
class AuthController {

    private AuthService $authService;

    public function __construct(?AuthService $authService = null) {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * GET /login o GET / — Muestra el formulario de inicio de sesión.
     * Si ya existe sesión activa, redirige según el rol (ACC-01).
     */
    public function showLogin(): void {
        if (!empty($_SESSION['id_usuario'])) {
            $this->redirectByRole($_SESSION['rol'] ?? '');
            return;
        }

        $csrfToken       = CsrfMiddleware::generateToken();
        $flashError      = $_SESSION['flash_error'] ?? null;
        $flashSuccess    = $_SESSION['flash_success'] ?? null;
        $oldIdentifier   = $_SESSION['old_identifier'] ?? '';

        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['old_identifier']);

        if (isset($_GET['logout'])) {
            $flashSuccess = 'Has cerrado sesión exitosamente.';
        }

        require_once __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Alias en español: mostrarFormulario
     */
    public function mostrarFormulario(): void {
        $this->showLogin();
    }

    /**
     * POST /login — Procesa credenciales de usuario o correo.
     * Valida CSRF, delega autenticación a AuthService y redirige según el rol.
     */
    public function handleLogin(): void {
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        // Acepta identifier o usuario indistintamente
        $identifier = trim($_POST['identifier'] ?? $_POST['usuario'] ?? '');
        $password   = (string)($_POST['contrasena'] ?? '');

        $result = $this->authService->authenticate($identifier, $password);

        if ($result['success']) {
            $this->redirectByRole($result['role']);
            return;
        }

        // Mensaje flash en caso de fallo (SEC-01)
        $_SESSION['flash_error']    = $result['message'];
        $_SESSION['old_identifier'] = $identifier;
        header('Location: /login');
        exit;
    }

    /**
     * Alias en español: iniciarSesion
     */
    public function iniciarSesion(): void {
        $this->handleLogin();
    }

    /**
     * GET /logout — Cierra sesión y redirige a la pantalla de login.
     */
    public function logout(): void {
        $this->authService->logout();
        header('Location: /login?logout=1');
        exit;
    }

    /**
     * Alias en español: cerrarSesion
     */
    public function cerrarSesion(): void {
        $this->logout();
    }

    /**
     * Redirige al destino correspondiente según el rol del usuario (T-012, ACC-01).
     *
     * @param string|null $role
     */
    public function redirectByRole(?string $role): void {
        if ($role === 'administrador') {
            header('Location: /admin/dashboard');
            exit;
        }

        if ($role === 'veterinario') {
            header('Location: /veterinario/agenda');
            exit;
        }

        header('Location: /dashboard');
        exit;
    }
}
