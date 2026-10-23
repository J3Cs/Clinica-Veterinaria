<?php
/**
 * Pruebas: CsrfMiddleware (SEC-01) — token vacío, incorrecto y válido.
 * Ejecución: php pruebas/test_csrf.php
 */
declare(strict_types=1);

require __DIR__ . '/../src/app/Middlewares/CsrfMiddleware.php';

use App\Middlewares\CsrfMiddleware;

$fallos = 0;
function check(string $nombre, bool $ok): void {
    global $fallos;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $nombre . "\n";
    if (!$ok) { $fallos++; }
}

/** Ejecuta validateToken en un proceso aparte (la función hace exit en fallo). */
function resultado(?string $almacenado, string $enviado): int {
    $init = $almacenado === null ? '' : '$_SESSION["csrf_token"]=' . var_export($almacenado, true) . ';';
    $code = '$_SESSION=[];' . $init
          . 'require ' . var_export(__DIR__ . '/../src/app/Middlewares/CsrfMiddleware.php', true) . ';'
          . 'App\Middlewares\CsrfMiddleware::validateToken(' . var_export($enviado, true) . ');'
          . 'echo "PASS";';
    $out = shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($code) . ' 2>/dev/null');
    return trim((string)$out) === 'PASS' ? 200 : 419;
}

check('Sin token en sesión y token vacío -> rechazado', resultado(null, '') === 419);
check('Token en sesión y envío vacío -> rechazado',     resultado('abc123', '') === 419);
check('Token incorrecto -> rechazado',                  resultado('abc123', 'otro') === 419);
check('Token correcto -> aceptado',                     resultado('abc123', 'abc123') === 200);
check('generateToken devuelve 64 hex', (function () {
    $_SESSION = [];
    return (bool) preg_match('/^[a-f0-9]{64}$/', CsrfMiddleware::generateToken());
})());

exit($fallos === 0 ? 0 : 1);
