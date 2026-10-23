<?php
namespace App\Middlewares;

class ErrorHandlerMiddleware {
    public static function register() {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException($exception) {
        http_response_code(500);
        $debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        // Siempre registrar el detalle en el log del servidor, nunca al usuario en producción (SEC-01)
        error_log(sprintf('[%s] %s en %s:%d', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));

        if ($debug) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; font-family: monospace; border-left: 5px solid #dc3545; margin: 20px;'>";
            echo "<h3>❌ Error Crítico (Excepción):</h3>";
            echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>Archivo:</strong> " . $exception->getFile() . " en la línea " . $exception->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            echo "</div>";
        } else {
            echo "<h1>500 - Error interno del servidor</h1>";
        }
        exit;
    }

    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleException(new \ErrorException(
                $error['message'], 0, $error['type'], $error['file'], $error['line']
            ));
        }
    }
}