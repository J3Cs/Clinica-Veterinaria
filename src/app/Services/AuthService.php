<?php
namespace App\Services;

use App\Repositories\UsuarioRepository;

/**
 * AuthService — T-011, ARQ-01, SEC-01, ACC-01
 * Contiene todas las reglas de negocio de autenticación y sesión de usuario.
 * Maneja credenciales, estado activo, session fixation y cierre seguro.
 */
class AuthService {

    private UsuarioRepository $repo;

    public function __construct(?UsuarioRepository $repo = null) {
        $this->repo = $repo ?? new UsuarioRepository();
    }

    /**
     * Autentica a un usuario y establece su sesión si las credenciales y el estado son válidos.
     *
     * @param string $identifier Nombre de usuario o correo electrónico.
     * @param string $password   Contraseña en texto plano enviada por el formulario.
     * @return array{success: bool, message: string, role: ?string} Resultado estructurado.
     */
    public function authenticate(string $identifier, string $password): array {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Todos los campos son obligatorios.',
                'role'    => null,
            ];
        }

        // Buscar usuario por nombre de usuario o correo
        $user = $this->repo->findByIdentifier($identifier);

        // Si no existe, mensaje genérico para no revelar existencia de cuenta (SEC-01)
        if ($user === null) {
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos.',
                'role'    => null,
            ];
        }

        // Validar si la cuenta está activa (T-011)
        if ((int)$user['activo'] !== 1) {
            return [
                'success' => false,
                'message' => 'La cuenta se encuentra inactiva. Contacte al administrador.',
                'role'    => null,
            ];
        }

        // Verificar hash de contraseña (SEC-01)
        if (!password_verify($password, $user['contrasena'])) {
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos.',
                'role'    => null,
            ];
        }

        // Validar rol permitido (ACC-01: solo administrador o veterinario)
        $role = (string)$user['rol'];
        if (!in_array($role, ['administrador', 'veterinario'], true)) {
            return [
                'success' => false,
                'message' => 'Acceso denegado. Perfil de usuario no autorizado.',
                'role'    => null,
            ];
        }

        // Iniciar sesión si no está iniciada y regenerar ID para prevenir session fixation (SEC-01)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['id_usuario'] = (int) $user['id_usuario'];
        $_SESSION['nombre']     = (string) $user['nombre'];
        $_SESSION['usuario']    = (string) $user['usuario'];
        $_SESSION['correo']     = (string) $user['correo'];
        $_SESSION['rol']        = $role;
        $_SESSION['login_time'] = time();

        return [
            'success' => true,
            'message' => 'Autenticación exitosa.',
            'role'    => $role,
        ];
    }

    /**
     * Método booleano retrocompatible para login.
     *
     * @param string $identifier
     * @param string $password
     * @return bool
     */
    public function login(string $identifier, string $password): bool {
        $result = $this->authenticate($identifier, $password);
        return $result['success'];
    }

    /**
     * Cierra la sesión activa de forma segura y destruye la cookie de sesión.
     */
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (!headers_sent() && ini_get('session.use_cookies')) {
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

    /**
     * Retorna los datos del usuario en la sesión activa.
     *
     * @return array<string,mixed>|null
     */
    public function getCurrentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['id_usuario'])) {
            return null;
        }

        return [
            'id_usuario' => $_SESSION['id_usuario'],
            'nombre'     => $_SESSION['nombre'] ?? '',
            'usuario'    => $_SESSION['usuario'] ?? '',
            'correo'     => $_SESSION['correo'] ?? '',
            'rol'        => $_SESSION['rol'] ?? '',
        ];
    }
}
