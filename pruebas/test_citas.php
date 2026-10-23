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
        $this->items[$id] = $data + ['id_cita' => $id, 'estado' => $estado];
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
    assert($r2['success'] === false && str_contains($r2['message'], 'ya no está disponible'), 'La segunda se rechaza con explicacion');
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

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
