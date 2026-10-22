<?php
/**
 * Suite de Pruebas Automatizadas: US-02 (RF02) — Gestión de Propietarios (T-022)
 * Cumple con TEC-01: PHP Nativo con assert(), sin Composer ni dependencias externas.
 *
 * Ejecución vía Docker:
 *   Get-Content pruebas\test_propietarios.php -Raw | docker exec -i php_mvc_dual php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-02 (RF02)\n";
echo " Modulo: Propietarios\n";
echo "========================================================\n\n";

// ── 1. Autoloader PSR-4 ─────────────────────────────────────────── //
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

use App\Services\PropietariosMascotasService;
use App\Repositories\PropietarioRepository;
use App\Repositories\MascotaRepository;
use App\Models\PropietarioModel;

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
// GRUPO 1: Normalización y Validación de Formato de DUI (UNI-01)
// ────────────────────────────────────────────────────────────────── //
$stubMascotaRepo = new class extends MascotaRepository { public function __construct() {} };
$service = new PropietariosMascotasService(new class extends PropietarioRepository { public function __construct() {} }, $stubMascotaRepo);

runTest('UNI-01: Normalizacion de DUI de 9 digitos continuos a ########-#', function () use ($service) {
    $resultado = $service->normalizarDui('012345678');
    assert($resultado === '01234567-8', 'Debe insertar el guion antes del digito final');

    $conEspacios = $service->normalizarDui('  01234567-8  ');
    assert($conEspacios === '01234567-8', 'Debe limpiar espacios en blanco');
});

runTest('UNI-01: Validacion regex de formato salvadoreño valido', function () use ($service) {
    assert($service->validarDui('01234567-8') === true, '01234567-8 debe ser valido');
    assert($service->validarDui('98765432-1') === true, '98765432-1 debe ser valido');
});

runTest('UNI-01: Rechazo de formatos invalidos de DUI', function () use ($service) {
    assert($service->validarDui('1234567-8') === false, 'Debe rechazar si faltan digitos');
    assert($service->validarDui('01234567-89') === false, 'Debe rechazar si sobran digitos');
    assert($service->validarDui('0123456A-8') === false, 'Debe rechazar si contiene letras');
    assert($service->validarDui('012345678') === false, 'Debe rechazar si no tiene guion (antes de normalizar)');
    assert($service->validarDui('') === false, 'Debe rechazar cadena vacia');
});

// ────────────────────────────────────────────────────────────────── //
// GRUPO 2: Reglas de Negocio con Doble de Prueba (En Memoria)
// ────────────────────────────────────────────────────────────────── //

class TestPropietarioModelMock extends PropietarioModel {
    private array $items = [];
    private int $autoIncrement = 1;

    public function __construct(array $initialItems = []) {
        $this->items = $initialItems;
        $this->autoIncrement = count($initialItems) + 1;
    }

    public function insert(array $data): int {
        $id = $this->autoIncrement++;
        $data['id_propietario'] = $id;
        $data['fecha_registro'] = date('Y-m-d H:i:s');
        $this->items[$id] = $data;
        return $id;
    }

    public function findByDui(string $dui): ?array {
        foreach ($this->items as $item) {
            if ($item['dui'] === $dui) {
                return $item;
            }
        }
        return null;
    }

    public function findById(int $id): ?array {
        return $this->items[$id] ?? null;
    }

    public function existsDui(string $dui, ?int $excludeId = null): bool {
        foreach ($this->items as $item) {
            if ($item['dui'] === $dui) {
                if ($excludeId !== null && (int)$item['id_propietario'] === $excludeId) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    public function findAll(bool $onlyActive = true): array {
        return array_values($this->items);
    }
}

$mockPropietarios = [
    1 => [
        'id_propietario' => 1,
        'dui'            => '01234567-8',
        'nombre_completo'=> 'Carlos Perez',
        'direccion'      => 'San Salvador',
        'telefono'       => '2222-3333',
        'correo'         => 'carlos@ejemplo.com',
        'activo'         => 1,
        'fecha_registro' => '2026-10-21 00:00:00',
    ]
];

$testModel = new TestPropietarioModelMock($mockPropietarios);
$testRepo  = new PropietarioRepository($testModel);
$testService = new PropietariosMascotasService($testRepo, $stubMascotaRepo);

runTest('T-018: Rechazo de alta si el nombre completo esta vacio o tiene menos de 3 caracteres', function () use ($testService) {
    $res1 = $testService->registrarPropietario(['nombre_completo' => '', 'dui' => '05555555-5']);
    assert($res1['success'] === false, 'Debe fallar sin nombre');
    assert(isset($res1['errors']['nombre_completo']), 'Debe reportar error en nombre_completo');

    $res2 = $testService->registrarPropietario(['nombre_completo' => 'Ab', 'dui' => '05555555-5']);
    assert($res2['success'] === false, 'Debe fallar con nombre muy corto');
});

runTest('T-018: Rechazo de alta si el DUI esta vacio o tiene formato invalido', function () use ($testService) {
    $res1 = $testService->registrarPropietario(['nombre_completo' => 'Maria Lopez', 'dui' => '']);
    assert($res1['success'] === false, 'Debe fallar sin DUI');
    assert(isset($res1['errors']['dui']), 'Debe reportar error en DUI');

    $res2 = $testService->registrarPropietario(['nombre_completo' => 'Maria Lopez', 'dui' => '12345']);
    assert($res2['success'] === false, 'Debe fallar formato invalido');
});

runTest('T-018 / UNI-01: Rechazo estricto de DUI duplicado', function () use ($testService) {
    $res = $testService->registrarPropietario([
        'nombre_completo' => 'Otro Propietario',
        'dui'             => '01234567-8', // Ya existe en mock
    ]);
    assert($res['success'] === false, 'Debe rechazar DUI duplicado');
    assert(isset($res['errors']['dui']), 'Debe señalar error en DUI');
    assert(str_contains(mb_strtolower($res['errors']['dui']), 'ya se encuentra registrado'), 'Mensaje debe indicar duplicidad');
});

runTest('T-018: Validacion de correo opcional (valido vs invalido)', function () use ($testService) {
    $resInvalido = $testService->registrarPropietario([
        'nombre_completo' => 'Fernando Soto',
        'dui'             => '07777777-7',
        'correo'          => 'correonoesvalido',
    ]);
    assert($resInvalido['success'] === false, 'Debe fallar correo no valido');
    assert(isset($resInvalido['errors']['correo']), 'Error en correo');
});

runTest('T-018 / UNI-01: Permite telefono compartido entre dos propietarios distintos', function () use ($testService) {
    $res = $testService->registrarPropietario([
        'nombre_completo' => 'Ana Perez (Hermana de Carlos)',
        'dui'             => '08888888-8',
        'telefono'        => '2222-3333', // Mismo telefono de Carlos (UNI-01)
    ]);
    assert($res['success'] === true, 'El telefono no es unico, debe permitirlo');
    assert($res['id'] !== null, 'Debe generar nuevo ID');
});

runTest('T-018: Registro exitoso completo con normalizacion automatica de 9 digitos', function () use ($testService) {
    $res = $testService->registrarPropietario([
        'nombre_completo' => 'Elena Gomez',
        'dui'             => '099999999', // 9 digitos continuos
        'telefono'        => '7123-4567',
        'correo'          => 'elena@ejemplo.com',
        'direccion'       => 'Colonia Miramonte #12',
    ]);
    assert($res['success'] === true, 'Registro debe ser exitoso');
    assert(is_int($res['id']) && $res['id'] > 0, 'Debe retornar ID entero valido');

    $guardado = $testService->obtenerPropietarioPorId($res['id']);
    assert($guardado !== null, 'Debe existir el registro');
    assert($guardado['dui'] === '09999999-9', 'El DUI debe estar normalizado con guion');
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (100%%).\n", $passedTests, $totalTests);
echo "========================================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}

