<?php
/**
 * Pruebas: CsrfMiddleware (SEC-01) — token vacío, incorrecto y válido.
 * Ejecución: php pruebas/test_csrf.php
 */
declare(strict_types=1);

$baseDirs = [
    __DIR__ . '/../src/app/',
    '/var/www/html/app/',
    __DIR__ . '/../app/',
];
$csrfFile = null;
foreach ($baseDirs as $dir) {
    $candidate = $dir . 'Middlewares/CsrfMiddleware.php';
    if (file_exists($candidate)) {
        $csrfFile = $candidate;
        break;
    }
}
if (!$csrfFile) {
    throw new RuntimeException("CsrfMiddleware.php no encontrado");
}

require_once $csrfFile;

use App\Middlewares\CsrfMiddleware;

$fallos = 0;
function check(string $nombre, bool $ok): void {
    global $fallos;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $nombre . "\n";
    if (!$ok) { $fallos++; }
}

/** Ejecuta validateToken en un proceso aparte (la función hace exit en fallo). */
function resultado(?string $almacenado, string $enviado): int {
    global $csrfFile;
    $init = $almacenado === null ? '' : '$_SESSION["csrf_token"]=' . var_export($almacenado, true) . ';';
    $code = '$_SESSION=[];' . $init
          . 'require ' . var_export($csrfFile, true) . ';'
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
