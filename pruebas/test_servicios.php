<?php
/**
 * Suite de Pruebas Automatizadas: US-10 (RF10) — Catálogo de Servicios (T-065 a T-069)
 * Cumple con TEC-01: PHP Nativo con assert(), sin Composer ni dependencias externas.
 *
 * Ejecución vía Docker:
 *   Get-Content pruebas\test_servicios.php -Raw | docker exec -i php_mvc_dual php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-10 (RF10)\n";
echo " Modulo: Catalogo de Servicios\n";
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

use App\Models\ServicioModel;
use App\Repositories\ServicioRepository;
use App\Services\CatalogoServiciosService;

$totalTests  = 0;
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
// Doble de Prueba en Memoria para ServicioModel
// ────────────────────────────────────────────────────────────────── //

class TestServicioModelMock extends ServicioModel {
    /** @var array<int, array<string,mixed>> */
    private array $items = [];
    private int $autoIncrement = 1;
    /** @var array<int, bool> */
    private array $conCitas = [];

    /**
     * @param array<int, array<string,mixed>> $initialItems
     * @param array<int, bool> $conCitas
     */
    public function __construct(array $initialItems = [], array $conCitas = []) {
        $this->items         = $initialItems;
        $this->autoIncrement = empty($initialItems) ? 1 : (max(array_keys($initialItems)) + 1);
        $this->conCitas      = $conCitas;
    }

    public function findAll(bool $soloActivos = true): array {
        $rows = array_values($this->items);
        if ($soloActivos) {
            $rows = array_values(array_filter($rows, static fn(array $r): bool => (int) $r['activo'] === 1));
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['nombre_servicio'], (string) $b['nombre_servicio']));
        return $rows;
    }

    public function findById(int $id): ?array {
        return $this->items[$id] ?? null;
    }

    public function findActivoById(int $id): ?array {
        $item = $this->items[$id] ?? null;
        if ($item && (int) $item['activo'] === 1) {
            return $item;
        }
        return null;
    }

    public function findByNombre(string $nombre, ?int $excludeId = null): ?array {
        foreach ($this->items as $item) {
            if (mb_strtolower((string) $item['nombre_servicio'], 'UTF-8') === mb_strtolower($nombre, 'UTF-8')) {
                if ($excludeId !== null && (int) $item['id_servicio'] === $excludeId) {
                    continue;
                }
                return $item;
            }
        }
        return null;
    }

    public function tieneCitasAsociadas(int $id): bool {
        return $this->conCitas[$id] ?? false;
    }

    public function insert(array $data): int {
        $id = $this->autoIncrement++;
        $this->items[$id] = [
            'id_servicio'           => $id,
            'nombre_servicio'       => $data['nombre_servicio'],
            'descripcion'           => $data['descripcion'] ?? null,
            'duracion_estimada_min' => (int) $data['duracion_estimada_min'],
            'activo'                => 1,
        ];
        return $id;
    }

    public function update(int $id, array $data): bool {
        if (!isset($this->items[$id])) {
            return false;
        }
        $this->items[$id]['nombre_servicio']       = $data['nombre_servicio'];
        $this->items[$id]['descripcion']           = $data['descripcion'] ?? null;
        $this->items[$id]['duracion_estimada_min'] = (int) $data['duracion_estimada_min'];
        return true;
    }

    public function desactivar(int $id): bool {
        if (!isset($this->items[$id]) || (int) $this->items[$id]['activo'] === 0) {
            return false;
        }
        $this->items[$id]['activo'] = 0;
        return true;
    }

    public function activar(int $id): bool {
        if (!isset($this->items[$id]) || (int) $this->items[$id]['activo'] === 1) {
            return false;
        }
        $this->items[$id]['activo'] = 1;
        return true;
    }
}

$mockServicios = [
    1 => [
        'id_servicio'           => 1,
        'nombre_servicio'       => 'Consulta General',
        'descripcion'           => 'Revisión clínica general del paciente.',
        'duracion_estimada_min' => 30,
        'activo'                => 1,
    ],
    2 => [
        'id_servicio'           => 2,
        'nombre_servicio'       => 'Vacunación',
        'descripcion'           => 'Aplicación de esquema de vacunas.',
        'duracion_estimada_min' => 20,
        'activo'                => 1,
    ],
];

$testModel   = new TestServicioModelMock($mockServicios, [1 => true]);
$testRepo    = new ServicioRepository($testModel);
$testService = new CatalogoServiciosService($testRepo);

// ────────────────────────────────────────────────────────────────── //
// Casos de Prueba (T-065 a T-069)
// ────────────────────────────────────────────────────────────────── //

runTest('T-067: Rechazo de alta cuando el nombre del servicio esta vacio', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => '   ',
        'descripcion'           => 'Servicio de prueba',
        'duracion_estimada_min' => '30',
    ]);
    assert($res['success'] === false, 'Debe rechazar nombre vacio');
    assert(isset($res['errors']['nombre_servicio']), 'Debe reportar error en nombre_servicio');
});

runTest('T-067: Rechazo de alta cuando el nombre tiene menos de 3 caracteres', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => 'Rx',
        'duracion_estimada_min' => '15',
    ]);
    assert($res['success'] === false, 'Debe rechazar nombre menor a 3 caracteres');
    assert(isset($res['errors']['nombre_servicio']), 'Debe reportar error en nombre_servicio');
});

runTest('T-067: Rechazo de duracion igual a cero (0)', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => 'Ecografia Abdominal',
        'duracion_estimada_min' => '0',
    ]);
    assert($res['success'] === false, 'Duracion 0 debe ser rechazada');
    assert(isset($res['errors']['duracion_estimada_min']), 'Debe reportar error en duracion_estimada_min');
});

runTest('T-067: Rechazo de duracion negativa', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => 'Ecografia Abdominal',
        'duracion_estimada_min' => '-15',
    ]);
    assert($res['success'] === false, 'Duracion negativa debe ser rechazada');
    assert(isset($res['errors']['duracion_estimada_min']), 'Debe reportar error en duracion_estimada_min');
});

runTest('T-067: Rechazo de duracion no numerica o vacia', function () use ($testService) {
    $res1 = $testService->registrarServicio([
        'nombre_servicio'       => 'Ecografia Abdominal',
        'duracion_estimada_min' => 'treinta',
    ]);
    assert($res1['success'] === false, 'Duracion alfabetica debe ser rechazada');
    assert(isset($res1['errors']['duracion_estimada_min']), 'Debe reportar error en duracion_estimada_min');

    $res2 = $testService->registrarServicio([
        'nombre_servicio'       => 'Ecografia Abdominal',
        'duracion_estimada_min' => '',
    ]);
    assert($res2['success'] === false, 'Duracion vacia debe ser rechazada');
});

runTest('T-067: Rechazo de nombre duplicado (case-insensitive)', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => 'consulta general',
        'duracion_estimada_min' => '45',
    ]);
    assert($res['success'] === false, 'Debe rechazar nombre duplicado');
    assert(isset($res['errors']['nombre_servicio']), 'Debe indicar error en nombre_servicio');
});

runTest('T-067: Registro exitoso de servicio completo con todos sus campos', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => 'Limpieza Dental Ultrasónica',
        'descripcion'           => 'Profilaxis dental completa bajo sedación ligera.',
        'duracion_estimada_min' => '60',
    ]);
    assert($res['success'] === true, 'Registro completo debe ser exitoso');
    assert(is_int($res['id']) && $res['id'] === 3, 'Debe asignar ID 3');

    $creado = $testService->obtenerServicioPorId(3);
    assert($creado !== null, 'El servicio creado debe poder consultarse');
    assert((int) $creado['duracion_estimada_min'] === 60, 'Duracion debe ser 60');
    assert((int) $creado['activo'] === 1, 'Debe crearse activo = 1 por defecto');
});

runTest('T-067: Registro exitoso omitiendo descripcion opcional', function () use ($testService) {
    $res = $testService->registrarServicio([
        'nombre_servicio'       => 'Desparasitación Interna',
        'descripcion'           => '',
        'duracion_estimada_min' => '15',
    ]);
    assert($res['success'] === true, 'Registro sin descripcion debe ser exitoso');
    $creado = $testService->obtenerServicioPorId($res['id']);
    assert($creado !== null && $creado['descripcion'] === null, 'Descripcion vacia se normaliza a null');
});

runTest('T-067: Actualizacion exitosa de un servicio existente', function () use ($testService) {
    $res = $testService->actualizarServicio(2, [
        'nombre_servicio'       => 'Vacunación Completa',
        'descripcion'           => 'Aplicación de vacuna múltiple y antirrábica.',
        'duracion_estimada_min' => '25',
    ]);
    assert($res['success'] === true, 'Actualizacion debe ser exitosa');

    $actualizado = $testService->obtenerServicioPorId(2);
    assert($actualizado['nombre_servicio'] === 'Vacunación Completa', 'Nombre actualizado');
    assert((int) $actualizado['duracion_estimada_min'] === 25, 'Duracion actualizada a 25');
});

runTest('T-067: Actualizacion rechazada si intenta usar el nombre de otro servicio', function () use ($testService) {
    $res = $testService->actualizarServicio(2, [
        'nombre_servicio'       => 'Consulta General', // ID 1 ya tiene este nombre
        'descripcion'           => 'Intento duplicado',
        'duracion_estimada_min' => '25',
    ]);
    assert($res['success'] === false, 'Debe rechazar duplicidad con otro servicio');
    assert(isset($res['errors']['nombre_servicio']), 'Debe reportar error en nombre_servicio');
});

runTest('T-067 / BAJA-01: Desactivacion logica conserva el registro historico pero lo oculta de activos', function () use ($testService, $testRepo) {
    // El servicio 1 tiene citas asociadas y se desactiva lógicamente sin borrarse
    assert($testRepo->tieneCitasAsociadas(1) === true, 'Servicio 1 tiene citas asociadas');

    $res = $testService->desactivarServicio(1);
    assert($res['success'] === true, 'Desactivacion debe ser exitosa');

    // Sigue existiendo por ID (para historial de citas)
    $historico = $testService->obtenerServicioPorId(1);
    assert($historico !== null, 'El registro no se elimina fisicamente (BAJA-01)');
    assert((int) $historico['activo'] === 0, 'Su estado pasa a activo = 0');

    // No aparece en listActivos() (usado por CitaService para agendar)
    $activos = $testRepo->listActivos();
    foreach ($activos as $a) {
        assert((int) $a['id_servicio'] !== 1, 'Servicio inactivo no debe ofrecerse en agendamiento');
    }

    // Pero sí aparece en listarServicios() del catálogo administrativo
    $todos = $testService->listarServicios();
    $idsTodos = array_map(static fn(array $s): int => (int) $s['id_servicio'], $todos);
    assert(in_array(1, $idsTodos, true), 'Debe seguir listándose en el catalogo admin');
});

runTest('T-067: Reactivacion de servicio previamente desactivado', function () use ($testService, $testRepo) {
    $res = $testService->activarServicio(1);
    assert($res['success'] === true, 'Reactivacion debe ser exitosa');

    $reactivado = $testRepo->findActivoById(1);
    assert($reactivado !== null && (int) $reactivado['activo'] === 1, 'Vuelve a estar activo para citas');
});

echo "\n--------------------------------------------------------\n";
echo sprintf(
    " Resumen: %d de %d pruebas superadas (%d%%).\n",
    $passedTests,
    $totalTests,
    $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0
);
echo "========================================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}
