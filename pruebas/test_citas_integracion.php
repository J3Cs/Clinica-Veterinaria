<?php
/**
 * Pruebas de integración: US-04 (RF04, T-036) — Agendamiento contra MySQL real.
 *
 * Verifica lo que los dobles en memoria no pueden: las claves foráneas, el ENUM
 * de estado y, sobre todo, que N procesos PHP simultáneos que piden el mismo
 * horario del mismo veterinario dejen UNA sola cita Programada.
 *
 * Requiere una base con el esquema de init.sql. Usa las variables DB_HOST,
 * DB_PORT, DB_NAME, DB_USER y DB_PASS. Crea sus propios datos (prefijo único)
 * y los elimina al terminar. Si no puede conectar, se omite.
 *
 * Ejecución local (ejemplo con Docker):
 *   DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=app_user DB_PASS=app_password \
 *   php -d zend.assertions=1 pruebas/test_citas_integracion.php
 */

declare(strict_types=1);

ini_set('assert.exception', '1');

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/../src/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Config\Database;
use App\Models\CitaModel;
use App\Services\CitaService;

// ── Modo trabajador: una solicitud de reserva lanzada por el proceso padre ── //
if (($argv[1] ?? '') === '--worker') {
    $datos  = json_decode(base64_decode($argv[2]), true);
    $inicio = (float) $argv[3];

    Database::getConnection();            // conectar ANTES de la barrera
    $service = new CitaService();         // los modelos ya comparten la conexión
    while (microtime(true) < $inicio) {   // barrera: todos arrancan a la vez
        usleep(200);
    }
    echo json_encode($service->agendarCita($datos));
    exit(0);
}

if (($argv[1] ?? '') === '--worker-cancelar') {
    $datos  = json_decode(base64_decode($argv[2]), true);
    $inicio = (float) $argv[3];

    Database::getConnection();
    $service = new CitaService();
    while (microtime(true) < $inicio) {
        usleep(200);
    }
    echo json_encode($service->cancelarCita($datos['id'], $datos['motivo']));
    exit(0);
}

try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    echo "OMITIDA: no hay conexión a la base de datos (" . strtok($e->getMessage(), "\n") . ")\n";
    exit(0);
}

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

echo "========================================================\n";
echo " Clinica Veterinaria -- Integracion US-04 (MySQL real)\n";
echo "========================================================\n\n";

// ── Datos propios de la prueba ────────────────────────────────────── //
$tag   = 'it' . bin2hex(random_bytes(4));
$fecha = (new DateTimeImmutable('next monday'))->modify('+1 week')->format('Y-m-d');   // lunes futuro

$ids = [];
try {
    $pdo->prepare("INSERT INTO usuarios (nombre, correo, usuario, contrasena, rol, activo) VALUES (?,?,?,?,?,1)")
        ->execute(["Vet $tag", "$tag@test.local", $tag, '!sin-acceso', 'veterinario']);
    $ids['usuario'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO veterinarios (id_usuario, especialidad) VALUES (?, 'Test')")->execute([$ids['usuario']]);
    $ids['vet'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO horarios_atencion (id_veterinario, dia_semana, hora_inicio, hora_fin) VALUES (?, 1, '08:00:00', '12:00:00')")
        ->execute([$ids['vet']]);

    $pdo->prepare("INSERT INTO servicios (nombre_servicio, duracion_estimada_min, activo) VALUES (?, 30, 1)")->execute(["Svc $tag"]);
    $ids['servicio'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO propietarios (dui, nombre_completo) VALUES (?, ?)")
        ->execute([substr(preg_replace('/\D/', '', $tag . '0000000000') . '000000000', 0, 8) . '-0', "Prop $tag"]);
    $ids['prop'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO mascotas (id_propietario, nombre, especie, sexo) VALUES (?, ?, 'Perro', 'Macho')")
        ->execute([$ids['prop'], "Pet $tag"]);
    $ids['mascota'] = (int) $pdo->lastInsertId();

    $pedido = fn(string $hora): array => [
        'id_mascota' => $ids['mascota'], 'id_servicio' => $ids['servicio'],
        'id_veterinario' => $ids['vet'], 'fecha' => $fecha, 'hora_cita' => $hora,
    ];
    $contar = function (string $hora) use ($pdo, $ids, $fecha): int {
        $st = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE id_veterinario=? AND fecha_cita=? AND hora_cita=? AND estado='Programada'");
        $st->execute([$ids['vet'], $fecha, $hora . ':00']);
        return (int) $st->fetchColumn();
    };
    $service = new CitaService();

    runTest('Reserva real: se guarda como Programada con fecha y hora', function () use ($service, $pedido, $contar) {
        $r = $service->agendarCita($pedido('09:00'));
        assert($r['success'] === true, 'Debe agendar: ' . json_encode($r));
        assert($contar('09:00') === 1, 'Una cita Programada a las 09:00');
    });

    runTest('Reserva real: el mismo horario se rechaza', function () use ($service, $pedido, $contar) {
        $r = $service->agendarCita($pedido('09:00'));
        assert($r['success'] === false && isset($r['errors']['hora_cita']), 'Debe rechazar el duplicado');
        assert($contar('09:00') === 1, 'Sigue habiendo una sola');
    });

    runTest('Reserva real: un horario que solapa parcialmente se rechaza; uno contiguo se acepta', function () use ($service, $pedido) {
        assert($service->agendarCita($pedido('09:15'))['success'] === false, '09:15 solapa con 09:00-09:30');
        assert($service->agendarCita($pedido('08:30'))['success'] === true, '08:30-09:00 es contiguo');
        assert($service->agendarCita($pedido('09:30'))['success'] === true, '09:30 es contiguo');
    });

    runTest('Reserva real: una cita Cancelada libera el horario', function () use ($service, $pedido, $pdo, $ids, $fecha, $contar) {
        $pdo->prepare("UPDATE citas SET estado='Cancelada', motivo_cancelacion='prueba' WHERE id_veterinario=? AND fecha_cita=? AND hora_cita='09:00:00'")
            ->execute([$ids['vet'], $fecha]);
        assert($contar('09:00') === 0, 'La de las 09:00 quedo cancelada');
        assert($service->agendarCita($pedido('09:00'))['success'] === true, 'Se puede reservar de nuevo');
        assert($contar('09:00') === 1, 'Una Programada nueva');
    });

    runTest('DAT-01: la base rechaza una mascota inexistente (clave foranea)', function () use ($ids, $fecha) {
        $lanzo = false;
        try {
            (new CitaModel())->insert([
                'id_mascota' => 999999999, 'id_veterinario' => $ids['vet'], 'id_servicio' => $ids['servicio'],
                'fecha_cita' => $fecha, 'hora_cita' => '11:00:00',
            ]);
        } catch (\PDOException) {
            $lanzo = true;
        }
        assert($lanzo, 'La FK debe impedir la cita');
    });

    runTest('Estado: el modelo rechaza un estado fuera del ENUM', function () use ($ids, $fecha) {
        $lanzo = false;
        try {
            (new CitaModel())->insert([
                'id_mascota' => $ids['mascota'], 'id_veterinario' => $ids['vet'], 'id_servicio' => $ids['servicio'],
                'fecha_cita' => $fecha, 'hora_cita' => '11:00:00', 'estado' => 'Pendiente',
            ]);
        } catch (InvalidArgumentException) {
            $lanzo = true;
        }
        assert($lanzo, 'Estado invalido debe lanzar excepcion');
    });

    runTest('BAJA-01: no se puede borrar fisicamente una mascota con citas (RESTRICT)', function () use ($pdo, $ids) {
        $lanzo = false;
        try {
            $pdo->prepare("DELETE FROM mascotas WHERE id_mascota = ?")->execute([$ids['mascota']]);
        } catch (\PDOException) {
            $lanzo = true;
        }
        assert($lanzo, 'El DELETE debe fallar por la clave foranea');
    });

    // ── Concurrencia real: N procesos, mismo veterinario, mismo horario ── //
    runTest('Concurrencia: 8 procesos simultaneos piden el mismo horario -> exactamente 1 reserva', function () use ($pedido, $contar) {
        $n = 8;
        $horaDisputada = '10:00';
        $inicio = microtime(true) + 1.5;                       // barrera común
        $payload = base64_encode(json_encode($pedido($horaDisputada)));

        $procs = [];
        for ($i = 0; $i < $n; $i++) {
            $cmd = [PHP_BINARY, __FILE__, '--worker', $payload, (string) $inicio];
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            assert(is_resource($p), 'No se pudo lanzar el proceso');
            $procs[] = [$p, $pipes];
        }

        $exitos = 0;
        $rechazos = 0;
        foreach ($procs as [$p, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            $r = json_decode($out, true);
            assert(is_array($r), 'Respuesta invalida del proceso: ' . $out . ' ' . $err);
            $r['success'] ? $exitos++ : $rechazos++;
        }

        assert($exitos === 1, "Debia haber 1 exito y hubo $exitos");
        assert($rechazos === $n - 1, "Debia haber " . ($n - 1) . " rechazos y hubo $rechazos");
        assert($contar($horaDisputada) === 1, 'En la base debe quedar UNA cita Programada a las ' . $horaDisputada);
    });


    // ── US-05: cancelación (T-037, T-038, T-041) ──────────────────────────── //
    $estadoDe = function (int $idCita) use ($pdo): array {
        $st = $pdo->prepare("SELECT estado, motivo_cancelacion FROM citas WHERE id_cita = ?");
        $st->execute([$idCita]);
        return $st->fetch(PDO::FETCH_ASSOC);
    };
    $idCancelar = 0;

    runTest('Cancelacion real: una cita Programada pasa a Cancelada y guarda el motivo', function () use ($service, $pedido, $estadoDe, &$idCancelar) {
        $r = $service->agendarCita($pedido('11:00'));
        assert($r['success'] === true, 'Se crea la cita a cancelar');
        $idCancelar = $r['id'];

        $c = $service->cancelarCita((string) $idCancelar, '  Prueba de cancelacion  ');
        assert($c['success'] === true, 'Debe cancelar: ' . $c['message']);
        $fila = $estadoDe($idCancelar);
        assert($fila['estado'] === 'Cancelada', 'Estado Cancelada en la base');
        assert($fila['motivo_cancelacion'] === 'Prueba de cancelacion', 'Motivo guardado');
    });

    runTest('Cancelacion real: el UPDATE condicional no afecta una cita ya Cancelada y conserva el motivo', function () use ($service, $estadoDe, &$idCancelar) {
        assert((new CitaModel())->cancelarSiProgramada($idCancelar, 'otro') === 0, '0 filas afectadas');
        $c = $service->cancelarCita($idCancelar, 'otro');
        assert($c['success'] === false, 'El servicio tambien la rechaza');
        assert($estadoDe($idCancelar)['motivo_cancelacion'] === 'Prueba de cancelacion', 'El motivo original no cambia');
    });

    runTest('Cancelacion real: una cita Atendida no se cancela (servicio ni UPDATE condicional)', function () use ($service, $pedido, $pdo, $estadoDe) {
        $r = $service->agendarCita($pedido('11:30'));
        assert($r['success'] === true, 'Se crea la cita');
        $pdo->prepare("UPDATE citas SET estado='Atendida' WHERE id_cita=?")->execute([$r['id']]);

        assert($service->cancelarCita($r['id'], 'x')['success'] === false, 'El servicio la rechaza');
        assert((new CitaModel())->cancelarSiProgramada($r['id'], 'x') === 0, 'El UPDATE condicional afecta 0 filas');
        $fila = $estadoDe($r['id']);
        assert($fila['estado'] === 'Atendida' && $fila['motivo_cancelacion'] === null, 'Sigue Atendida y sin motivo');
    });

    runTest('Cancelacion real: el horario liberado se puede reservar de nuevo', function () use ($service, $pedido, $contar) {
        assert($contar('11:00') === 0, 'No hay Programada a las 11:00 (la cancelada no cuenta)');
        $r = $service->agendarCita($pedido('11:00'));
        assert($r['success'] === true, 'Se puede reservar el horario liberado: ' . json_encode($r['errors']));
        assert($contar('11:00') === 1, 'Una Programada nueva');
    });

    runTest('Listado real: trae nombres de mascota, servicio y veterinario y respeta el filtro de estado', function () use ($service, $ids, &$idCancelar) {
        $d = (new CitaModel())->findDetalleById($idCancelar);
        assert($d !== null && $d['mascota_nombre'] !== '' && $d['nombre_servicio'] !== '' && $d['veterinario_nombre'] !== '', 'Detalle con las uniones');
        assert($d['propietario_nombre'] !== '' && (int) $d['duracion_estimada_min'] === 30, 'Propietario y duracion');

        $canceladas = array_column($service->listarCitas('Cancelada')['citas'], 'id_cita');
        $programadas = array_column($service->listarCitas('Programada')['citas'], 'id_cita');
        assert(in_array($idCancelar, array_map('intval', $canceladas), true), 'Aparece entre las Canceladas');
        assert(!in_array($idCancelar, array_map('intval', $programadas), true), 'No aparece entre las Programadas');
    });

    runTest('Concurrencia: 6 procesos cancelan la misma cita a la vez -> exactamente 1 lo logra', function () use ($service, $pedido, $estadoDe) {
        $r = $service->agendarCita($pedido('10:30'));
        assert($r['success'] === true, 'Se crea la cita a cancelar');
        $idCita = $r['id'];

        $n = 6;
        $inicio = microtime(true) + 1.5;
        $procs = [];
        for ($i = 0; $i < $n; $i++) {
            // Motivo distinto por proceso: si el UPDATE no fuera condicional, todos "tendrian exito".
            $payload = base64_encode(json_encode(['id' => (string) $idCita, 'motivo' => "proceso-$i"]));
            $cmd = [PHP_BINARY, __FILE__, '--worker-cancelar', $payload, (string) $inicio];
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            assert(is_resource($p), 'No se pudo lanzar el proceso');
            $procs[] = [$p, $pipes];
        }

        $exitos = 0;
        foreach ($procs as [$p, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            $res = json_decode($out, true);
            assert(is_array($res), 'Respuesta invalida: ' . $out . ' ' . $err);
            if ($res['success']) { $exitos++; }
        }

        assert($exitos === 1, "Debia haber 1 cancelacion exitosa y hubo $exitos");
        $fila = $estadoDe($idCita);
        assert($fila['estado'] === 'Cancelada', 'Quedo Cancelada');
        assert(str_starts_with((string) $fila['motivo_cancelacion'], 'proceso-'), 'El motivo es el del proceso ganador');
    });

} finally {
    // Limpieza (los DELETE respetan el orden de las claves foráneas RESTRICT)
    if (isset($ids['mascota'])) { $pdo->prepare("DELETE FROM citas WHERE id_mascota = ?")->execute([$ids['mascota']]); }
    if (isset($ids['mascota'])) { $pdo->prepare("DELETE FROM mascotas WHERE id_mascota = ?")->execute([$ids['mascota']]); }
    if (isset($ids['prop']))    { $pdo->prepare("DELETE FROM propietarios WHERE id_propietario = ?")->execute([$ids['prop']]); }
    if (isset($ids['servicio'])){ $pdo->prepare("DELETE FROM servicios WHERE id_servicio = ?")->execute([$ids['servicio']]); }
    if (isset($ids['vet']))     { $pdo->prepare("DELETE FROM horarios_atencion WHERE id_veterinario = ?")->execute([$ids['vet']]); }
    if (isset($ids['vet']))     { $pdo->prepare("DELETE FROM veterinarios WHERE id_veterinario = ?")->execute([$ids['vet']]); }
    if (isset($ids['usuario'])) { $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$ids['usuario']]); }
}

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
