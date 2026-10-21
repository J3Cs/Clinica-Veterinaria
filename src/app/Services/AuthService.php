<?php
namespace App\Services;

use App\Repositories\UsuarioRepository;

/**
 * AuthService — ARQ-01, SEC-01, ACC-01
 * Toda la lógica de autenticación vive aquí.
 * Nunca revela la causa exacta de un fallo (SEC-01).
 */
class AuthService {

    private UsuarioRepository $repo;

    public function __construct() {
        $this->repo = new UsuarioRepository();
    }

    /**
     * Intenta autenticar al usuario.
     * En éxito: regenera ID de sesión y almacena datos mínimos.
     *
     * @return bool true si el login fue exitoso.
     */
    public function login(string $usuario, string $password): bool {
        $row = $this->repo->findActiveByUsername(trim($usuario));

        if ($row === null) {
            return false;
        }

        if (!password_verify($password, $row['contrasena'])) {
            return false;
        }

        // Regenerar ID de sesión al autenticar — previene session fixation
        session_regenerate_id(true);

        $_SESSION['id_usuario'] = (int) $row['id_usuario'];
        $_SESSION['nombre']     = $row['nombre'];
        $_SESSION['rol']        = $row['rol'];

        return true;
    }

    /**
     * Cierra sesión de forma limpia.
     */
    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
