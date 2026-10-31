<?php
/**
 * Pruebas de integración: US-06 (RF06, T-048) — Consultas contra MySQL real.
 *
 * Verifica lo que los dobles en memoria no pueden: el rollback verdadero de la
 * transacción, el UNIQUE de `id_cita`, que NO se escriba en `historial_mascota`
 * y la concurrencia real con varios procesos PHP.
 *
 * Requiere una base con el esquema de init.sql. Usa DB_HOST, DB_PORT, DB_NAME,
 * DB_USER y DB_PASS. Crea sus propios datos (prefijo único) y los elimina al
 * terminar. Si no puede conectar, se omite.
 *
 * Ejecución local (ejemplo con Docker):
 *   DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=app_user DB_PASS=app_password \
 *   php -d zend.assertions=1 pruebas/test_consultas_integracion.php
 */

declare(strict_types=1);

ini_set('assert.exception', '1');
ini_set('error_log', sys_get_temp_dir() . '/test_consultas_integracion_errores.log');

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
function getConsultasScriptFile(): string {
    return (file_exists(__FILE__) && is_file(__FILE__)) ? __FILE__ : '/var/www/html/pruebas/test_consultas_integracion.php';
}

use App\Config\Database;
use App\Models\CitaModel;
use App\Models\ConsultaVeterinariaModel;
use App\Repositories\CitaRepository;
use App\Services\CitaService;
use App\Services\ConsultaService;

// ── Modo trabajador: una operación lanzada por el proceso padre ────────── //
$modo = $argv[1] ?? '';
if ($modo === '--worker-consulta' || $modo === '--worker-cancelar') {
    $datos  = json_decode(base64_decode($argv[2]), true);
    $inicio = (float) $argv[3];

    Database::getConnection();                               // conectar ANTES de la barrera
    $consultas = new ConsultaService();
    $citas     = new CitaService();
    while (microtime(true) < $inicio) {                      // barrera: todos arrancan a la vez
        usleep(200);
    }
    usleep(((int) ($datos['retraso_ms'] ?? 0)) * 1000);      // retraso opcional para variar quien llega primero

    $res = $modo === '--worker-consulta'
        ? $consultas->registrarConsulta($datos['usuario'], $datos['id_cita'], $datos['datos'])
        : $citas->cancelarCita($datos['id_cita'], 'cancelada en la carrera');
    echo json_encode($res);
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

/** Lanza $n procesos a la vez y devuelve sus resultados decodificados. */
function lanzarEnParalelo(array $trabajos): array {
    $inicio = microtime(true) + 1.5;
    $procs = [];
    foreach ($trabajos as [$modo, $datos]) {
        $cmd = [PHP_BINARY, getConsultasScriptFile(), $modo, base64_encode(json_encode($datos)), (string) $inicio];
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        assert(is_resource($p), 'No se pudo lanzar el proceso');
        $procs[] = [$p, $pipes];
    }
    $res = [];
    foreach ($procs as [$p, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);
        $r = json_decode($out, true);
        assert(is_array($r), 'Respuesta invalida del proceso: ' . $out . ' ' . $err);
        $res[] = $r;
    }
    return $res;
}

echo "========================================================\n";
echo " Clinica Veterinaria -- Integracion US-06 (MySQL real)\n";
echo "========================================================\n\n";

// ── Datos propios de la prueba ────────────────────────────────────── //
$tag   = 'ic' . bin2hex(random_bytes(4));
$fecha = (new DateTimeImmutable('next monday'))->modify('+1 week')->format('Y-m-d');
$ahora = new DateTimeImmutable('2030-03-04 10:30:00');
$ids   = [];

$datosValidos = [
    'diagnostico' => 'Otitis externa', 'tratamiento' => 'Gotas óticas cada 12 h',
    'observaciones' => 'Prurito moderado', 'servicios_adicionales' => 'Limpieza ótica',
];

try {
    $crearVet = function (string $sufijo) use ($pdo, $tag): array {
        $pdo->prepare("INSERT INTO usuarios (nombre, correo, usuario, contrasena, rol, activo) VALUES (?,?,?,?,?,1)")
            ->execute(["Vet $sufijo $tag", "$sufijo-$tag@test.local", "$sufijo$tag", '!sin-acceso', 'veterinario']);
        $idUsuario = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO veterinarios (id_usuario, especialidad) VALUES (?, 'Test')")->execute([$idUsuario]);
        return ['usuario' => $idUsuario, 'vet' => (int) $pdo->lastInsertId()];
    };
    $a = $crearVet('a');
    $b = $crearVet('b');
    $ids['usuarios'] = [$a['usuario'], $b['usuario']];
    $ids['vets']     = [$a['vet'], $b['vet']];

    $pdo->prepare("INSERT INTO servicios (nombre_servicio, duracion_estimada_min, activo) VALUES (?, 30, 1)")->execute(["Svc $tag"]);
    $ids['servicio'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO propietarios (dui, nombre_completo) VALUES (?, ?)")
        ->execute([sprintf('%08d-%d', random_int(0, 99999999), random_int(0, 9)), "Prop $tag"]);
    $ids['prop'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO mascotas (id_propietario, nombre, especie, sexo) VALUES (?, ?, 'Perro', 'Macho')")
        ->execute([$ids['prop'], "Pet $tag"]);
    $ids['mascota'] = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO historial_mascota (id_mascota, condicion_medica, fecha_registro_antecedente) VALUES (?, 'Epilepsia', '2029-06-01')")
        ->execute([$ids['mascota']]);

    $horaSiguiente = 8 * 60;
    $crearCita = function (int $idVet, string $estado = 'Programada') use ($pdo, $ids, $fecha, &$horaSiguiente): int {
        $hora = sprintf('%02d:%02d:00', intdiv($horaSiguiente, 60), $horaSiguiente % 60);
        $horaSiguiente += 30;
        $pdo->prepare("INSERT INTO citas (id_mascota, id_veterinario, id_servicio, fecha_cita, hora_cita, estado) VALUES (?,?,?,?,?,?)")
            ->execute([$ids['mascota'], $idVet, $ids['servicio'], $fecha, $hora, $estado]);
        return (int) $pdo->lastInsertId();
    };
    $estadoCita = function (int $idCita) use ($pdo): string {
        $st = $pdo->prepare("SELECT estado FROM citas WHERE id_cita = ?");
        $st->execute([$idCita]);
        return (string) $st->fetchColumn();
    };
    $nConsultas = function (int $idCita) use ($pdo): int {
        $st = $pdo->prepare("SELECT COUNT(*) FROM consultas_veterinarias WHERE id_cita = ?");
        $st->execute([$idCita]);
        return (int) $st->fetchColumn();
    };
    $nHistorial = function () use ($pdo, $ids): int {
        $st = $pdo->prepare("SELECT COUNT(*) FROM historial_mascota WHERE id_mascota = ?");
        $st->execute([$ids['mascota']]);
        return (int) $st->fetchColumn();
    };

    $service = new ConsultaService();
    $citaOk  = $crearCita($a['vet']);

    runTest('Registro real: guarda la consulta y la cita pasa a Atendida', function () use ($service, $citaOk, $a, $datosValidos, $ahora, $pdo, $ids, $estadoCita, $nConsultas) {
        $r = $service->registrarConsulta($a['usuario'], (string) $citaOk, $datosValidos, $ahora);
        assert($r['success'] === true, 'Debe guardar: ' . json_encode($r));
        assert($estadoCita($citaOk) === 'Atendida', 'Cita Atendida');
        assert($nConsultas($citaOk) === 1, 'Una consulta');

        $st = $pdo->prepare("SELECT * FROM consultas_veterinarias WHERE id_cita = ?");
        $st->execute([$citaOk]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        assert((int) $c['id_mascota'] === $ids['mascota'] && (int) $c['id_veterinario'] === $a['vet'], 'Mascota y veterinario tomados de la cita');
        assert($c['fecha_atencion'] === '2030-03-04 10:30:00', 'Fecha de atencion: ' . $c['fecha_atencion']);
        assert($c['diagnostico'] === 'Otitis externa' && $c['observaciones'] === 'Prurito moderado', 'Textos guardados');
    });

    runTest('Historial: la consulta NO se copia a historial_mascota y aparece una sola vez en el consolidado', function () use ($service, $ids, $nHistorial) {
        assert($nHistorial() === 1, 'historial_mascota sigue con su unico antecedente');
        $h = $service->historialClinico($ids['mascota']);
        $tipos = array_count_values(array_column($h, 'tipo'));
        assert(($tipos['Consulta'] ?? 0) === 1 && ($tipos['Antecedente'] ?? 0) === 1, 'Una consulta y un antecedente: ' . json_encode($tipos));
        assert($h[0]['tipo'] === 'Consulta', 'La consulta (2030) es lo mas reciente');
    });

    runTest('Una sola consulta por cita: la segunda se rechaza', function () use ($service, $citaOk, $a, $datosValidos, $nConsultas) {
        $r = $service->registrarConsulta($a['usuario'], $citaOk, ['diagnostico' => 'Otro'] + $datosValidos);
        assert($r['success'] === false, 'Debe rechazar: ' . $r['message']);
        assert($nConsultas($citaOk) === 1, 'Sigue habiendo una');
    });

    runTest('DAT-01: la base impide una segunda consulta para la misma cita (UNIQUE)', function () use ($citaOk, $a, $ids) {
        $lanzo = false;
        try {
            (new ConsultaVeterinariaModel())->insert([
                'id_cita' => $citaOk, 'id_mascota' => $ids['mascota'], 'id_veterinario' => $a['vet'],
                'fecha_atencion' => '2030-03-04 10:30:00', 'diagnostico' => 'X', 'tratamiento' => 'Y',
            ]);
        } catch (\PDOException $e) {
            $lanzo = ($e->errorInfo[1] ?? null) === 1062;
        }
        assert($lanzo, 'El UNIQUE debe rechazarlo con error 1062');
    });

    runTest('El modelo exige diagnostico y tratamiento', function () use ($crearCita, $a, $ids) {
        $idCita = $crearCita($a['vet']);
        foreach (['diagnostico', 'tratamiento'] as $campo) {
            $d = ['id_cita' => $idCita, 'id_mascota' => $ids['mascota'], 'id_veterinario' => $a['vet'],
                  'fecha_atencion' => '2030-03-04 10:30:00', 'diagnostico' => 'X', 'tratamiento' => 'Y', $campo => '  '];
            $lanzo = false;
            try { (new ConsultaVeterinariaModel())->insert($d); } catch (InvalidArgumentException) { $lanzo = true; }
            assert($lanzo, "Debe exigir $campo");
        }
    });

    runTest('Reglas: cita ajena, cancelada, atendida y campos vacios se rechazan sin dejar nada', function () use ($service, $crearCita, $a, $b, $datosValidos, $estadoCita, $nConsultas) {
        $ajena = $crearCita($a['vet']);
        $r = $service->registrarConsulta($b['usuario'], $ajena, $datosValidos);
        assert($r['success'] === false && str_contains($r['message'], 'asignada a tu usuario'), 'Cita de otro veterinario: ' . $r['message']);

        $cancelada = $crearCita($a['vet'], 'Cancelada');
        assert($service->registrarConsulta($a['usuario'], $cancelada, $datosValidos)['success'] === false, 'Cancelada');
        $atendida = $crearCita($a['vet'], 'Atendida');
        assert($service->registrarConsulta($a['usuario'], $atendida, $datosValidos)['success'] === false, 'Atendida sin consulta previa');

        $vacio = $crearCita($a['vet']);
        $r = $service->registrarConsulta($a['usuario'], $vacio, ['diagnostico' => '', 'tratamiento' => ''] + $datosValidos);
        assert($r['success'] === false && isset($r['errors']['diagnostico'], $r['errors']['tratamiento']), 'Campos vacios');

        foreach ([$ajena, $cancelada, $atendida, $vacio] as $id) {
            assert($nConsultas($id) === 0, "Sin consulta para la cita $id");
        }
        assert($estadoCita($ajena) === 'Programada' && $estadoCita($vacio) === 'Programada', 'Siguen Programadas');
        assert($estadoCita($cancelada) === 'Cancelada' && $estadoCita($atendida) === 'Atendida', 'Estados intactos');
    });

    runTest('ROLLBACK real: si falla el cambio de estado, la consulta ya insertada desaparece', function () use ($crearCita, $a, $datosValidos, $estadoCita, $nConsultas) {
        $citaQueFalla = new class extends CitaModel {
            public function marcarAtendidaSiProgramada(int $id): int {
                throw new \PDOException('fallo simulado al cambiar el estado');
            }
        };
        $svc = new ConsultaService(new CitaRepository($citaQueFalla));
        $idCita = $crearCita($a['vet']);

        $r = $svc->registrarConsulta($a['usuario'], $idCita, $datosValidos);
        assert($r['success'] === false && $r['reintentable'] === true, 'Falla y se puede reintentar');
        assert($nConsultas($idCita) === 0, 'No queda consulta parcial');
        assert($estadoCita($idCita) === 'Programada', 'No queda estado inconsistente');

        // Y la operacion sigue funcionando despues del fallo
        $ok = (new ConsultaService())->registrarConsulta($a['usuario'], $idCita, $datosValidos);
        assert($ok['success'] === true && $nConsultas($idCita) === 1 && $estadoCita($idCita) === 'Atendida', 'El reintento funciona');
    });

    runTest('ROLLBACK real: si el UPDATE no afecta filas, se revierte la consulta', function () use ($crearCita, $a, $datosValidos, $estadoCita, $nConsultas) {
        $citaSinEfecto = new class extends CitaModel {
            public function marcarAtendidaSiProgramada(int $id): int { return 0; }
        };
        $svc = new ConsultaService(new CitaRepository($citaSinEfecto));
        $idCita = $crearCita($a['vet']);

        $r = $svc->registrarConsulta($a['usuario'], $idCita, $datosValidos);
        assert($r['success'] === false && str_contains($r['message'], 'Programada') && str_contains($r['message'], 'no se'), 'Rechazo: ' . $r['message']);
        assert($nConsultas($idCita) === 0 && $estadoCita($idCita) === 'Programada', 'Sin consulta ni cambio de estado');
    });

    runTest('El historial_mascota no cambio durante todas las pruebas', function () use ($nHistorial) {
        assert($nHistorial() === 1, 'Sigue el unico antecedente original');
    });

    runTest('Concurrencia: 6 procesos registran la consulta de la misma cita -> exactamente 1', function () use ($crearCita, $a, $datosValidos, $estadoCita, $nConsultas) {
        $idCita = $crearCita($a['vet']);
        $trabajos = [];
        for ($i = 0; $i < 6; $i++) {
            $trabajos[] = ['--worker-consulta', ['usuario' => $a['usuario'], 'id_cita' => (string) $idCita,
                'datos' => ['diagnostico' => "diag-$i"] + $datosValidos]];
        }
        $resultados = lanzarEnParalelo($trabajos);
        $exitos = count(array_filter($resultados, fn($r) => $r['success']));
        assert($exitos === 1, "Debia haber 1 exito y hubo $exitos");
        assert($nConsultas($idCita) === 1, 'Una sola consulta en la base');
        assert($estadoCita($idCita) === 'Atendida', 'La cita quedo Atendida');
    });

    runTest('Concurrencia: cancelar y registrar a la vez nunca dejan la cita en un estado inconsistente (8 rondas)', function () use ($crearCita, $a, $datosValidos, $estadoCita, $nConsultas) {
        // La consulta hace mas trabajo antes de bloquear, asi que sin retraso casi siempre pierde.
        // Se retrasa la cancelacion (0 a 150 ms) para que gane cada una en distintas rondas.
        $retrasos = [0, 5, 10, 20, 35, 60, 100, 150];
        $hubo = ['Atendida' => 0, 'Cancelada' => 0];
        foreach ($retrasos as $i => $retraso) {
            $ronda = $i + 1;
            $idCita = $crearCita($a['vet']);
            $trabajos = [
                ['--worker-consulta', ['usuario' => $a['usuario'], 'id_cita' => (string) $idCita, 'datos' => $datosValidos]],
                ['--worker-consulta', ['usuario' => $a['usuario'], 'id_cita' => (string) $idCita, 'datos' => $datosValidos]],
                ['--worker-cancelar', ['id_cita' => (string) $idCita, 'retraso_ms' => $retraso]],
                ['--worker-cancelar', ['id_cita' => (string) $idCita, 'retraso_ms' => $retraso]],
            ];
            $res = lanzarEnParalelo($trabajos);
            $exitos = count(array_filter($res, fn($r) => $r['success']));
            $estado = $estadoCita($idCita);
            $n = $nConsultas($idCita);

            assert($exitos === 1, "Ronda $ronda: debia ganar exactamente una operacion y ganaron $exitos");
            $consistente = ($estado === 'Atendida' && $n === 1) || ($estado === 'Cancelada' && $n === 0);
            assert($consistente, "Ronda $ronda: estado inconsistente ($estado, $n consultas)");
            $hubo[$estado]++;
        }
        echo "(ganaron: {$hubo['Atendida']} consultas, {$hubo['Cancelada']} cancelaciones) ";
    });

} finally {
    // Limpieza (los DELETE respetan el orden de las claves foráneas RESTRICT)
    if (isset($ids['mascota'])) {
        foreach (['consultas_veterinarias', 'citas', 'historial_mascota'] as $tabla) {
            $pdo->prepare("DELETE FROM $tabla WHERE id_mascota = ?")->execute([$ids['mascota']]);
        }
        $pdo->prepare("DELETE FROM mascotas WHERE id_mascota = ?")->execute([$ids['mascota']]);
    }
    if (isset($ids['prop']))     { $pdo->prepare("DELETE FROM propietarios WHERE id_propietario = ?")->execute([$ids['prop']]); }
    if (isset($ids['servicio'])) { $pdo->prepare("DELETE FROM servicios WHERE id_servicio = ?")->execute([$ids['servicio']]); }
    foreach ($ids['vets'] ?? [] as $v)     { $pdo->prepare("DELETE FROM veterinarios WHERE id_veterinario = ?")->execute([$v]); }
    foreach ($ids['usuarios'] ?? [] as $u) { $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$u]); }
}

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
