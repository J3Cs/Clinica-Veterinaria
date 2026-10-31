<?php
/**
 * Suite de Pruebas Automatizadas: US-04 (RF04) — Agendamiento de Citas (T-036)
 * Cumple con TEC-01: PHP nativo con assert(), sin Composer ni dependencias externas.
 * Valida reglas: ARQ-01, DAT-01, SEC-01, ACC-01, UI-01
 *
 * No necesita base de datos (dobles en memoria). Para probar contra MySQL real,
 * incluida la concurrencia, ver test_citas_integracion.php.
 *
 * Ejecución local:
 *   php -d zend.assertions=1 pruebas/test_citas.php
 */

declare(strict_types=1);

ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-04 (RF04)\n";
echo " Modulo: Agendamiento de citas\n";
echo "========================================================\n\n";

// ── 1. Autoloader PSR-4 ─────────────────────────────────────────── //
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relativeFile = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    foreach ([__DIR__ . '/../src/app/', '/var/www/html/app/', __DIR__ . '/../app/'] as $dir) {
        if (file_exists($dir . $relativeFile)) {
            require_once $dir . $relativeFile;
            return;
        }
    }
});

use App\Models\CitaModel;
use App\Models\HorarioAtencionModel;
use App\Models\MascotaModel;
use App\Models\ServicioModel;
use App\Models\VeterinarioModel;
use App\Repositories\CitaRepository;
use App\Repositories\HorarioAtencionRepository;
use App\Repositories\MascotaRepository;
use App\Repositories\ServicioRepository;
use App\Repositories\VeterinarioRepository;
use App\Services\CitaService;

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
// Dobles de prueba en memoria
// ────────────────────────────────────────────────────────────────── //

/** Registro compartido de eventos para comprobar el orden transaccional. */
class TestLogUS4 {
    public static array $eventos = [];
}

class TestCitaModelMockUS4 extends CitaModel {
    public array $items = [];
    private int $autoIncrement = 1;

    public function __construct(private array $servicios) {}

    public function insert(array $data): int {
        TestLogUS4::$eventos[] = 'INSERT';
        $estado = $data['estado'] ?? 'Programada';
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de cita no válido');
        }
        $id = $this->autoIncrement++;
        $this->items[$id] = $data + ['id_cita' => $id, 'estado' => $estado, 'motivo_cancelacion' => null];
        $this->items[$id]['estado'] = $estado;
        $this->items[$id]['id_cita'] = $id;
        return $id;
    }

    public function findById(int $id): ?array {
        return $this->items[$id] ?? null;
    }

    public function findByVeterinarioFecha(int $idVeterinario, string $fecha, ?array $estados = null, bool $bloquear = false): array {
        if ($bloquear) {
            TestLogUS4::$eventos[] = 'READ_LOCK';
        }
        $res = [];
        foreach ($this->items as $c) {
            if ((int) $c['id_veterinario'] !== $idVeterinario || $c['fecha_cita'] !== $fecha) {
                continue;
            }
            if ($estados !== null && !in_array($c['estado'], $estados, true)) {
                continue;
            }
            $c['duracion_estimada_min'] = $this->servicios[$c['id_servicio']]['duracion_estimada_min'];
            $res[] = $c;
        }
        usort($res, fn($a, $b) => strcmp((string) $a['hora_cita'], (string) $b['hora_cita']));
        return $res;
    }

    /** Gancho para simular que otra persona cambia la cita entre la lectura y el UPDATE. */
    public ?Closure $antesDeCancelar = null;

    private function conDetalle(array $c): array {
        $srv = $this->servicios[$c['id_servicio']];
        return $c + [
            'mascota_nombre' => 'Rocky', 'mascota_especie' => 'Perro', 'propietario_nombre' => 'Ana',
            'nombre_servicio' => $srv['nombre_servicio'], 'duracion_estimada_min' => $srv['duracion_estimada_min'],
            'veterinario_nombre' => 'Dra. Laura', 'motivo_cancelacion' => $c['motivo_cancelacion'] ?? null,
        ];
    }

    public function findDetalleById(int $id): ?array {
        return isset($this->items[$id]) ? $this->conDetalle($this->items[$id]) : null;
    }

    public function findAllDetalle(array|string|null $filtrosOEstado = null, int $limite = 200, ?int $idVeterinario = null): array {
        $estado     = null;
        $fechaDesde = null;
        $fechaHasta = null;
        $idVet      = $idVeterinario;
        $termino    = null;

        if (is_array($filtrosOEstado)) {
            $estado     = $filtrosOEstado['estado'] ?? null;
            $fechaDesde = $filtrosOEstado['fecha_desde'] ?? null;
            $fechaHasta = $filtrosOEstado['fecha_hasta'] ?? null;
            $idVet      = $filtrosOEstado['id_veterinario'] ?? $idVeterinario;
            $termino    = $filtrosOEstado['q'] ?? ($filtrosOEstado['termino'] ?? null);
            if (isset($filtrosOEstado['limite']) && is_numeric($filtrosOEstado['limite'])) {
                $limite = (int) $filtrosOEstado['limite'];
            }
        } elseif (is_string($filtrosOEstado) && $filtrosOEstado !== '') {
            $estado = $filtrosOEstado;
        }

        $res = array_filter($this->items, function ($c) use ($estado, $fechaDesde, $fechaHasta, $idVet, $termino) {
            if ($estado !== null && $estado !== '' && $estado !== 'Todas' && $c['estado'] !== $estado) {
                return false;
            }
            if ($fechaDesde !== null && $fechaDesde !== '' && $c['fecha_cita'] < $fechaDesde) {
                return false;
            }
            if ($fechaHasta !== null && $fechaHasta !== '' && $c['fecha_cita'] > $fechaHasta) {
                return false;
            }
            if ($idVet !== null && $idVet !== '' && (int) $idVet > 0 && (int) $c['id_veterinario'] !== (int) $idVet) {
                return false;
            }
            if ($termino !== null && is_string($termino) && trim($termino) !== '') {
                $q = mb_strtolower(trim($termino), 'UTF-8');
                $d = $this->conDetalle($c);
                $txt = mb_strtolower(
                    ($d['mascota_nombre'] ?? '') . ' ' .
                    ($d['propietario_nombre'] ?? '') . ' ' .
                    ($d['nombre_servicio'] ?? '') . ' ' .
                    ($d['veterinario_nombre'] ?? ''),
                    'UTF-8'
                );
                if (!str_contains($txt, $q)) {
                    return false;
                }
            }
            return true;
        });

        usort($res, fn($a, $b) => [$a['fecha_cita'], $a['hora_cita'], $a['id_cita']] <=> [$b['fecha_cita'], $b['hora_cita'], $b['id_cita']]);
        return array_map(fn($c) => $this->conDetalle($c), array_slice($res, 0, $limite));
    }

    public function cancelarSiProgramada(int $id, ?string $motivo): int {
        TestLogUS4::$eventos[] = 'CANCEL';
        if ($this->antesDeCancelar) {
            ($this->antesDeCancelar)($this);
        }
        $c = $this->items[$id] ?? null;
        if (!$c || $c['estado'] !== 'Programada') {
            return 0;
        }
        $this->items[$id]['estado'] = 'Cancelada';
        $this->items[$id]['motivo_cancelacion'] = $motivo;
        return 1;
    }

    public function transaccion(callable $fn): mixed {
        TestLogUS4::$eventos[] = 'BEGIN';
        try {
            $r = $fn();
            TestLogUS4::$eventos[] = 'COMMIT';
            return $r;
        } catch (\Throwable $e) {
            TestLogUS4::$eventos[] = 'ROLLBACK';
            throw $e;
        }
    }

    public function sembrar(int $idMascota, int $idVet, int $idServicio, string $fecha, string $hora, string $estado = 'Programada'): void {
        $this->insert([
            'id_mascota' => $idMascota, 'id_veterinario' => $idVet, 'id_servicio' => $idServicio,
            'fecha_cita' => $fecha, 'hora_cita' => $hora, 'estado' => $estado,
        ]);
        TestLogUS4::$eventos = [];
    }
}

class TestServicioModelMockUS4 extends ServicioModel {
    public function __construct(private array $servicios) {}
    public function findAllActivos(): array {
        return array_values(array_filter($this->servicios, fn($s) => (int) $s['activo'] === 1));
    }
    public function findActivoById(int $id): ?array {
        $s = $this->servicios[$id] ?? null;
        return ($s && (int) $s['activo'] === 1) ? $s : null;
    }
}

class TestVeterinarioModelMockUS4 extends VeterinarioModel {
    public function __construct(private array $vets) {}
    public function findAllActivos(): array {
        return array_values(array_filter($this->vets, fn($v) => (int) $v['activo'] === 1));
    }
    public function findActivoById(int $id): ?array {
        $v = $this->vets[$id] ?? null;
        return ($v && (int) $v['activo'] === 1) ? $v : null;
    }
    public function bloquearActivoPorId(int $id): bool {
        TestLogUS4::$eventos[] = 'LOCK';
        return $this->findActivoById($id) !== null;
    }
}

class TestHorarioModelMockUS4 extends HorarioAtencionModel {
    public function __construct(private array $horarios) {}
    public function findByVeterinarioYDia(int $idVeterinario, int $diaSemana): array {
        return $this->horarios[$idVeterinario][$diaSemana] ?? [];
    }
}

class TestMascotaModelMockUS4 extends MascotaModel {
    public function __construct(private array $mascotas) {}
    public function findById(int $id): ?array { return $this->mascotas[$id] ?? null; }
    public function findAll(bool $onlyActive = true): array {
        return array_values(array_filter(
            $this->mascotas,
            fn($m) => !$onlyActive || ((int) $m['activo'] === 1 && (int) $m['propietario_activo'] === 1)
        ));
    }
}

// ────────────────────────────────────────────────────────────────── //
// Escenario
// ────────────────────────────────────────────────────────────────── //

// Un lunes futuro (dia_semana = 1) y el domingo anterior como "ahora".
$LUNES   = (new DateTimeImmutable('first monday of march 2030'));
$FECHA   = $LUNES->format('Y-m-d');
$SABADO  = $LUNES->modify('+5 days')->format('Y-m-d');
$AHORA   = $LUNES->modify('-1 day')->setTime(10, 0);   // domingo anterior

const SERV_CONSULTA = 1; // 30 min
const SERV_CIRUGIA  = 2; // 60 min
const SERV_INACTIVO = 3;
const VET_1 = 10;        // lunes 08:00-12:00
const VET_2 = 11;        // lunes 08:00-12:00 y 14:00-17:00
const VET_INACTIVO = 12;
const MASC_OK = 1;
const MASC_INACTIVA = 2;
const MASC_PROP_INACTIVO = 3;

/** @return array{0:CitaService,1:TestCitaModelMockUS4} */
function crearEscenario(): array {
    TestLogUS4::$eventos = [];

    $servicios = [
        SERV_CONSULTA => ['id_servicio' => 1, 'nombre_servicio' => 'Consulta general', 'duracion_estimada_min' => 30, 'activo' => 1],
        SERV_CIRUGIA  => ['id_servicio' => 2, 'nombre_servicio' => 'Cirugia menor',    'duracion_estimada_min' => 60, 'activo' => 1],
        SERV_INACTIVO => ['id_servicio' => 3, 'nombre_servicio' => 'Descontinuado',    'duracion_estimada_min' => 30, 'activo' => 0],
    ];
    $vets = [
        VET_1         => ['id_veterinario' => 10, 'nombre' => 'Dra. Laura', 'especialidad' => 'General', 'activo' => 1],
        VET_2         => ['id_veterinario' => 11, 'nombre' => 'Dr. Carlos', 'especialidad' => 'Cirugia', 'activo' => 1],
        VET_INACTIVO  => ['id_veterinario' => 12, 'nombre' => 'Dr. Baja',   'especialidad' => null,      'activo' => 0],
    ];
    $horarios = [
        VET_1 => [1 => [['hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00']]],
        VET_2 => [1 => [
            ['hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00'],
            ['hora_inicio' => '14:00:00', 'hora_fin' => '17:00:00'],
        ]],
        VET_INACTIVO => [1 => [['hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00']]],
    ];
    $mascotas = [
        MASC_OK              => ['id_mascota' => 1, 'nombre' => 'Rocky', 'especie' => 'Perro', 'activo' => 1, 'propietario_activo' => 1, 'propietario_nombre' => 'Ana'],
        MASC_INACTIVA        => ['id_mascota' => 2, 'nombre' => 'Inactiva', 'especie' => 'Gato', 'activo' => 0, 'propietario_activo' => 1, 'propietario_nombre' => 'Ana'],
        MASC_PROP_INACTIVO   => ['id_mascota' => 3, 'nombre' => 'Huerfana', 'especie' => 'Gato', 'activo' => 1, 'propietario_activo' => 0, 'propietario_nombre' => 'Baja'],
    ];

    $citaModel = new TestCitaModelMockUS4($servicios);
    $service = new CitaService(
        new CitaRepository($citaModel),
        new HorarioAtencionRepository(new TestHorarioModelMockUS4($horarios)),
        new ServicioRepository(new TestServicioModelMockUS4($servicios)),
        new VeterinarioRepository(new TestVeterinarioModelMockUS4($vets)),
        new MascotaRepository(new TestMascotaModelMockUS4($mascotas))
    );
    return [$service, $citaModel];
}

function pedido(string $fecha, string $hora, int $servicio = SERV_CONSULTA, int $vet = VET_1, int $mascota = MASC_OK): array {
    return [
        'id_mascota' => $mascota, 'id_servicio' => $servicio, 'id_veterinario' => $vet,
        'fecha' => $fecha, 'hora_cita' => $hora,
    ];
}

function franjas(CitaService $s, string $fecha, DateTimeImmutable $ahora, int $servicio = SERV_CONSULTA, int $vet = VET_1): array {
    $r = $s->consultarDisponibilidad([
        'id_mascota' => MASC_OK, 'id_servicio' => $servicio, 'id_veterinario' => $vet, 'fecha' => $fecha,
    ], $ahora);
    assert($r['errors'] === [], 'No debia haber errores: ' . json_encode($r['errors']));
    return $r['franjas'];
}

// ────────────────────────────────────────────────────────────────── //
// Pruebas
// ────────────────────────────────────────────────────────────────── //

runTest('US-04: Horario libre — franjas calculadas segun horario y duracion del servicio', function () use ($FECHA, $AHORA) {
    [$s] = crearEscenario();
    $f = franjas($s, $FECHA, $AHORA);               // 08:00-12:00, servicio de 30 min, paso de 15
    assert(count($f) === 15, 'Se esperaban 15 franjas, hubo ' . count($f));
    assert($f[0] === '08:00', 'Primera franja 08:00');
    assert(end($f) === '11:30', 'Ultima franja 11:30 (termina 12:00)');

    $f60 = franjas($s, $FECHA, $AHORA, SERV_CIRUGIA); // 60 min -> ultima 11:00
    assert(end($f60) === '11:00', 'Servicio de 60 min: ultima franja 11:00');
});

runTest('US-04: Horario libre — la cita se crea en estado Programada', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    $r = $s->agendarCita(pedido($FECHA, '09:00'), $AHORA);
    assert($r['success'] === true, 'Debe agendar: ' . json_encode($r['errors']));
    assert($r['id'] === 1, 'Debe devolver el id');
    $c = $citas->items[1];
    assert($c['estado'] === 'Programada', 'Estado inicial Programada');
    assert($c['hora_cita'] === '09:00:00' && $c['fecha_cita'] === $FECHA, 'Fecha y hora guardadas');
    assert(str_contains($r['message'], 'Rocky'), 'El mensaje identifica la mascota');
});

runTest('US-04: Fuera de horario — se rechaza antes, despues y a mitad de un servicio que no cabe', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    foreach (['07:45', '12:00', '11:45'] as $hora) {    // 11:45 + 30 min = 12:15 > 12:00
        $r = $s->agendarCita(pedido($FECHA, $hora), $AHORA);
        assert($r['success'] === false && isset($r['errors']['hora_cita']), "Debe rechazar $hora");
    }
    assert($citas->items === [], 'No debe crearse ninguna cita');
});

runTest('US-04: Fuera de horario — dia sin horario definido no ofrece franjas ni permite agendar', function () use ($SABADO, $AHORA) {
    [$s, $citas] = crearEscenario();
    assert(franjas($s, $SABADO, $AHORA) === [], 'Sabado sin horario: sin franjas');
    $r = $s->agendarCita(pedido($SABADO, '09:00'), $AHORA);
    assert($r['success'] === false && $citas->items === [], 'No debe agendar un dia sin horario');
});

runTest('US-04: Horario con dos bloques — no ofrece el hueco entre bloques', function () use ($FECHA, $AHORA) {
    [$s] = crearEscenario();
    $f = franjas($s, $FECHA, $AHORA, SERV_CONSULTA, VET_2);
    assert(!in_array('12:00', $f, true) && !in_array('13:30', $f, true), 'Sin franjas entre 12:00 y 14:00');
    assert(in_array('11:30', $f, true) && in_array('14:00', $f, true) && in_array('16:30', $f, true), 'Franjas de ambos bloques');
    assert(!in_array('16:45', $f, true), '16:45 + 30 min excede las 17:00');
});

runTest('US-04: Servicio inactivo — se rechaza y no se crea cita', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    $r = $s->agendarCita(pedido($FECHA, '09:00', SERV_INACTIVO), $AHORA);
    assert($r['success'] === false && isset($r['errors']['id_servicio']), 'Debe rechazar servicio inactivo');
    assert($citas->items === [], 'No debe crearse cita');

    $c = $s->consultarDisponibilidad(['id_mascota' => MASC_OK, 'id_servicio' => SERV_INACTIVO, 'id_veterinario' => VET_1, 'fecha' => $FECHA], $AHORA);
    assert($c['franjas'] === [] && isset($c['errors']['id_servicio']), 'La consulta tampoco ofrece franjas');
    $ids = array_column($s->obtenerOpciones()['servicios'], 'id_servicio');
    assert(!in_array(SERV_INACTIVO, $ids, true), 'El selector no incluye servicios inactivos');
});

runTest('US-04: Cita solapada — se rechaza cualquier inicio que invada una cita Programada', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');   // ocupa 09:00-09:30

    foreach (['08:45', '09:00', '09:15'] as $hora) {
        $r = $s->agendarCita(pedido($FECHA, $hora), $AHORA);
        assert($r['success'] === false && isset($r['errors']['hora_cita']), "Debe rechazar $hora por solape");
    }
    assert(count($citas->items) === 1, 'Solo permanece la cita original');

    $f = franjas($s, $FECHA, $AHORA);
    assert(!in_array('08:45', $f, true) && !in_array('09:00', $f, true) && !in_array('09:15', $f, true), 'No se ofrecen horas solapadas');
    assert(in_array('08:30', $f, true) && in_array('09:30', $f, true), 'Citas contiguas (sin invadir) si se ofrecen');
});

runTest('US-04: Cita solapada — la duracion del servicio nuevo tambien cuenta', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '10:00:00');   // 10:00-10:30
    $f = franjas($s, $FECHA, $AHORA, SERV_CIRUGIA);                        // 60 min
    assert(!in_array('09:15', $f, true), '09:15 + 60 min invade la cita de las 10:00');
    assert(in_array('09:00', $f, true), '09:00 + 60 min termina justo a las 10:00');
    assert(!in_array('10:15', $f, true) && in_array('10:30', $f, true), '10:15 solapa; 10:30 es libre');
});

runTest('US-04: Cita solapada — otro veterinario no se ve afectado', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    $r = $s->agendarCita(pedido($FECHA, '09:00', SERV_CONSULTA, VET_2), $AHORA);
    assert($r['success'] === true, 'El mismo horario con otro veterinario debe permitirse');
});

runTest('US-04: Estados — una cita Cancelada libera el horario; una Atendida lo mantiene ocupado', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00', 'Cancelada');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '10:00:00', 'Atendida');
    $f = franjas($s, $FECHA, $AHORA);
    assert(in_array('09:00', $f, true), 'La cancelada no ocupa agenda');
    assert(!in_array('10:00', $f, true), 'La atendida si ocupa agenda');
    assert($s->agendarCita(pedido($FECHA, '09:00'), $AHORA)['success'] === true, 'Se puede reservar sobre una cancelada');
});

runTest('US-04: Dos solicitudes concurrentes al mismo hueco — solo una queda registrada', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    // Ambas administradoras vieron libre las 10:00 y confirman casi a la vez.
    $f1 = franjas($s, $FECHA, $AHORA);
    $f2 = franjas($s, $FECHA, $AHORA);
    assert(in_array('10:00', $f1, true) && in_array('10:00', $f2, true), 'Ambas la ven libre');

    $r1 = $s->agendarCita(pedido($FECHA, '10:00'), $AHORA);
    $r2 = $s->agendarCita(pedido($FECHA, '10:00', SERV_CONSULTA, VET_1, MASC_OK), $AHORA);
    assert($r1['success'] === true, 'La primera se registra');
    assert($r2['success'] === false && str_contains($r2['message'], 'disponible') && str_contains($r2['message'], 'ya no'), 'La segunda se rechaza con explicacion');
    assert(count($citas->items) === 1, 'Queda una sola cita');
});

runTest('US-04: Transaccion — bloquea al veterinario y revalida con lectura de bloqueo antes de insertar', function () use ($FECHA, $AHORA) {
    [$s] = crearEscenario();
    TestLogUS4::$eventos = [];
    $s->agendarCita(pedido($FECHA, '09:00'), $AHORA);
    $ev = TestLogUS4::$eventos;
    assert($ev === ['BEGIN', 'LOCK', 'READ_LOCK', 'INSERT', 'COMMIT'], 'Orden transaccional: ' . implode(',', $ev));

    // Un rechazo por disponibilidad no inserta nada
    TestLogUS4::$eventos = [];
    $s->agendarCita(pedido($FECHA, '09:00'), $AHORA);
    assert(!in_array('INSERT', TestLogUS4::$eventos, true), 'El rechazo no inserta');
});

runTest('US-04: Entidades invalidas — mascota inexistente, inactiva o con propietario inactivo', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    foreach ([99, MASC_INACTIVA, MASC_PROP_INACTIVO, 0] as $idMascota) {
        $r = $s->agendarCita(pedido($FECHA, '09:00', SERV_CONSULTA, VET_1, $idMascota), $AHORA);
        assert($r['success'] === false && isset($r['errors']['id_mascota']), "Debe rechazar mascota $idMascota");
    }
    $nombres = array_column($s->obtenerOpciones()['mascotas'], 'nombre');
    assert($nombres === ['Rocky'], 'El selector solo ofrece mascotas validas');
    assert($citas->items === [], 'No se crea cita');
});

runTest('US-04: Entidades invalidas — veterinario inexistente o inactivo', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    foreach ([VET_INACTIVO, 99] as $vet) {
        $r = $s->agendarCita(pedido($FECHA, '09:00', SERV_CONSULTA, $vet), $AHORA);
        assert($r['success'] === false && isset($r['errors']['id_veterinario']), "Debe rechazar veterinario $vet");
    }
    $ids = array_column($s->obtenerOpciones()['veterinarios'], 'id_veterinario');
    assert(!in_array(VET_INACTIVO, $ids, true), 'El selector no incluye veterinarios inactivos');
    assert($citas->items === [], 'No se crea cita');
});

runTest('US-04: Fecha — pasada, mal formada o inexistente se rechaza; hoy solo ofrece horas futuras', function () use ($FECHA, $AHORA, $LUNES) {
    [$s, $citas] = crearEscenario();
    foreach (['2000-01-01', '2030-02-30', '03/04/2030', '', 'abc'] as $fecha) {
        $r = $s->agendarCita(pedido($fecha, '09:00'), $AHORA);
        assert($r['success'] === false && isset($r['errors']['fecha']), "Debe rechazar fecha '$fecha'");
    }

    $lunes10 = $LUNES->setTime(10, 0);          // "ahora" es el propio lunes a las 10:00
    $f = franjas($s, $FECHA, $lunes10);
    assert($f[0] === '10:15', 'Hoy no se ofrecen horas pasadas ni la actual: ' . $f[0]);
    $r = $s->agendarCita(pedido($FECHA, '09:00'), $lunes10);
    assert($r['success'] === false, 'No se puede reservar una hora que ya paso hoy');

    $tarde = $LUNES->setTime(13, 0);
    assert(franjas($s, $FECHA, $tarde) === [], 'Despues del cierre no queda cupo hoy');
    assert($citas->items === [], 'No se creo ninguna cita');
});

runTest('US-04: Hora — ausente, mal formada o no ofrecida (fuera de la cuadricula) se rechaza', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    foreach (['', 'abc', '25:00', '9:00', '09:07', '09:00:00; DROP TABLE citas'] as $hora) {
        $r = $s->agendarCita(pedido($FECHA, $hora), $AHORA);
        assert($r['success'] === false && isset($r['errors']['hora_cita']), "Debe rechazar hora '$hora'");
    }
    assert($citas->items === [], 'No se crea cita');
});

runTest('US-04: Datos incompletos — se informan todos los errores a la vez', function () use ($AHORA) {
    [$s] = crearEscenario();
    $r = $s->agendarCita([], $AHORA);
    foreach (['id_mascota', 'id_servicio', 'id_veterinario', 'fecha', 'hora_cita'] as $campo) {
        assert(isset($r['errors'][$campo]), "Falta error para $campo");
    }
});

runTest('US-04: Estado — el modelo solo admite Programada, Atendida o Cancelada', function () {
    $citas = new TestCitaModelMockUS4([]);
    $base = ['id_mascota' => 1, 'id_veterinario' => 1, 'id_servicio' => 1, 'fecha_cita' => '2030-03-04', 'hora_cita' => '09:00:00'];
    $citas->insert($base + ['estado' => 'Atendida']);
    $lanzo = false;
    try {
        $citas->insert($base + ['estado' => 'Pendiente']);
    } catch (InvalidArgumentException) {
        $lanzo = true;
    }
    assert($lanzo, 'Un estado fuera del ENUM debe rechazarse');
    assert(CitaModel::ESTADOS === ['Programada', 'Atendida', 'Cancelada'], 'Constantes de estado');
});

// ────────────────────────────────────────────────────────────────── //
// US-05: Cancelación y listado (T-037, T-038, T-041)
// ────────────────────────────────────────────────────────────────── //

runTest('US-05: Cancelar una cita Programada — cambia a Cancelada, guarda el motivo y confirma', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    $r = $s->cancelarCita('1', "  El propietario viaja  ");
    assert($r['success'] === true, 'Debe cancelar: ' . $r['message']);
    assert($citas->items[1]['estado'] === 'Cancelada', 'Estado Cancelada');
    assert($citas->items[1]['motivo_cancelacion'] === 'El propietario viaja', 'Motivo guardado sin espacios sobrantes');
    assert(str_contains($r['message'], 'Cita cancelada') && str_contains($r['message'], 'Rocky'), 'Mensaje de confirmacion');
    assert(str_contains($r['message'], 'horario') && str_contains($r['message'], 'disponible'), 'Indica que el horario se libero');
});

runTest('US-05: El motivo es opcional — ausente, vacio o solo espacios se guarda como NULL', function () use ($FECHA) {
    foreach ([null, '', '   ', "\n\t "] as $i => $motivo) {
        [$s, $citas] = crearEscenario();
        $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
        $r = $s->cancelarCita(1, $motivo);
        assert($r['success'] === true, 'Debe cancelar sin motivo (caso ' . $i . ')');
        assert($citas->items[1]['motivo_cancelacion'] === null, 'Motivo NULL (caso ' . $i . ')');
    }
});

runTest('US-05: Motivo — se limita a 500 caracteres, se limpian controles y se rechaza lo invalido', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');

    $r = $s->cancelarCita(1, str_repeat('a', 501));
    assert($r['success'] === false && isset($r['errors']['motivo']), '501 caracteres: rechazado');
    assert($citas->items[1]['estado'] === 'Programada', 'Una cancelacion rechazada no cambia la cita');

    foreach (["\xC3\x28", ['x'], 12] as $malo) {   // UTF-8 invalido, arreglo, numero
        $r = $s->cancelarCita(1, $malo);
        assert($r['success'] === false && isset($r['errors']['motivo']), 'Motivo invalido rechazado');
    }
    assert($citas->items[1]['estado'] === 'Programada', 'Sigue Programada');

    $r = $s->cancelarCita(1, str_repeat("\xC3\xA1", 500) );
    assert($r['success'] === true && mb_strlen((string) $citas->items[1]['motivo_cancelacion'], 'UTF-8') === 500, '500 caracteres exactos (multibyte): aceptado');

    [$s2, $c2] = crearEscenario();
    $c2->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    $s2->cancelarCita(1, "Hola\x00 mundo\x07");
    assert($c2->items[1]['motivo_cancelacion'] === 'Hola mundo', 'Caracteres de control eliminados');
});

runTest('US-05: Una cita Atendida no se puede cancelar', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00', 'Atendida');
    TestLogUS4::$eventos = [];
    $r = $s->cancelarCita(1, 'x');
    assert($r['success'] === false && str_contains($r['message'], 'Atendida'), 'Rechazo que menciona el estado: ' . $r['message']);
    assert($citas->items[1]['estado'] === 'Atendida' && $citas->items[1]['motivo_cancelacion'] === null, 'La cita no cambia');
    assert(!in_array('CANCEL', TestLogUS4::$eventos, true), 'Ni siquiera se intenta el UPDATE');
});

runTest('US-05: Una cita Cancelada no vuelve a cancelarse ni pierde su motivo original', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    assert($s->cancelarCita(1, 'Motivo original')['success'] === true, 'Primera cancelacion');
    $r = $s->cancelarCita(1, 'Otro motivo');
    assert($r['success'] === false && str_contains($r['message'], 'Cancelada'), 'Segunda cancelacion rechazada');
    assert($citas->items[1]['motivo_cancelacion'] === 'Motivo original', 'El motivo original se conserva');
});

runTest('US-05: Identificador invalido o inexistente — se rechaza sin tocar datos', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    TestLogUS4::$eventos = [];
    foreach ([0, 'abc', '1e3', '-1', '1.5', '', null, 99, '99', ['1']] as $id) {
        $r = $s->cancelarCita($id, 'x');
        assert($r['success'] === false, 'Debe rechazar id ' . json_encode($id));
    }
    assert($citas->items[1]['estado'] === 'Programada', 'La cita existente sigue igual');
    assert(!in_array('CANCEL', TestLogUS4::$eventos, true), 'Nunca se llego al UPDATE');
});

runTest('US-05: Liberacion del horario — tras cancelar, la franja vuelve a ofrecerse y se puede reservar', function () use ($FECHA, $AHORA) {
    [$s, $citas] = crearEscenario();
    assert($s->agendarCita(pedido($FECHA, '09:00'), $AHORA)['success'] === true, 'Reserva inicial');
    assert(!in_array('09:00', franjas($s, $FECHA, $AHORA), true), 'Ocupada mientras esta Programada');
    assert($s->agendarCita(pedido($FECHA, '09:00'), $AHORA)['success'] === false, 'No admite otra encima');

    assert($s->cancelarCita(1, 'Cambio de planes')['success'] === true, 'Se cancela');
    assert(in_array('09:00', franjas($s, $FECHA, $AHORA), true), 'La franja 09:00 vuelve a estar disponible');
    $nueva = $s->agendarCita(pedido($FECHA, '09:00'), $AHORA);
    assert($nueva['success'] === true, 'Se puede reservar otra cita en ese horario');
    assert($citas->items[2]['estado'] === 'Programada', 'La nueva queda Programada');
});

runTest('US-05: Carrera — si la cita cambia entre la lectura y el UPDATE, no se pisa y se informa', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    $citas->antesDeCancelar = function (TestCitaModelMockUS4 $m) { $m->items[1]['estado'] = 'Atendida'; };
    $r = $s->cancelarCita(1, 'x');
    assert($r['success'] === false && str_contains($r['message'], 'Programada') && str_contains($r['message'], 'no se realizaron cambios'), 'Debe avisar: ' . $r['message']);
    assert($citas->items[1]['estado'] === 'Atendida' && $citas->items[1]['motivo_cancelacion'] === null, 'No se pisa el estado Atendida');
});

runTest('US-05: Listado — por defecto solo Programadas; filtros por estado; valor desconocido = Programada', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '10:00:00');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00', 'Atendida');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '11:00:00', 'Cancelada');

    $def = $s->listarCitas();
    assert($def['estado'] === 'Programada' && count($def['citas']) === 1, 'Por defecto: 1 Programada');
    assert(count($s->listarCitas('Atendida')['citas']) === 1, 'Atendidas');
    assert(count($s->listarCitas('Cancelada')['citas']) === 1, 'Canceladas');
    $todas = $s->listarCitas('Todas');
    assert($todas['estado'] === 'Todas' && count($todas['citas']) === 3, 'Todas');
    assert(array_column($todas['citas'], 'hora_cita') === ['09:00:00', '10:00:00', '11:00:00'], 'Ordenadas por fecha y hora');
    foreach (['xyz', '', null, ['Todas'], 5] as $raro) {
        assert($s->listarCitas($raro)['estado'] === 'Programada', 'Filtro desconocido -> Programada');
    }
    $c = $todas['citas'][0];
    assert(isset($c['mascota_nombre'], $c['nombre_servicio'], $c['veterinario_nombre'], $c['propietario_nombre']), 'Incluye nombres');
});

runTest('US-05: Listado — se limita a 200 citas e indica que hay mas', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    for ($i = 0; $i < 205; $i++) {
        $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    }
    $l = $s->listarCitas('Programada');
    assert(count($l['citas']) === 200 && $l['truncado'] === true, 'Tope de 200 con aviso');

    [$s2, $c2] = crearEscenario();
    for ($i = 0; $i < 200; $i++) {
        $c2->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    }
    $l2 = $s2->listarCitas('Programada');
    assert(count($l2['citas']) === 200 && $l2['truncado'] === false, 'Exactamente 200: sin aviso');
});

runTest('US-05: Pantalla de confirmacion — solo se ofrece para citas Programadas', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '10:00:00', 'Atendida');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '11:00:00', 'Cancelada');

    $ok = $s->obtenerParaCancelar('1');
    assert($ok['success'] === true && $ok['cita']['mascota_nombre'] === 'Rocky', 'Programada: se muestra');
    foreach (['2', '3', 'abc', '99'] as $id) {
        assert($s->obtenerParaCancelar($id)['success'] === false, "No se ofrece para $id");
    }
});

// ────────────────────────────────────────────────────────────────── //
//  US-08: Listado Filtrable de Citas (T-054 a T-058, RF08)
// ────────────────────────────────────────────────────────────────── //

runTest('US-08: Validación de filtros — estados permitidos y rechazo de estados no válidos con mensaje claro', function () {
    [$s] = crearEscenario();

    // Estados válidos
    foreach (['Programada', 'Atendida', 'Cancelada', 'Todas', ''] as $valido) {
        $r = $s->validarFiltros(['estado' => $valido]);
        assert($r['valido'] === true, "Estado '$valido' debe ser válido");
        assert(empty($r['errores']), "No debe haber errores para '$valido'");
    }

    // Estados inválidos
    foreach (['Invalido', 'Pendiente', 'Terminada', '123', 'null'] as $invalido) {
        $r = $s->validarFiltros(['estado' => $invalido]);
        assert($r['valido'] === false, "Estado '$invalido' debe ser rechazado");
        assert(isset($r['errores']['estado']), "Debe generar error de estado");
        assert(str_contains($r['errores']['estado'], 'solo se admiten Programada, Atendida o Cancelada'), 'Mensaje claro sobre estados permitidos');
    }
});

runTest('US-08: Validación de filtros — fechas válidas e inválidas producen mensaje claro', function () {
    [$s] = crearEscenario();

    // Fechas válidas
    $ok = $s->validarFiltros(['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']);
    assert($ok['valido'] === true && empty($ok['errores']), 'Fechas válidas');

    // Fechas inválidas por formato o calendario
    $casosInvalidos = [
        '31-10-2026', '2026/10/01', '2026-02-30', 'texto', '2026-13-01', '2026-00-10'
    ];
    foreach ($casosInvalidos as $fechaMala) {
        $rDesde = $s->validarFiltros(['fecha_desde' => $fechaMala]);
        assert($rDesde['valido'] === false && isset($rDesde['errores']['fecha_desde']), "fecha_desde '$fechaMala' rechazada");
        assert(str_contains($rDesde['errores']['fecha_desde'], 'AAAA-MM-DD'), 'Mensaje de formato para fecha_desde');

        $rHasta = $s->validarFiltros(['fecha_hasta' => $fechaMala]);
        assert($rHasta['valido'] === false && isset($rHasta['errores']['fecha_hasta']), "fecha_hasta '$fechaMala' rechazada");
        assert(str_contains($rHasta['errores']['fecha_hasta'], 'AAAA-MM-DD'), 'Mensaje de formato para fecha_hasta');
    }
});

runTest('US-08: Validación de filtros — coherencia de rango de fechas (fecha_desde <= fecha_hasta)', function () {
    [$s] = crearEscenario();

    // Mismo día: permitido
    $mismo = $s->validarFiltros(['fecha_desde' => '2026-10-28', 'fecha_hasta' => '2026-10-28']);
    assert($mismo['valido'] === true, 'Mismo día permitido');

    // fecha_desde anterior a fecha_hasta: permitido
    $rangoOk = $s->validarFiltros(['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-28']);
    assert($rangoOk['valido'] === true, 'Rango cronológico correcto');

    // fecha_desde posterior a fecha_hasta: rechazado
    $invertido = $s->validarFiltros(['fecha_desde' => '2026-10-29', 'fecha_hasta' => '2026-10-28']);
    assert($invertido['valido'] === false, 'Rango invertido debe ser rechazado');
    assert(isset($invertido['errores']['rango_fechas']), 'Error específico de rango de fechas');
    assert(str_contains($invertido['errores']['rango_fechas'], 'no puede ser posterior'), 'Mensaje claro de rango de fechas');
});

runTest('US-08: Validación de filtros — id_veterinario debe ser entero positivo', function () {
    [$s] = crearEscenario();

    $ok = $s->validarFiltros(['id_veterinario' => '2']);
    assert($ok['valido'] === true && $ok['criterios']['id_veterinario'] === 2, 'ID veterinario válido');

    foreach (['0', '-1', 'abc', '1.5'] as $malo) {
        $r = $s->validarFiltros(['id_veterinario' => $malo]);
        assert($r['valido'] === false && isset($r['errores']['id_veterinario']), "ID vet '$malo' rechazado");
        assert(str_contains($r['errores']['id_veterinario'], 'no es') && str_contains($r['errores']['id_veterinario'], 'veterinario'), 'Mensaje claro sobre veterinario');
    }
});

runTest('US-08: Listado filtrable — filtrado por estado específico y Todas', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00', 'Programada');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '10:00:00', 'Atendida');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '11:00:00', 'Cancelada');

    $prog = $s->listarCitas(['estado' => 'Programada']);
    assert(count($prog['citas']) === 1 && $prog['citas'][0]['estado'] === 'Programada', 'Solo Programada');

    $aten = $s->listarCitas(['estado' => 'Atendida']);
    assert(count($aten['citas']) === 1 && $aten['citas'][0]['estado'] === 'Atendida', 'Solo Atendida');

    $canc = $s->listarCitas(['estado' => 'Cancelada']);
    assert(count($canc['citas']) === 1 && $canc['citas'][0]['estado'] === 'Cancelada', 'Solo Cancelada');

    $todas = $s->listarCitas(['estado' => 'Todas']);
    assert(count($todas['citas']) === 3, 'Todas las citas');
});

runTest('US-08: Listado filtrable — filtrado por rango de fecha', function () {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, '2026-10-20', '09:00:00', 'Programada');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, '2026-10-25', '09:00:00', 'Programada');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, '2026-10-30', '09:00:00', 'Programada');

    // Filtro fecha_desde
    $rDesde = $s->listarCitas(['estado' => 'Todas', 'fecha_desde' => '2026-10-25']);
    assert(count($rDesde['citas']) === 2, 'Desde 2026-10-25: 2 citas');

    // Filtro fecha_hasta
    $rHasta = $s->listarCitas(['estado' => 'Todas', 'fecha_hasta' => '2026-10-25']);
    assert(count($rHasta['citas']) === 2, 'Hasta 2026-10-25: 2 citas');

    // Rango cerrado exacto
    $rRango = $s->listarCitas(['estado' => 'Todas', 'fecha_desde' => '2026-10-22', 'fecha_hasta' => '2026-10-28']);
    assert(count($rRango['citas']) === 1 && $rRango['citas'][0]['fecha_cita'] === '2026-10-25', 'Rango 22 a 28: solo 1 cita');
});

runTest('US-08: Listado filtrable — filtrado por médico veterinario', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00', 'Programada');
    $citas->sembrar(MASC_OK, VET_2, SERV_CONSULTA, $FECHA, '09:00:00', 'Programada');

    $rVet1 = $s->listarCitas(['estado' => 'Todas', 'id_veterinario' => VET_1]);
    assert(count($rVet1['citas']) === 1 && (int)$rVet1['citas'][0]['id_veterinario'] === VET_1, 'Solo VET_1');

    $rVet2 = $s->listarCitas(['estado' => 'Todas', 'id_veterinario' => VET_2]);
    assert(count($rVet2['citas']) === 1 && (int)$rVet2['citas'][0]['id_veterinario'] === VET_2, 'Solo VET_2');
});

runTest('US-08: Listado filtrable — combinación de filtros (estado + rango de fecha + veterinario)', function () {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, '2026-10-25', '09:00:00', 'Programada');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, '2026-10-25', '10:00:00', 'Cancelada');
    $citas->sembrar(MASC_OK, VET_2, SERV_CONSULTA, '2026-10-25', '09:00:00', 'Programada');
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, '2026-10-29', '09:00:00', 'Programada');

    $combo = $s->listarCitas([
        'estado'         => 'Programada',
        'fecha_desde'    => '2026-10-24',
        'fecha_hasta'    => '2026-10-26',
        'id_veterinario' => VET_1,
    ]);
    assert(count($combo['citas']) === 1, 'Solo 1 cita coincide con los 4 criterios simultáneos');
    assert($combo['citas'][0]['estado'] === 'Programada');
    assert((int)$combo['citas'][0]['id_veterinario'] === VET_1);
    assert($combo['citas'][0]['fecha_cita'] === '2026-10-25');
});

runTest('US-08: Listado filtrable — búsqueda por término relacionado y empty state', function () use ($FECHA) {
    [$s, $citas] = crearEscenario();
    $citas->sembrar(MASC_OK, VET_1, SERV_CONSULTA, $FECHA, '09:00:00', 'Programada');

    // Búsqueda por nombre de mascota
    $rMasc = $s->listarCitas(['estado' => 'Todas', 'q' => 'Rocky']);
    assert(count($rMasc['citas']) === 1, 'Coincide por mascota');

    // Búsqueda sin coincidencias (empty state)
    $rVacio = $s->listarCitas(['estado' => 'Todas', 'q' => 'InexistenteXYZ']);
    assert(count($rVacio['citas']) === 0, 'Sin resultados produce lista vacía');

    // Rango de fechas sin citas (empty state)
    $rRangoVacio = $s->listarCitas(['estado' => 'Todas', 'fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-01-02']);
    assert(count($rRangoVacio['citas']) === 0, 'Rango sin citas produce lista vacía');
});

runTest('US-08: Listado filtrable — filtros inválidos devuelven lista vacía y reporte de errores', function () {
    [$s] = crearEscenario();
    $res = $s->listarCitas([
        'estado'      => 'Invalido',
        'fecha_desde' => '2026-99-99',
    ]);
    assert($res['citas'] === [], 'No ejecuta consulta con filtros inválidos');
    assert(!empty($res['errors']), 'Devuelve errores estructurados');
    assert(isset($res['errors']['estado']), 'Error en estado');
    assert(isset($res['errors']['fecha_desde']), 'Error en fecha');
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
