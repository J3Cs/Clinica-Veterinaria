<?php
/**
 * Suite de Pruebas Automatizadas con PHP Nativo — T-015
 * Cumple con TEC-01: Sin dependencias externas ni Composer/PHPUnit.
 *
 * Ejecución desde la raíz del proyecto:
 *   docker exec -i php_mvc_dual php < pruebas/test_autenticacion.php
 */

declare(strict_types=1);

// Iniciar sesión antes de emitir cualquier salida por CLI
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar aserciones nativas en PHP
ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-01 (RF01)\n";
echo "========================================================\n\n";

// ── 1. Registro del Autoloader PSR-4 ────────────────────────────── //
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $baseDirs = [
        __DIR__ . '/../src/app/',
        '/var/www/html/app/',
        __DIR__ . '/../app/',
    ];

    $relative = substr($class, strlen($prefix));
    $relativeFile = str_replace('\\', '/', $relative) . '.php';

    foreach ($baseDirs as $dir) {
        $file = $dir . $relativeFile;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

use App\Middlewares\CsrfMiddleware;
use App\Services\AuthService;
use App\Repositories\UsuarioRepository;
use App\Models\UsuarioModel;

$totalTests = 0;
$passedTests = 0;

function runTest(string $description, callable $testFn): void {
    global $totalTests, $passedTests;
    $totalTests++;
    echo sprintf("[%02d] %s ... ", $totalTests, $description);
    try {
        $testFn();
        $passedTests++;
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "FALLO\n";
        echo "     Error: " . $e->getMessage() . "\n";
        echo "     En: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

// ────────────────────────────────────────────────────────────────── //
// GRUPO 1: Hashing y Seguridad de Contraseñas (SEC-01)
// ────────────────────────────────────────────────────────────────── //
runTest('SEC-01: Hash con BCRYPT y verificacion valida', function () {
    $password = 'Secreto123*';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    assert(is_string($hash), 'El hash debe ser una cadena valida');
    assert(password_verify($password, $hash) === true, 'La contrasena original debe verificarse');
    assert(password_verify('PasswordIncorrecto', $hash) === false, 'Una contrasena erronea debe ser rechazada');
});

// ────────────────────────────────────────────────────────────────── //
// GRUPO 2: CsrfMiddleware (SEC-01)
// ────────────────────────────────────────────────────────────────── //
runTest('SEC-01: Generacion y persistencia de token CSRF', function () {
    $token1 = CsrfMiddleware::generateToken();
    assert(is_string($token1) && strlen($token1) === 64, 'Token CSRF debe ser un hex de 64 caracteres');
    $token2 = CsrfMiddleware::generateToken();
    assert($token1 === $token2, 'Dentro de la misma sesion el token no debe cambiar hasta ser validado');
});

runTest('SEC-01: Validacion de token CSRF con rotacion posterior', function () {
    $token = CsrfMiddleware::generateToken();
    CsrfMiddleware::validateToken($token);
    $nuevoToken = CsrfMiddleware::generateToken();
    assert($token !== $nuevoToken, 'El token CSRF debe rotar tras una validacion exitosa');
});

// ────────────────────────────────────────────────────────────────── //
// GRUPO 3: Repositorio y Servicio con Doble de Prueba (En Memoria)
// ────────────────────────────────────────────────────────────────── //

class TestUsuarioModelMock extends UsuarioModel {
    private array $data;

    public function __construct(array $initialData) {
        $this->data = $initialData;
    }

    public function findByIdentifier(string $identifier): ?array {
        foreach ($this->data as $row) {
            if ($row['usuario'] === $identifier || $row['correo'] === $identifier) {
                return $row;
            }
        }
        return null;
    }

    public function findById(int $id): ?array {
        foreach ($this->data as $row) {
            if ((int)$row['id_usuario'] === $id) {
                return $row;
            }
        }
        return null;
    }
}

$mockUsers = [
    [
        'id_usuario' => 1,
        'nombre'     => 'Administrador General',
        'correo'     => 'admin@clinica.com',
        'usuario'    => 'admin',
        'contrasena' => password_hash('Admin1234*', PASSWORD_BCRYPT, ['cost' => 12]),
        'rol'        => 'administrador',
        'activo'     => 1,
        'fecha_creacion' => '2026-10-21 00:00:00',
    ],
    [
        'id_usuario' => 2,
        'nombre'     => 'Dr. Veterinario',
        'correo'     => 'vet@clinica.com',
        'usuario'    => 'drveterinario',
        'contrasena' => password_hash('Vet1234*', PASSWORD_BCRYPT, ['cost' => 12]),
        'rol'        => 'veterinario',
        'activo'     => 1,
        'fecha_creacion' => '2026-10-21 00:00:00',
    ],
    [
        'id_usuario' => 3,
        'nombre'     => 'Usuario Inactivo',
        'correo'     => 'inactivo@clinica.com',
        'usuario'    => 'inactivo',
        'contrasena' => password_hash('Inactivo123*', PASSWORD_BCRYPT, ['cost' => 12]),
        'rol'        => 'veterinario',
        'activo'     => 0, // INACTIVO
        'fecha_creacion' => '2026-10-21 00:00:00',
    ]
];

$testModel = new TestUsuarioModelMock($mockUsers);
$testRepo  = new UsuarioRepository($testModel);
$testAuth  = new AuthService($testRepo);

runTest('T-010: Repositorio busca por usuario O por correo indistintamente', function () use ($testRepo) {
    $byUser  = $testRepo->findByIdentifier('admin');
    $byEmail = $testRepo->findByIdentifier('admin@clinica.com');

    assert($byUser !== null, 'Debe encontrar por nombre de usuario');
    assert($byEmail !== null, 'Debe encontrar por correo electronico');
    assert($byUser['id_usuario'] === $byEmail['id_usuario'], 'Ambas consultas deben apuntar al mismo usuario');
});

runTest('T-010: Repositorio filtra cuentas inactivas con findActiveByIdentifier', function () use ($testRepo) {
    $inactiveRaw = $testRepo->findByIdentifier('inactivo');
    assert($inactiveRaw !== null, 'El usuario inactivo existe en BD');

    $inactiveFiltered = $testRepo->findActiveByIdentifier('inactivo');
    assert($inactiveFiltered === null, 'findActiveByIdentifier debe retornar null para cuentas inactivas');

    assert($testRepo->isActive('inactivo') === false, 'isActive debe retornar false');
    assert($testRepo->isActive('admin') === true, 'isActive debe retornar true para admin');
    assert($testRepo->isActive('no_existe') === null, 'isActive debe retornar null si no existe');
});

runTest('T-011: Autenticacion rechaza campos vacios', function () use ($testAuth) {
    $res = $testAuth->authenticate('', '');
    assert($res['success'] === false, 'Debe fallar si los campos estan vacios');
    assert(str_contains(mb_strtolower($res['message']), 'obligatorios'), 'Mensaje debe advertir obligatoriedad');
});

runTest('T-011: Autenticacion rechaza usuario inexistente con mensaje generico (SEC-01)', function () use ($testAuth) {
    $res = $testAuth->authenticate('fantasma', 'Cualquiera123*');
    assert($res['success'] === false, 'Debe fallar');
    assert(str_contains(mb_strtolower($res['message']), 'incorrect'), 'Mensaje debe ser generico');
});

runTest('T-011: Autenticacion rechaza cuenta inactiva', function () use ($testAuth) {
    $res = $testAuth->authenticate('inactivo', 'Inactivo123*');
    assert($res['success'] === false, 'Debe rechazar cuenta inactiva');
    assert(str_contains(mb_strtolower($res['message']), 'inactiva'), 'Debe indicar que la cuenta esta inactiva');
});

runTest('T-011: Autenticacion rechaza contrasena erronea con mensaje generico', function () use ($testAuth) {
    $res = $testAuth->authenticate('admin', 'PasswordFalsa123*');
    assert($res['success'] === false, 'Debe rechazar contrasena incorrecta');
    assert(str_contains(mb_strtolower($res['message']), 'incorrect'), 'Mensaje debe ser generico');
});

runTest('T-011 / T-012: Autenticacion exitosa de Administrador con usuario y correo', function () use ($testAuth) {
    // 1. Acceso con nombre de usuario
    $resUser = $testAuth->authenticate('admin', 'Admin1234*');
    assert($resUser['success'] === true, 'Debe autenticar con nombre de usuario');
    assert($resUser['role'] === 'administrador', 'El rol debe ser administrador');
    assert($_SESSION['rol'] === 'administrador', 'La variable de sesion debe estar establecida');
    assert(!empty($_SESSION['id_usuario']), 'id_usuario debe estar en sesion');

    // 2. Acceso con correo electronico
    $resEmail = $testAuth->authenticate('admin@clinica.com', 'Admin1234*');
    assert($resEmail['success'] === true, 'Debe autenticar con correo');
    assert($resEmail['role'] === 'administrador', 'El rol debe ser administrador');
});

runTest('T-011 / T-012: Autenticacion exitosa de Veterinario y asignacion de rol', function () use ($testAuth) {
    $res = $testAuth->authenticate('drveterinario', 'Vet1234*');
    assert($res['success'] === true, 'Debe autenticar al veterinario');
    assert($res['role'] === 'veterinario', 'El rol retornado debe ser veterinario');
    assert($_SESSION['rol'] === 'veterinario', 'Rol veterinario en sesion');
});

runTest('T-011: Cierre de sesion seguro y limpieza de $_SESSION', function () use ($testAuth) {
    assert(!empty($_SESSION['id_usuario']), 'Debe haber sesion antes del logout');
    $testAuth->logout();
    assert(empty($_SESSION), '$_SESSION debe quedar vacia tras logout');
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}

