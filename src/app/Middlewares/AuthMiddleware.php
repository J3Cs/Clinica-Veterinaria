<?php
namespace App\Middlewares;

/**
 * AuthMiddleware — ACC-01, SEC-01
 * Valida sesión activa y rol del usuario en cada acción protegida.
 * Los controladores llaman estos métodos al inicio de cada acción privada.
 */
class AuthMiddleware {

    /**
     * Redirige a /login si no hay sesión activa.
     */
    public static function requireLogin(): void {
        if (empty($_SESSION['id_usuario'])) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Devuelve HTTP 403 si el rol de sesión no coincide con el requerido.
     * Se llama DESPUÉS de requireLogin().
     *
     * @param string|string[] $roles  Rol o lista de roles permitidos.
     */
    public static function requireRole(string|array $roles): void {
        self::requireLogin();
        $roles = (array) $roles;
        if (!in_array($_SESSION['rol'] ?? '', $roles, true)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403</title></head>';
            echo '<body style="font-family:sans-serif;padding:2rem"><h1>403 — Acceso denegado</h1>';
            echo '<p>No tienes permisos para acceder a esta sección.</p>';
            echo '<p><a href="/">Volver al inicio</a></p></body></html>';
            exit;
        }
    }
}
