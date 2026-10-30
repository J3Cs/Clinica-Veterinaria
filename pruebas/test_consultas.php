<?php
/**
 * Suite de Pruebas Automatizadas: US-06 (RF06) — Consultas veterinarias (T-048)
 * Cumple con TEC-01: PHP nativo con assert(), sin Composer ni dependencias externas.
 * Valida reglas: ARQ-01, DAT-01, SEC-01, ACC-01
 *
 * No necesita base de datos (dobles en memoria que además simulan el rollback).
 * Para probar contra MySQL real, incluida la concurrencia, ver test_consultas_integracion.php.
 *
 * Ejecución local:
 *   php -d zend.assertions=1 pruebas/test_consultas.php
 */

declare(strict_types=1);

ini_set('assert.exception', '1');
ini_set('error_log', sys_get_temp_dir() . '/test_consultas_errores.log');   // el servicio registra los fallos

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-06 (RF06)\n";
echo " Modulo: Consultas veterinarias\n";
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
use App\Models\ConsultaVeterinariaModel;
use App\Models\HistorialMascotaModel;
use App\Models\VeterinarioModel;
use App\Repositories\CitaRepository;
use App\Repositories\ConsultaVeterinariaRepository;
use App\Repositories\HistorialMascotaRepository;
use App\Repositories\VeterinarioRepository;
use App\Services\ConsultaService;

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

/** Estado compartido: así la "transacción" puede deshacer citas Y consultas. */
class TestStoreUS6 {
    public array $citas = [];
    public array $consultas = [];
    public array $antecedentes = [];
    public int $auto = 1;
    public array $eventos = [];
    /** Cambios de estado hechos por la transacción en curso: [id_cita, estado_anterior]. */
    public array $deshacer = [];
}

class TestCitaModelMockUS6 extends CitaModel {
    public ?Closure $alBloquear = null;   // simula que otra operación termina justo antes del bloqueo
    public ?Closure $alMarcar = null;     // simula un cambio concurrente justo antes del UPDATE
    public bool $fallaAlMarcar = false;

    public function __construct(private TestStoreUS6 $st) {}

    private function conDetalle(array $c): array {
        return $c + [
            'mascota_nombre' => 'Rocky', 'mascota_especie' => 'Perro', 'mascota_raza' => 'Criollo',
            'mascota_sexo' => 'Macho', 'mascota_fecha_nacimiento' => null, 'mascota_caracteristicas' => null,
            'propietario_nombre' => 'Ana', 'propietario_telefono' => '7000-0000',
            'nombre_servicio' => 'Consulta general', 'duracion_estimada_min' => 30,
            'veterinario_nombre' => 'Dra. Laura', 'motivo_cancelacion' => null,
        ];
    }

    public function findDetalleById(int $id): ?array {
        return isset($this->st->citas[$id]) ? $this->conDetalle($this->st->citas[$id]) : null;
    }

    public function findAllDetalle(?string $estado = null, int $limite = 200, ?int $idVeterinario = null): array {
        $res = array_filter($this->st->citas, fn($c) => ($estado === null || $c['estado'] === $estado)
            && ($idVeterinario === null || (int) $c['id_veterinario'] === $idVeterinario));
        usort($res, fn($a, $b) => [$a['fecha_cita'], $a['hora_cita'], $a['id_cita']] <=> [$b['fecha_cita'], $b['hora_cita'], $b['id_cita']]);
        return array_map(fn($c) => $this->conDetalle($c), array_slice($res, 0, $limite));
    }

    public function bloquearPorId(int $id): ?array {
        $this->st->eventos[] = 'LOCK_CITA';
        if ($this->alBloquear) {
            ($this->alBloquear)($this->st);
        }
        return $this->st->citas[$id] ?? null;
    }

    public function marcarAtendidaSiProgramada(int $id): int {
        $this->st->eventos[] = 'MARCAR';
        if ($this->alMarcar) {
            ($this->alMarcar)($this->st);
        }
        if ($this->fallaAlMarcar) {
            throw new PDOException('fallo simulado al cambiar el estado');
        }
        $c = $this->st->citas[$id] ?? null;
        if (!$c || $c['estado'] !== 'Programada') {
            return 0;
        }
        $this->st->deshacer[] = [$id, $c['estado']];
        $this->st->citas[$id]['estado'] = 'Atendida';
        return 1;
    }

    public function transaccion(callable $fn): mixed {
        $this->st->eventos[] = 'BEGIN';
        $consultas = $this->st->consultas;
        $this->st->deshacer = [];
        try {
            $r = $fn();
            $this->st->eventos[] = 'COMMIT';
            return $r;
        } catch (\Throwable $e) {
            // ROLLBACK: solo se deshace lo que hizo ESTA transaccion. Lo que otras
            // operaciones ya confirmaron (p. ej. una cancelacion) permanece, como en MySQL.
            foreach (array_reverse($this->st->deshacer) as [$idCita, $anterior]) {
                $this->st->citas[$idCita]['estado'] = $anterior;
            }
            $this->st->consultas = $consultas;
            $this->st->eventos[] = 'ROLLBACK';
            throw $e;
        }
    }
}

class TestConsultaModelMockUS6 extends ConsultaVeterinariaModel {
    public bool $falla = false;
    public bool $forzarDuplicado = false;

    public function __construct(private TestStoreUS6 $st) {}

    public function insert(array $data): int {
        $this->st->eventos[] = 'INSERT_CONSULTA';
        if (trim((string) ($data['diagnostico'] ?? '')) === '' || trim((string) ($data['tratamiento'] ?? '')) === '') {
            throw new InvalidArgumentException('Diagnóstico y tratamiento son obligatorios.');
        }
        if ($this->falla) {
            throw new PDOException('fallo simulado al insertar');
        }
        foreach ($this->st->consultas as $c) {
            if ((int) $c['id_cita'] === (int) $data['id_cita'] || $this->forzarDuplicado) {
                $e = new PDOException('Duplicate entry');
                $e->errorInfo = ['23000', 1062, 'Duplicate entry'];
                throw $e;
            }
        }
        if ($this->forzarDuplicado) {
            $e = new PDOException('Duplicate entry');
            $e->errorInfo = ['23000', 1062, 'Duplicate entry'];
            throw $e;
        }
        $id = $this->st->auto++;
        $this->st->consultas[$id] = $data + ['id_consulta' => $id];
        return $id;
    }

    public function findByCitaId(int $idCita): ?array {
        foreach ($this->st->consultas as $c) {
            if ((int) $c['id_cita'] === $idCita) {
                return $c;
            }
        }
        return null;
    }

    public function findByMascota(int $idMascota, int $limite = 50): array {
        $res = array_filter($this->st->consultas, fn($c) => (int) $c['id_mascota'] === $idMascota);
        usort($res, fn($a, $b) => strcmp((string) $b['fecha_atencion'], (string) $a['fecha_atencion']));
        return array_map(fn($c) => $c + ['veterinario_nombre' => 'Dra. Laura', 'nombre_servicio' => 'Consulta general'],
            array_slice($res, 0, $limite));
    }
}

class TestHistorialModelMockUS6 extends HistorialMascotaModel {
    public function __construct(private TestStoreUS6 $st) {}
    public function findByMascota(int $idMascota, int $limite = 50): array {
        return array_slice(array_values(array_filter($this->st->antecedentes, fn($a) => (int) $a['id_mascota'] === $idMascota)), 0, $limite);
    }
}

class TestVeterinarioModelMockUS6 extends VeterinarioModel {
    /** @param array<int,array> $porUsuario id_usuario => perfil activo */
    public function __construct(private array $porUsuario) {}
    public function findActivoByUsuarioId(int $idUsuario): ?array { return $this->porUsuario[$idUsuario] ?? null; }
}

// ────────────────────────────────────────────────────────────────── //
// Escenario
// ────────────────────────────────────────────────────────────────── //

const U_LAURA = 100;      // usuario de la Dra. Laura  -> veterinario 10
const U_CARLOS = 101;     // usuario del Dr. Carlos    -> veterinario 11
const U_INACTIVO = 102;   // veterinario con cuenta inactiva (no tiene perfil activo)
const U_SIN_PERFIL = 103; // usuario con rol veterinario pero sin fila en `veterinarios`
const VET_LAURA = 10;
const VET_CARLOS = 11;

$AHORA = new DateTimeImmutable('2030-03-04 10:30:00');

function cita(int $id, int $vet, string $estado, string $fecha = '2030-03-05', string $hora = '09:00:00', int $mascota = 1): array {
    return ['id_cita' => $id, 'id_mascota' => $mascota, 'id_veterinario' => $vet, 'id_servicio' => 1,
            'fecha_cita' => $fecha, 'hora_cita' => $hora, 'estado' => $estado];
}

/** @return array{0:ConsultaService,1:TestStoreUS6,2:TestCitaModelMockUS6,3:TestConsultaModelMockUS6} */
function crearEscenario(): array {
    $st = new TestStoreUS6();
    $st->citas = [
        1 => cita(1, VET_LAURA,  'Programada'),
        2 => cita(2, VET_LAURA,  'Cancelada', '2030-03-05', '10:00:00'),
        3 => cita(3, VET_LAURA,  'Atendida',  '2030-03-05', '11:00:00'),
        4 => cita(4, VET_CARLOS, 'Programada', '2030-03-05', '09:00:00', 2),
    ];
    $citaModel     = new TestCitaModelMockUS6($st);
    $consultaModel = new TestConsultaModelMockUS6($st);
    $servicio = new ConsultaService(
        new CitaRepository($citaModel),
        new ConsultaVeterinariaRepository($consultaModel),
        new VeterinarioRepository(new TestVeterinarioModelMockUS6([
            U_LAURA  => ['id_veterinario' => VET_LAURA,  'nombre' => 'Dra. Laura'],
            U_CARLOS => ['id_veterinario' => VET_CARLOS, 'nombre' => 'Dr. Carlos'],
        ])),
        new HistorialMascotaRepository(new TestHistorialModelMockUS6($st))
    );
    return [$servicio, $st, $citaModel, $consultaModel];
}

function datos(array $extra = []): array {
    return $extra + [
        'diagnostico' => 'Otitis externa', 'tratamiento' => 'Gotas óticas cada 12 h por 7 días',
        'observaciones' => 'Prurito moderado', 'servicios_adicionales' => 'Limpieza ótica',
    ];
}

function sinCambios(TestStoreUS6 $st, int $idCita = 1, string $estado = 'Programada'): void {
    assert($st->consultas === [], 'No debe existir ninguna consulta');
    assert($st->citas[$idCita]['estado'] === $estado, "La cita $idCita debe seguir $estado");
}

// ────────────────────────────────────────────────────────────────── //
// Pruebas
// ────────────────────────────────────────────────────────────────── //

runTest('US-06: Consulta valida — se guarda y la cita pasa a Atendida en la misma transaccion', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $r = $s->registrarConsulta(U_LAURA, '1', datos(), $AHORA);
    assert($r['success'] === true, 'Debe guardar: ' . $r['message']);
    assert($r['id'] === 1 && $r['reintentable'] === false, 'Devuelve el id');
    $c = $st->consultas[1];
    assert($c['id_cita'] === 1 && $c['id_mascota'] === 1 && $c['id_veterinario'] === VET_LAURA, 'Cita, mascota y veterinario salen de la cita guardada');
    assert($c['fecha_atencion'] === '2030-03-04 10:30:00', 'Fecha de atencion = ahora');
    assert($c['diagnostico'] === 'Otitis externa' && $c['tratamiento'] === 'Gotas óticas cada 12 h por 7 días', 'Textos guardados');
    assert($st->citas[1]['estado'] === 'Atendida', 'La cita queda Atendida');
    assert($st->eventos === ['BEGIN', 'LOCK_CITA', 'INSERT_CONSULTA', 'MARCAR', 'COMMIT'], 'Orden: ' . implode(',', $st->eventos));
});

runTest('US-06: Opcionales — vacios o solo espacios se guardan como NULL; los textos se normalizan', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $r = $s->registrarConsulta(U_LAURA, 1, [
        'diagnostico' => "  Otitis\r\nexterna\x00  ", 'tratamiento' => "\tGotas\x07 ",
        'observaciones' => "   \n ", 'servicios_adicionales' => null,
    ], $AHORA);
    assert($r['success'] === true, 'Debe guardar');
    $c = $st->consultas[1];
    assert($c['diagnostico'] === "Otitis\nexterna", 'Saltos de linea unificados y sin controles: ' . json_encode($c['diagnostico']));
    assert($c['tratamiento'] === 'Gotas', 'Recortado');
    assert($c['observaciones'] === null && $c['servicios_adicionales'] === null, 'Opcionales vacios -> NULL');
});

runTest('US-06: Diagnostico obligatorio — vacio, solo espacios, controles o ausente se rechaza', function () use ($AHORA) {
    foreach (['', '   ', "\x00\x07", null] as $malo) {
        [$s, $st] = crearEscenario();
        $d = datos();
        $d['diagnostico'] = $malo;
        $r = $s->registrarConsulta(U_LAURA, 1, $d, $AHORA);
        assert($r['success'] === false && isset($r['errors']['diagnostico']), 'Debe rechazar diagnostico ' . json_encode($malo));
        assert($r['reintentable'] === true, 'El formulario se puede corregir');
        assert(!in_array('BEGIN', $st->eventos, true), 'Ni siquiera se abre la transaccion');
        sinCambios($st);
    }
});

runTest('US-06: Tratamiento obligatorio — y los errores de ambos campos se informan a la vez', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $r = $s->registrarConsulta(U_LAURA, 1, datos(['tratamiento' => '  ']), $AHORA);
    assert($r['success'] === false && isset($r['errors']['tratamiento']) && !isset($r['errors']['diagnostico']), 'Solo tratamiento');
    sinCambios($st);

    $r = $s->registrarConsulta(U_LAURA, 1, ['observaciones' => 'x'], $AHORA);
    assert(isset($r['errors']['diagnostico'], $r['errors']['tratamiento']), 'Ambos errores a la vez');
    sinCambios($st);
});

runTest('US-06: Longitud y tipo — maximo 2000 caracteres, texto UTF-8 valido', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    foreach (['diagnostico', 'tratamiento', 'observaciones', 'servicios_adicionales'] as $campo) {
        $r = $s->registrarConsulta(U_LAURA, 1, datos([$campo => str_repeat('a', 2001)]), $AHORA);
        assert($r['success'] === false && isset($r['errors'][$campo]), "2001 caracteres en $campo");
    }
    foreach ([['x'], 12, "\xC3\x28"] as $malo) {   // arreglo, numero, UTF-8 invalido
        $r = $s->registrarConsulta(U_LAURA, 1, datos(['diagnostico' => $malo]), $AHORA);
        assert($r['success'] === false && isset($r['errors']['diagnostico']), 'Valor no valido rechazado');
    }
    sinCambios($st);

    $r = $s->registrarConsulta(U_LAURA, 1, datos(['diagnostico' => str_repeat('ñ', 2000)]), $AHORA);
    assert($r['success'] === true && mb_strlen($st->consultas[1]['diagnostico']) === 2000, '2000 caracteres multibyte: aceptado');
});

runTest('US-06: Cita Cancelada — se rechaza y no se guarda nada', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $r = $s->registrarConsulta(U_LAURA, 2, datos(), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'cancelada'), 'Rechazo: ' . $r['message']);
    assert($r['reintentable'] === false && !in_array('BEGIN', $st->eventos, true), 'Sin transaccion');
    sinCambios($st, 2, 'Cancelada');
});

runTest('US-06: Cita Atendida — se rechaza y no se guarda nada', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $r = $s->registrarConsulta(U_LAURA, 3, datos(), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'ya fue atendida'), 'Rechazo: ' . $r['message']);
    sinCambios($st, 3, 'Atendida');
});

runTest('US-06: Cita de otro veterinario — se rechaza (solo el veterinario asignado puede atenderla)', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $r = $s->registrarConsulta(U_LAURA, 4, datos(), $AHORA);       // la cita 4 es del Dr. Carlos
    assert($r['success'] === false && str_contains($r['message'], 'no está asignada a tu usuario'), 'Rechazo: ' . $r['message']);
    sinCambios($st, 4);
});

runTest('US-06: Usuario sin perfil de veterinario, inactivo o identificador invalido — se rechaza', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    foreach ([U_INACTIVO, U_SIN_PERFIL, 0, 'abc', null, 999] as $usuario) {
        $r = $s->registrarConsulta($usuario, 1, datos(), $AHORA);
        assert($r['success'] === false && str_contains($r['message'], 'perfil de veterinario'), 'Debe rechazar usuario ' . json_encode($usuario));
    }
    assert(!in_array('BEGIN', $st->eventos, true), 'Nunca se abre la transaccion');
    sinCambios($st);
});

runTest('US-06: Identificador de cita invalido o inexistente — se rechaza sin tocar datos', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    foreach ([0, 'abc', '1e3', '-1', '1.5', '', null, ['1'], '99', 99] as $id) {
        $r = $s->registrarConsulta(U_LAURA, $id, datos(), $AHORA);
        assert($r['success'] === false, 'Debe rechazar id ' . json_encode($id));
    }
    assert(!in_array('BEGIN', $st->eventos, true), 'Nunca se abre la transaccion');
    sinCambios($st);
});

runTest('US-06: Una sola consulta por cita — la segunda se rechaza', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    assert($s->registrarConsulta(U_LAURA, 1, datos(), $AHORA)['success'] === true, 'La primera se guarda');
    $r = $s->registrarConsulta(U_LAURA, 1, datos(['diagnostico' => 'Otro']), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'ya fue atendida'), 'La segunda se rechaza: ' . $r['message']);
    assert(count($st->consultas) === 1 && $st->consultas[1]['diagnostico'] === 'Otitis externa', 'Una sola, la original');

    // Dato inconsistente: cita Programada que ya tiene consulta
    [$s2, $st2] = crearEscenario();
    $st2->consultas[1] = ['id_consulta' => 1, 'id_cita' => 4, 'id_mascota' => 2, 'id_veterinario' => VET_CARLOS,
                          'fecha_atencion' => '2030-01-01 08:00:00', 'diagnostico' => 'Previo', 'tratamiento' => 'Previo'];
    $st2->auto = 2;
    $r = $s2->registrarConsulta(U_CARLOS, 4, datos(), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'Ya existe una consulta'), 'Rechazo: ' . $r['message']);
    assert(count($st2->consultas) === 1 && $st2->citas[4]['estado'] === 'Programada', 'No se duplica ni se cambia el estado');
});

runTest('US-06: Carrera — si la cita se cancela antes del bloqueo, no se guarda la consulta', function () use ($AHORA) {
    [$s, $st, $citaModel] = crearEscenario();
    // La comprobacion previa la ve Programada; justo al bloquear, otra operacion ya la cancelo.
    $citaModel->alBloquear = function (TestStoreUS6 $x) { $x->citas[1]['estado'] = 'Cancelada'; };
    $r = $s->registrarConsulta(U_LAURA, 1, datos(), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'cancelada'), 'Rechazo: ' . $r['message']);
    assert(!in_array('INSERT_CONSULTA', $st->eventos, true), 'No se llego a insertar');
    sinCambios($st, 1, 'Cancelada');
});

runTest('US-06: Fallo transaccional — si falla la INSERCION se revierte todo', function () use ($AHORA) {
    [$s, $st, , $consultaModel] = crearEscenario();
    $consultaModel->falla = true;
    $r = $s->registrarConsulta(U_LAURA, 1, datos(), $AHORA);
    assert($r['success'] === false && $r['reintentable'] === true, 'Falla y se puede reintentar');
    assert(str_contains($r['message'], 'no se realizó ningún cambio'), 'Explica que no hubo cambios: ' . $r['message']);
    assert(in_array('ROLLBACK', $st->eventos, true) && !in_array('COMMIT', $st->eventos, true), 'Hubo ROLLBACK y no COMMIT');
    sinCambios($st);
});

runTest('US-06: Fallo transaccional — si falla el CAMBIO DE ESTADO se revierte la consulta ya insertada', function () use ($AHORA) {
    [$s, $st, $citaModel] = crearEscenario();
    $citaModel->fallaAlMarcar = true;
    $r = $s->registrarConsulta(U_LAURA, 1, datos(), $AHORA);
    assert($r['success'] === false && $r['reintentable'] === true, 'Falla y se puede reintentar');
    assert(in_array('INSERT_CONSULTA', $st->eventos, true) && in_array('ROLLBACK', $st->eventos, true), 'Se inserto y luego se revirtio');
    sinCambios($st);   // ni consulta parcial ni estado inconsistente
});

runTest('US-06: Fallo transaccional — si el UPDATE no afecta filas se revierte la consulta', function () use ($AHORA) {
    [$s, $st, $citaModel] = crearEscenario();
    $citaModel->alMarcar = function (TestStoreUS6 $x) { $x->citas[1]['estado'] = 'Cancelada'; };
    $r = $s->registrarConsulta(U_LAURA, 1, datos(), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'ya no está Programada'), 'Rechazo: ' . $r['message']);
    assert($r['reintentable'] === false, 'No tiene sentido reintentar');
    assert(in_array('ROLLBACK', $st->eventos, true), 'Hubo ROLLBACK');
    sinCambios($st, 1, 'Cancelada');
});

runTest('US-06: Duplicado detectado por la base (UNIQUE) — mensaje claro y sin cambios', function () use ($AHORA) {
    [$s, $st, , $consultaModel] = crearEscenario();
    $consultaModel->forzarDuplicado = true;
    $r = $s->registrarConsulta(U_LAURA, 1, datos(), $AHORA);
    assert($r['success'] === false && str_contains($r['message'], 'Ya existe una consulta'), 'Mensaje: ' . $r['message']);
    assert($r['reintentable'] === false, 'No reintentable');
    sinCambios($st);
});

runTest('US-06: Agenda — el veterinario ve solo SUS citas Programadas, de la mas proxima a la mas lejana', function () {
    [$s, $st] = crearEscenario();
    $st->citas[5] = cita(5, VET_LAURA, 'Programada', '2030-03-04', '15:00:00');   // la mas proxima
    $st->citas[6] = cita(6, VET_LAURA, 'Programada', '2030-03-05', '08:00:00');
    $a = $s->agendaDelVeterinario(U_LAURA);
    assert(array_map('intval', array_column($a['citas'], 'id_cita')) === [5, 6, 1], 'Orden: ' . json_encode(array_column($a['citas'], 'id_cita')));
    assert($a['veterinario']['id_veterinario'] === VET_LAURA && $a['truncado'] === false, 'Perfil y sin truncar');

    $b = $s->agendaDelVeterinario(U_CARLOS);
    assert(array_map('intval', array_column($b['citas'], 'id_cita')) === [4], 'El otro veterinario solo ve la suya');

    foreach ([U_INACTIVO, U_SIN_PERFIL, 0, null, 'x'] as $u) {
        $sin = $s->agendaDelVeterinario($u);
        assert($sin['veterinario'] === null && $sin['citas'] === [], 'Sin perfil activo: agenda vacia');
    }
});

runTest('US-06: Pantalla de consulta — solo para una cita Programada propia', function () {
    [$s] = crearEscenario();
    $ok = $s->prepararConsulta(U_LAURA, '1');
    assert($ok['success'] === true && $ok['cita']['mascota_nombre'] === 'Rocky', 'Se muestra la cita con datos de la mascota');
    assert(is_array($ok['historial']), 'Incluye historial');

    foreach ([[U_LAURA, '2'], [U_LAURA, '3'], [U_LAURA, '4'], [U_LAURA, '99'], [U_LAURA, 'abc'], [U_INACTIVO, '1'], [0, '1']] as [$u, $id]) {
        $r = $s->prepararConsulta($u, $id);
        assert($r['success'] === false && $r['cita'] === null && $r['message'] !== '', "No se prepara para usuario $u, cita $id");
    }
});

runTest('US-06: Historial consolidado — la consulta aparece UNA sola vez junto a los antecedentes, de lo reciente a lo antiguo', function () use ($AHORA) {
    [$s, $st] = crearEscenario();
    $st->antecedentes = [
        ['id_historial_mascota' => 1, 'id_mascota' => 1, 'condicion_medica' => 'Epilepsia', 'descripcion_problema' => 'Crisis ocasionales', 'alergias' => null, 'fecha_registro_antecedente' => '2029-06-01'],
        ['id_historial_mascota' => 2, 'id_mascota' => 1, 'condicion_medica' => 'Alergia alimentaria', 'descripcion_problema' => null, 'alergias' => 'Pollo', 'fecha_registro_antecedente' => null],
        ['id_historial_mascota' => 3, 'id_mascota' => 2, 'condicion_medica' => 'De otra mascota', 'descripcion_problema' => null, 'alergias' => null, 'fecha_registro_antecedente' => '2029-01-01'],
    ];
    assert(count($s->historialClinico(1)) === 2, 'Antes: 2 antecedentes de la mascota 1');

    $s->registrarConsulta(U_LAURA, 1, datos(), $AHORA);

    $h = $s->historialClinico(1);
    assert(count($h) === 3, 'Ahora: 2 antecedentes + 1 consulta = 3, no 4');
    assert($h[0]['tipo'] === 'Consulta' && $h[0]['titulo'] === 'Otitis externa', 'La consulta es lo mas reciente');
    assert($h[1]['tipo'] === 'Antecedente' && $h[1]['titulo'] === 'Epilepsia', 'Luego el antecedente con fecha');
    assert($h[2]['tipo'] === 'Antecedente' && $h[2]['titulo'] === 'Alergia alimentaria', 'Sin fecha va al final');
    assert(count(array_filter($h, fn($e) => $e['tipo'] === 'Consulta')) === 1, 'La consulta aparece una sola vez');
    assert($s->historialClinico(1) == $h, 'Estable si se consulta de nuevo');
    assert(count($s->historialClinico(1, 2)) === 2, 'Respeta el limite');
    assert($s->historialClinico(2)[0]['titulo'] === 'De otra mascota' && count($s->historialClinico(2)) === 1, 'Cada mascota ve lo suyo');
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
