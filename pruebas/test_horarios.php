<?php
/**
 * Suite de Pruebas Automatizadas: US-11 (RF11) — Gestión de Horarios de Atención (T-070 a T-075)
 * Cumple con TEC-01: PHP Nativo con assert(), sin frameworks ni dependencias externas.
 * Utiliza Dobles de Prueba en Memoria aislados para permitir ejecución offline/CLI instantánea.
 *
 * Ejecución:
 *   "C:\xampp\php\php.exe" pruebas/test_horarios.php
 *   o dentro de Docker:
 *   Get-Content pruebas\test_horarios.php -Raw | docker exec -i php_mvc_dual php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-11 (RF11)\n";
echo " Modulo: Horarios de Atencion (T-070 a T-075)\n";
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

use App\Models\HorarioAtencionModel;
use App\Models\VeterinarioModel;
use App\Models\CitaModel;
use App\Repositories\HorarioAtencionRepository;
use App\Repositories\VeterinarioRepository;
use App\Services\HorariosServicio;

// ── Dobles de Prueba en Memoria (Mocks) ────────────────────────── //

class TestHorarioAtencionModelMock extends HorarioAtencionModel {
    private array $items = [];
    private int $autoIncrement = 1;

    public function __construct(array $initial = []) {
        $this->items = $initial;
        $this->autoIncrement = empty($initial) ? 1 : (max(array_keys($initial)) + 1);
    }

    public function findByVeterinarioYDia(int $idVeterinario, int $diaSemana): array {
        $res = [];
        foreach ($this->items as $h) {
            if ((int)$h['id_veterinario'] === $idVeterinario && (int)$h['dia_semana'] === $diaSemana) {
                $res[] = $h;
            }
        }
        usort($res, fn($a, $b) => strcmp($a['hora_inicio'], $b['hora_inicio']));
        return $res;
    }

    public function findAll(): array {
        $res = array_values($this->items);
        usort($res, fn($a, $b) => $a['dia_semana'] <=> $b['dia_semana'] ?: strcmp($a['hora_inicio'], $b['hora_inicio']));
        return $res;
    }

    public function findByVeterinario(int $idVeterinario): array {
        $res = [];
        foreach ($this->items as $h) {
            if ((int)$h['id_veterinario'] === $idVeterinario) {
                $res[] = $h;
            }
        }
        usort($res, fn($a, $b) => $a['dia_semana'] <=> $b['dia_semana'] ?: strcmp($a['hora_inicio'], $b['hora_inicio']));
        return $res;
    }

    public function findById(int $idHorario): ?array {
        return $this->items[$idHorario] ?? null;
    }

    public function findSolapadas(int $idVeterinario, int $diaSemana, string $horaInicio, string $horaFin, ?int $excluirIdHorario = null): array {
        $solapadas = [];
        foreach ($this->items as $h) {
            if ((int)$h['id_veterinario'] !== $idVeterinario || (int)$h['dia_semana'] !== $diaSemana) {
                continue;
            }
            if ($excluirIdHorario !== null && (int)$h['id_horario'] === $excluirIdHorario) {
                continue;
            }
            // Condición estricta: hora_inicio < nueva_fin && hora_fin > nueva_inicio
            if ($h['hora_inicio'] < $horaFin && $h['hora_fin'] > $horaInicio) {
                $solapadas[] = $h;
            }
        }
        return $solapadas;
    }

    public function insert(int $idVeterinario, int $diaSemana, string $horaInicio, string $horaFin): int {
        $id = $this->autoIncrement++;
        $this->items[$id] = [
            'id_horario'         => $id,
            'id_veterinario'     => $idVeterinario,
            'dia_semana'         => $diaSemana,
            'hora_inicio'        => $horaInicio,
            'hora_fin'           => $horaFin,
            'veterinario_nombre' => 'Dr. Prueba',
            'especialidad'       => 'Cirugía',
        ];
        return $id;
    }

    public function update(int $idHorario, int $diaSemana, string $horaInicio, string $horaFin): bool {
        if (!isset($this->items[$idHorario])) return false;
        $this->items[$idHorario]['dia_semana']  = $diaSemana;
        $this->items[$idHorario]['hora_inicio'] = $horaInicio;
        $this->items[$idHorario]['hora_fin']    = $horaFin;
        return true;
    }

    public function delete(int $idHorario): bool {
        if (isset($this->items[$idHorario])) {
            unset($this->items[$idHorario]);
            return true;
        }
        return false;
    }
}

class TestVeterinarioModelMock extends VeterinarioModel {
    private array $vets = [];

    public function __construct(array $vets = []) {
        $this->vets = $vets;
    }

    public function findActivoById(int $id): ?array {
        return $this->vets[$id] ?? null;
    }

    public function findAllActivos(): array {
        return array_values($this->vets);
    }
}

class TestCitaModelMock extends CitaModel {
    private array $citas = [];

    public function __construct(array $citas = []) {
        $this->citas = $citas;
    }

    public function findProgramadasFuturasByVeterinario(int $idVeterinario, ?string $fechaDesde = null): array {
        $res = [];
        foreach ($this->citas as $c) {
            if ((int)$c['id_veterinario'] === $idVeterinario && $c['estado'] === self::ESTADO_PROGRAMADA) {
                $res[] = $c;
            }
        }
        return $res;
    }
}

// ── Runner de Pruebas ───────────────────────────────────────────── //

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
        echo "FAIL\n";
        echo "     Error: " . $e->getMessage() . "\n";
        echo "     Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    }
}

// Configuración inicial de Dobles de Prueba
$vetData = [
    1 => ['id_veterinario' => 1, 'nombre' => 'Dra. María Elena Rodríguez', 'especialidad' => 'Medicina Felina', 'activo' => 1],
    2 => ['id_veterinario' => 2, 'nombre' => 'Dr. Carlos Mendoza', 'especialidad' => 'Cirugía y Ortopedia', 'activo' => 1],
];

$vetModelMock  = new TestVeterinarioModelMock($vetData);
$vetRepo       = new VeterinarioRepository($vetModelMock);

$horarioModelMock = new TestHorarioAtencionModelMock([
    1 => ['id_horario' => 1, 'id_veterinario' => 1, 'dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00', 'veterinario_nombre' => 'Dra. María Elena Rodríguez', 'especialidad' => 'Medicina Felina'],
    2 => ['id_horario' => 2, 'id_veterinario' => 1, 'dia_semana' => 1, 'hora_inicio' => '14:00:00', 'hora_fin' => '18:00:00', 'veterinario_nombre' => 'Dra. María Elena Rodríguez', 'especialidad' => 'Medicina Felina'],
]);
$horarioRepo = new HorarioAtencionRepository($horarioModelMock);

$citaModelMock = new TestCitaModelMock([
    101 => [
        'id_cita'               => 101,
        'id_veterinario'        => 1,
        'fecha_cita'            => '2026-11-02', // Lunes (Día 1)
        'hora_cita'             => '10:00:00',
        'duracion_estimada_min' => 30,
        'estado'                => 'Programada',
        'mascota_nombre'        => 'Michi',
        'servicio_nombre'       => 'Consulta General',
    ]
]);

$service = new HorariosServicio($horarioRepo, $vetRepo, $citaModelMock);

// ── Test 1: Validación - Rechazar día de la semana fuera de rango (< 1) ── //
runTest("T-072: Rechazar dia_semana < 1 (fuera de rango)", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 1,
        'dia_semana'     => 0,
        'hora_inicio'    => '08:00',
        'hora_fin'       => '12:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar día 0");
    assert(isset($res['field_errors']['dia_semana']), "Debe marcar error en dia_semana");
});

// ── Test 2: Validación - Rechazar día de la semana fuera de rango (> 7) ── //
runTest("T-072: Rechazar dia_semana > 7 (fuera de rango)", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 1,
        'dia_semana'     => 8,
        'hora_inicio'    => '08:00',
        'hora_fin'       => '12:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar día 8");
    assert(isset($res['field_errors']['dia_semana']), "Debe marcar error en dia_semana");
});

// ── Test 3: Validación - Rechazar hora_inicio igual a hora_fin ── //
runTest("T-070/T-072: Rechazar hora_inicio igual a hora_fin", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 1,
        'dia_semana'     => 1,
        'hora_inicio'    => '10:00',
        'hora_fin'       => '10:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar horas iguales");
    assert(isset($res['field_errors']['hora_inicio']), "Debe señalar que inicio debe preceder a fin");
});

// ── Test 4: Validación - Rechazar hora_inicio posterior a hora_fin ── //
runTest("T-070/T-072: Rechazar hora_inicio posterior a hora_fin", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 1,
        'dia_semana'     => 1,
        'hora_inicio'    => '14:00',
        'hora_fin'       => '09:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar inicio > fin");
    assert(isset($res['field_errors']['hora_inicio']), "Debe reportar error en hora_inicio");
});

// ── Test 5: Validación - Rechazar franja menor a 15 minutos ── //
runTest("T-072: Rechazar franja con duracion menor a 15 minutos", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 1,
        'dia_semana'     => 1,
        'hora_inicio'    => '08:00',
        'hora_fin'       => '08:10',
    ]);
    assert($res['ok'] === false, "Debe rechazar franja de 10 min");
    assert(isset($res['field_errors']['hora_fin']), "Debe reportar error de duración mínima");
});

// ── Test 6: Validación - Formatos de hora inválidos ── //
runTest("T-072: Rechazar cadenas de hora mal formadas", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 1,
        'dia_semana'     => 2,
        'hora_inicio'    => '25:70',
        'hora_fin'       => '12:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar 25:70");
});

// ── Test 7: Validación - Veterinario inexistente ── //
runTest("T-071/T-072: Rechazar veterinario inexistente o no numerico", function() use ($service) {
    $res = $service->validarDatos([
        'id_veterinario' => 999999,
        'dia_semana'     => 3,
        'hora_inicio'    => '08:00',
        'hora_fin'       => '12:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar id de veterinario no existente");
    assert(isset($res['field_errors']['id_veterinario']));
});

// ── Test 8: Creación exitosa de franja válida ── //
runTest("T-070/T-072: Creación exitosa de franja horaria válida", function() use ($service) {
    $res = $service->crearHorario([
        'id_veterinario' => 2,
        'dia_semana'     => 2, // Martes
        'hora_inicio'    => '09:00',
        'hora_fin'       => '13:00',
    ]);
    assert($res['ok'] === true, "La franja debe crearse con éxito");
    assert(isset($res['id_horario']) && $res['id_horario'] > 0);
});

// ── Test 9: Rechazo de solapamiento con franja existente (mismo veterinario y día) ── //
runTest("T-072: Rechazar franja que se solapa con una existente", function() use ($service) {
    // Vet 1 ya tiene franja 08:00 a 12:00 el día 1
    $res = $service->crearHorario([
        'id_veterinario' => 1,
        'dia_semana'     => 1,
        'hora_inicio'    => '11:00',
        'hora_fin'       => '15:00',
    ]);
    assert($res['ok'] === false, "Debe rechazar solapamiento");
    assert(strpos($res['error'], 'solapa') !== false, "Mensaje debe mencionar solapamiento");
});

// ── Test 10: Aceptar franja contigua (adyacente sin sobreponer) ── //
runTest("T-072: Permitir franjas contiguas (ej. 12:00 a 14:00)", function() use ($service) {
    // Vet 1 tiene 08:00-12:00 y 14:00-18:00. 12:00 a 14:00 cabe exactamente en medio sin solapar.
    $res = $service->crearHorario([
        'id_veterinario' => 1,
        'dia_semana'     => 1,
        'hora_inicio'    => '12:00',
        'hora_fin'       => '14:00',
    ]);
    assert($res['ok'] === true, "Franja adyacente debe ser permitida");
});

// ── Test 11: Bloquear modificación si afecta citas programadas futuras ── //
runTest("T-072: Bloquear actualización si deja citas futuras fuera de horario (CIT-01)", function() use ($service) {
    // El horario id=1 es del Vet 1 (Día 1, 08:00-12:00). Hay una cita a las 10:00 (id=101).
    // Si intentamos reducir el horario a 08:00-09:30, la cita de las 10:00 queda fuera.
    $res = $service->actualizarHorario(1, [
        'dia_semana'  => 1,
        'hora_inicio' => '08:00',
        'hora_fin'    => '09:30',
    ]);
    assert($res['ok'] === false, "Debe bloquear la modificación");
    assert(!empty($res['citas_afectadas']), "Debe reportar las citas afectadas");
    assert(strpos($res['error'], 'citas futuras') !== false);
});

// ── Test 12: Bloquear eliminación si hay citas programadas futuras ── //
runTest("T-072: Bloquear eliminación si existen citas programadas (CIT-01, BAJA-01)", function() use ($service) {
    // Intentar eliminar la franja id=1 donde existe la cita #101
    $res = $service->eliminarHorario(1);
    assert($res['ok'] === false, "Debe bloquear la eliminación");
    assert(!empty($res['citas_afectadas']), "Debe listar la cita que impide borrar");
});

// ── Test 13: Permitir eliminación cuando no hay citas afectadas ── //
runTest("T-072: Permitir eliminación de franja libre de citas", function() use ($service) {
    // La franja id=2 (14:00-18:00) no tiene citas programadas
    $res = $service->eliminarHorario(2);
    assert($res['ok'] === true, "Debe permitir eliminar la franja sin citas");
});

// ── Test 14: Mapeo completo de nombres de días 1 a 7 ── //
runTest("T-074: Días de la semana indexados 1 a 7", function() {
    assert(HorariosServicio::DIAS_SEMANA[1] === 'Lunes');
    assert(HorariosServicio::DIAS_SEMANA[7] === 'Domingo');
    assert(count(HorariosServicio::DIAS_SEMANA) === 7);
});

// ── Resumen ─────────────────────────────────────────────────────── //
echo "\n--------------------------------------------------------\n";
echo sprintf("Resultado final: %d/%d pruebas superadas (100%%).\n", $passedTests, $totalTests);
echo "--------------------------------------------------------\n";

if ($passedTests !== $totalTests) {
    exit(1);
}
