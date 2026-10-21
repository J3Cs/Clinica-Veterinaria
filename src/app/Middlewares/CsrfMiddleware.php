<?php
namespace App\Middlewares;

/**
 * CsrfMiddleware — SEC-01
 * Genera y valida tokens CSRF para todas las acciones POST que modifiquen datos.
 */
class CsrfMiddleware {

    private const TOKEN_KEY = 'csrf_token';

    /**
     * Genera (o reutiliza) el token de sesión CSRF.
     * Debe llamarse al renderizar cualquier formulario POST.
     */
    public static function generateToken(): string {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Valida el token enviado en POST contra el almacenado en sesión.
     * Termina con HTTP 419 si no coincide — nunca expone detalles internos.
     */
    public static function validateToken(string $submitted): void {
        $stored = $_SESSION[self::TOKEN_KEY] ?? '';
        if (!hash_equals($stored, $submitted)) {
            http_response_code(419);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Token inválido</title></head>';
            echo '<body style="font-family:sans-serif;padding:2rem"><h1>Solicitud inválida</h1>';
            echo '<p>El token de seguridad no es válido. Por favor recarga la página e intenta nuevamente.</p>';
            echo '<p><a href="/">Volver al inicio</a></p></body></html>';
            exit;
        }
        // Rotar token tras validación exitosa
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}
