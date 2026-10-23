<?php
/**
 * Suite de Pruebas Automatizadas: US-03 (RF03) — Gestión y Registro de Mascotas
 * Cumple con TEC-01: PHP Nativo con assert(), sin Composer ni dependencias externas.
 * Valida reglas: ARQ-01, DAT-01, SEC-01, BAJA-01, UI-01
 *
 * Ejecución local:
 *   php pruebas/test_mascotas.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-03 (RF03)\n";
echo " Modulo: Mascotas\n";
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
use App\Models\MascotaModel;

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
// Dobles de Prueba en Memoria (Mocks) para Aislamiento de Base de Datos
// ────────────────────────────────────────────────────────────────── //

class TestPropietarioModelMockUS3 extends PropietarioModel {
    private array $propietarios;

    public function __construct(array $propietarios) {
        $this->propietarios = $propietarios;
    }

    public function findById(int $id): ?array {
        return $this->propietarios[$id] ?? null;
    }

    public function findAll(bool $onlyActive = true): array {
        if (!$onlyActive) {
            return array_values($this->propietarios);
        }
        return array_values(array_filter($this->propietarios, fn($p) => (int)$p['activo'] === 1));
    }
}

class TestMascotaModelMockUS3 extends MascotaModel {
    public array $items = [];
    private int $autoIncrement = 1;

    public function __construct(array $initial = []) {
        $this->items = $initial;
        $this->autoIncrement = count($initial) + 1;
    }

    public function insert(array $data): int {
        $id = $this->autoIncrement++;
        $data['id_mascota'] = $id;
        $data['fecha_registro'] = date('Y-m-d H:i:s');
        $this->items[$id] = $data;
        return $id;
    }

    public function findById(int $id): ?array {
        return $this->items[$id] ?? null;
    }

    public function findAll(bool $onlyActive = true): array {
        if (!$onlyActive) {
            return array_values($this->items);
        }
        return array_values(array_filter($this->items, fn($m) => (int)($m['activo'] ?? 1) === 1));
    }

    public function findByPropietarioId(int $idPropietario, bool $onlyActive = true): array {
        $res = [];
        foreach ($this->items as $m) {
            if ((int)$m['id_propietario'] === $idPropietario) {
                if (!$onlyActive || (int)($m['activo'] ?? 1) === 1) {
                    $res[] = $m;
                }
            }
        }
        return $res;
    }

    public function countActive(): int {
        return count(array_filter($this->items, fn($m) => (int)($m['activo'] ?? 1) === 1));
    }
}

// Datos de prueba de propietarios
$mockPropietarios = [
    1 => [
        'id_propietario' => 1,
        'dui'            => '01234567-8',
        'nombre_completo'=> 'Ana Martinez',
        'activo'         => 1,
    ],
    2 => [
        'id_propietario' => 2,
        'dui'            => '09876543-2',
        'nombre_completo'=> 'Roberto Duran (Inactivo)',
        'activo'         => 0, // Propietario dado de baja
    ],
];

$propModelMock = new TestPropietarioModelMockUS3($mockPropietarios);
$propRepoMock  = new PropietarioRepository($propModelMock);

$mascotaModelMock = new TestMascotaModelMockUS3();
$mascotaRepoMock  = new MascotaRepository($mascotaModelMock);

$service = new PropietariosMascotasService($propRepoMock, $mascotaRepoMock);

// ────────────────────────────────────────────────────────────────── //
// GRUPO 1: Integridad Referencial de Propietario (DAT-01)
// ────────────────────────────────────────────────────────────────── //

runTest('DAT-01: Rechazo de registro de mascota sin seleccionar propietario', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario' => '',
        'nombre'         => 'Firulais',
        'especie'        => 'Canino',
        'raza'           => 'Mestizo',
        'sexo'           => 'Macho',
    ]);
    assert($res['success'] === false, 'Debe fallar si no hay propietario');
    assert(isset($res['errors']['id_propietario']), 'Debe reportar error en id_propietario');
});

runTest('DAT-01: Rechazo si el id_propietario no existe en el sistema', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario' => 99999, // Inexistente
        'nombre'         => 'Firulais',
        'especie'        => 'Canino',
        'raza'           => 'Mestizo',
        'sexo'           => 'Macho',
    ]);
    assert($res['success'] === false, 'Debe fallar con propietario inexistente');
    assert(str_contains($res['errors']['id_propietario'], 'no existe'), 'El mensaje debe indicar no existencia');
});

runTest('DAT-01 / BAJA-01: Rechazo si el propietario esta inactivo', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario' => 2, // Propietario inactivo
        'nombre'         => 'Pelusa',
        'especie'        => 'Felino',
        'raza'           => 'Angora',
        'sexo'           => 'Hembra',
    ]);
    assert($res['success'] === false, 'No debe permitir asociar mascota a propietario inactivo');
    assert(str_contains($res['errors']['id_propietario'], 'inactivo'), 'El mensaje debe indicar inactividad');
});

// ────────────────────────────────────────────────────────────────── //
// GRUPO 2: Validaciones de Campos Obligatorios (Nombre, Especie, Raza, Sexo)
// ────────────────────────────────────────────────────────────────── //

runTest('US-03: Rechazo si el nombre de la mascota esta vacio o es menor a 2 caracteres', function () use ($service) {
    $res1 = $service->registrarMascota([
        'id_propietario' => 1,
        'nombre'         => '',
        'especie'        => 'Canino',
        'raza'           => 'Labrador',
        'sexo'           => 'Macho',
    ]);
    assert($res1['success'] === false, 'Debe fallar sin nombre');
    assert(isset($res1['errors']['nombre']), 'Error en nombre');

    $res2 = $service->registrarMascota([
        'id_propietario' => 1,
        'nombre'         => 'A',
        'especie'        => 'Canino',
        'raza'           => 'Labrador',
        'sexo'           => 'Macho',
    ]);
    assert($res2['success'] === false, 'Debe fallar nombre con 1 caracter');
});

runTest('US-03: Rechazo si la especie esta vacia', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario' => 1,
        'nombre'         => 'Max',
        'especie'        => '   ',
        'raza'           => 'Poodle',
        'sexo'           => 'Macho',
    ]);
    assert($res['success'] === false, 'Debe fallar sin especie');
    assert(isset($res['errors']['especie']), 'Error en especie');
});

runTest('US-03: Rechazo si la raza esta vacia', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario' => 1,
        'nombre'         => 'Max',
        'especie'        => 'Canino',
        'raza'           => '',
        'sexo'           => 'Macho',
    ]);
    assert($res['success'] === false, 'Debe fallar sin raza');
    assert(isset($res['errors']['raza']), 'Error en raza');
});

runTest('US-03: Validacion estricta del sexo (solo Macho o Hembra)', function () use ($service) {
    $resInvalido = $service->registrarMascota([
        'id_propietario' => 1,
        'nombre'         => 'Max',
        'especie'        => 'Canino',
        'raza'           => 'Husky',
        'sexo'           => 'Indeterminado',
    ]);
    assert($resInvalido['success'] === false, 'Debe rechazar sexo invalido');
    assert(isset($resInvalido['errors']['sexo']), 'Error en sexo');
});

// ────────────────────────────────────────────────────────────────── //
// GRUPO 3: Fecha de Nacimiento (Opcional, formato y no futura)
// ────────────────────────────────────────────────────────────────── //

runTest('US-03: Rechazo de fecha de nacimiento con formato incorrecto o no valida en calendario', function () use ($service) {
    $resFormato = $service->registrarMascota([
        'id_propietario'   => 1,
        'nombre'           => 'Bobi',
        'especie'          => 'Canino',
        'raza'             => 'Chihuahua',
        'sexo'             => 'Macho',
        'fecha_nacimiento' => '21/10/2023', // Formato no ISO
    ]);
    assert($resFormato['success'] === false, 'Debe fallar formato incorrecto');
    assert(isset($resFormato['errors']['fecha_nacimiento']), 'Error en fecha_nacimiento');

    $resDia = $service->registrarMascota([
        'id_propietario'   => 1,
        'nombre'           => 'Bobi',
        'especie'          => 'Canino',
        'raza'             => 'Chihuahua',
        'sexo'             => 'Macho',
        'fecha_nacimiento' => '2023-02-31', // Dia no existe en febrero
    ]);
    assert($resDia['success'] === false, 'Debe fallar dia de calendario invalido');
});

runTest('US-03: Rechazo si la fecha de nacimiento es futura', function () use ($service) {
    $fechaFutura = date('Y-m-d', strtotime('+3 days'));
    $res = $service->registrarMascota([
        'id_propietario'   => 1,
        'nombre'           => 'Bobi',
        'especie'          => 'Canino',
        'raza'             => 'Chihuahua',
        'sexo'             => 'Macho',
        'fecha_nacimiento' => $fechaFutura,
    ]);
    assert($res['success'] === false, 'Debe fallar fecha futura');
    assert(str_contains($res['errors']['fecha_nacimiento'], 'posterior a la fecha actual'), 'Mensaje de fecha futura');
});

// ────────────────────────────────────────────────────────────────── //
// GRUPO 4: Registro Exitoso y Disponibilidad para Citas
// ────────────────────────────────────────────────────────────────── //

runTest('US-03 / DAT-01: Registro exitoso completo con datos opcionales', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario'             => 1,
        'nombre'                     => 'Rocky Balboa',
        'especie'                    => 'Canino',
        'raza'                       => 'Bulldog Francés',
        'sexo'                       => 'Macho',
        'fecha_nacimiento'           => '2022-05-15',
        'caracteristicas_relevantes' => 'Color atigrado con mancha blanca en pecho, dócil con niños.',
    ]);

    assert($res['success'] === true, 'El registro debe ser exitoso');
    assert(is_int($res['id']) && $res['id'] > 0, 'Debe devolver un ID generado');
    assert(str_contains($res['message'], 'disponible para citas'), 'Confirmación debe señalar disponibilidad para citas');

    $mascota = $service->obtenerMascotaPorId($res['id']);
    assert($mascota !== null, 'Debe consultarse la mascota registrada');
    assert($mascota['nombre'] === 'Rocky Balboa', 'El nombre debe coincidir');
    assert($mascota['sexo'] === 'Macho', 'El sexo debe coincidir');
    assert($mascota['caracteristicas_relevantes'] !== null, 'Debe persistir características');
});

runTest('US-03: Registro exitoso con campos opcionales omitidos (vacios)', function () use ($service) {
    $res = $service->registrarMascota([
        'id_propietario'             => 1,
        'nombre'                     => 'Mimi',
        'especie'                    => 'Felino',
        'raza'                       => 'Siamés',
        'sexo'                       => 'Hembra',
        'fecha_nacimiento'           => '',
        'caracteristicas_relevantes' => '',
    ]);

    assert($res['success'] === true, 'Debe permitir registro con opcionales vacios');
    $m = $service->obtenerMascotaPorId($res['id']);
    assert($m['fecha_nacimiento'] === null, 'Fecha de nacimiento debe ser null');
    assert($m['caracteristicas_relevantes'] === null, 'Caracteristicas debe ser null');
});

runTest('US-03 / ARQ-01: Listado de mascotas asociadas a un propietario especifico', function () use ($service) {
    $mascotasProp1 = $service->listarMascotasPorPropietario(1);
    assert(count($mascotasProp1) >= 2, 'Ana Martinez debe tener al menos 2 mascotas registradas');

    $mascotasProp2 = $service->listarMascotasPorPropietario(2);
    assert(count($mascotasProp2) === 0, 'El propietario 2 no debe tener mascotas');
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}
