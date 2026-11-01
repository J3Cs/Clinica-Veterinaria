<?php
/**
 * Suite de Pruebas Automatizadas: US-12 (RF12) — Búsqueda, Mantenimiento y Política de Baja (T-076 a T-081, T-090)
 * Cumple con TEC-01: PHP Nativo con assert(), sin Composer ni dependencias externas.
 * Valida reglas: ARQ-01, UNI-01, DAT-01, BAJA-01, ACC-01, SEC-01
 *
 * Ejecución:
 *   docker exec -i php_mvc_dual php /var/www/html/../pruebas/test_mantenimiento.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-12 (RF12)\n";
echo " Modulo: Busqueda, Mantenimiento y Politica de Baja\n";
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
use App\Services\VeterinariosService;
use App\Services\AuthService;
use App\Repositories\PropietarioRepository;
use App\Repositories\MascotaRepository;
use App\Repositories\VeterinarioRepository;
use App\Repositories\UsuarioRepository;
use App\Models\PropietarioModel;
use App\Models\MascotaModel;
use App\Models\VeterinarioModel;
use App\Models\UsuarioModel;

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
// Dobles de Prueba en Memoria para Propietarios, Mascotas, Citas,
// Consultas, Historial, Usuarios y Veterinarios (T-076 a T-090)
// ────────────────────────────────────────────────────────────────── //

class InMemoryClinicalStore {
    public array $propietarios = [];
    public array $mascotas     = [];
    public array $citas        = [];
    public array $consultas    = [];
    public array $historial    = [];
    public array $usuarios     = [];
    public array $veterinarios = [];
}

class MockPropietarioModelUS12 extends PropietarioModel {
    public function __construct(private InMemoryClinicalStore $store) {}

    public function insert(array $data): int {
        $id = count($this->store->propietarios) + 1;
        while (isset($this->store->propietarios[$id])) {
            $id++;
        }
        $data['id_propietario'] = $id;
        $data['activo']         = $data['activo'] ?? 1;
        $data['fecha_registro'] = date('Y-m-d H:i:s');
        $this->store->propietarios[$id] = $data;
        return $id;
    }

    public function update(int $id, array $data): bool {
        if (!isset($this->store->propietarios[$id])) {
            return false;
        }
        foreach (['dui', 'nombre_completo', 'direccion', 'telefono', 'correo'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $this->store->propietarios[$id][$campo] = $data[$campo];
            }
        }
        return true;
    }

    public function findById(int $id): ?array {
        if (!isset($this->store->propietarios[$id])) {
            return null;
        }
        $p = $this->store->propietarios[$id];
        $rel = $this->contarRelaciones($id);
        return array_merge($p, $rel);
    }

    public function findByDui(string $dui): ?array {
        foreach ($this->store->propietarios as $id => $p) {
            if ($p['dui'] === $dui) {
                return $this->findById((int)$id);
            }
        }
        return null;
    }

    public function existsDui(string $dui, ?int $excludeId = null): bool {
        foreach ($this->store->propietarios as $p) {
            if ($p['dui'] === $dui) {
                if ($excludeId !== null && (int)$p['id_propietario'] === $excludeId) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    public function findAll(bool $onlyActive = true): array {
        return $this->search(null, $onlyActive);
    }

    public function search(?string $termino = null, bool $onlyActive = true): array {
        $res = [];
        $q = mb_strtolower(trim((string)$termino));
        foreach ($this->store->propietarios as $id => $p) {
            if ($onlyActive && (int)($p['activo'] ?? 1) !== 1) {
                continue;
            }
            if ($q !== '') {
                $haystack = mb_strtolower(
                    ($p['nombre_completo'] ?? '') . ' ' .
                    ($p['dui'] ?? '') . ' ' .
                    ($p['telefono'] ?? '') . ' ' .
                    ($p['correo'] ?? '')
                );
                if (!str_contains($haystack, $q)) {
                    continue;
                }
            }
            $res[] = $this->findById((int)$id);
        }
        return $res;
    }

    public function contarRelaciones(int $idPropietario): array {
        $mascotasTotal    = 0;
        $mascotasActivas  = 0;
        $citasTotal       = 0;
        $citasProgramadas = 0;
        $consultasTotal   = 0;
        $historialTotal   = 0;

        foreach ($this->store->mascotas as $m) {
            if ((int)$m['id_propietario'] === $idPropietario) {
                $mascotasTotal++;
                if ((int)($m['activo'] ?? 1) === 1) {
                    $mascotasActivas++;
                }
                $idM = (int)$m['id_mascota'];
                foreach ($this->store->citas as $c) {
                    if ((int)$c['id_mascota'] === $idM) {
                        $citasTotal++;
                        if (($c['estado'] ?? '') === 'Programada') {
                            $citasProgramadas++;
                        }
                    }
                }
                foreach ($this->store->consultas as $cv) {
                    if ((int)$cv['id_mascota'] === $idM) {
                        $consultasTotal++;
                    }
                }
                foreach ($this->store->historial as $hm) {
                    if ((int)$hm['id_mascota'] === $idM) {
                        $historialTotal++;
                    }
                }
            }
        }

        $tieneClinicos = ($citasTotal > 0 || $consultasTotal > 0 || $historialTotal > 0);
        return [
            'mascotas_total'       => $mascotasTotal,
            'mascotas_activas'     => $mascotasActivas,
            'citas_total'          => $citasTotal,
            'citas_programadas'    => $citasProgramadas,
            'consultas_total'      => $consultasTotal,
            'historial_total'      => $historialTotal,
            'tiene_dependencias'   => ($mascotasTotal > 0 || $tieneClinicos),
            'tiene_datos_clinicos' => $tieneClinicos,
        ];
    }

    public function desactivar(int $id): bool {
        if (!isset($this->store->propietarios[$id])) return false;
        $this->store->propietarios[$id]['activo'] = 0;
        return true;
    }

    public function activar(int $id): bool {
        if (!isset($this->store->propietarios[$id])) return false;
        $this->store->propietarios[$id]['activo'] = 1;
        return true;
    }

    public function delete(int $id): bool {
        if (!isset($this->store->propietarios[$id])) return false;
        unset($this->store->propietarios[$id]);
        return true;
    }
}

class MockMascotaModelUS12 extends MascotaModel {
    public function __construct(private InMemoryClinicalStore $store) {}

    public function insert(array $data): int {
        $id = count($this->store->mascotas) + 1;
        while (isset($this->store->mascotas[$id])) {
            $id++;
        }
        $data['id_mascota']     = $id;
        $data['activo']         = $data['activo'] ?? 1;
        $data['fecha_registro'] = date('Y-m-d H:i:s');
        $this->store->mascotas[$id] = $data;
        return $id;
    }

    public function update(int $id, array $data): bool {
        if (!isset($this->store->mascotas[$id])) return false;
        foreach (['id_propietario', 'nombre', 'especie', 'raza', 'sexo', 'fecha_nacimiento', 'caracteristicas_relevantes'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $this->store->mascotas[$id][$campo] = $data[$campo];
            }
        }
        return true;
    }

    public function findById(int $id): ?array {
        if (!isset($this->store->mascotas[$id])) return null;
        $m = $this->store->mascotas[$id];
        $p = $this->store->propietarios[(int)$m['id_propietario']] ?? [];
        $rel = $this->obtenerRelacionesClinicas($id);

        return array_merge($m, [
            'propietario_nombre'    => $p['nombre_completo'] ?? null,
            'propietario_dui'       => $p['dui'] ?? null,
            'propietario_telefono'  => $p['telefono'] ?? null,
            'propietario_correo'    => $p['correo'] ?? null,
            'propietario_direccion' => $p['direccion'] ?? null,
            'propietario_activo'    => $p['activo'] ?? 0,
            'citas_total'           => $rel['citas_total'],
            'citas_programadas'     => $rel['citas_programadas'],
            'consultas_total'       => $rel['consultas_total'],
            'historial_total'       => $rel['historial_total'],
        ]);
    }

    public function findAll(bool $onlyActive = true): array {
        return $this->search(null, $onlyActive, null);
    }

    public function search(?string $termino = null, bool $onlyActive = true, ?int $idPropietario = null): array {
        $res = [];
        $q = mb_strtolower(trim((string)$termino));
        foreach ($this->store->mascotas as $id => $m) {
            $full = $this->findById((int)$id);
            if (!$full) continue;
            if ($onlyActive && ((int)$full['activo'] !== 1 || (int)$full['propietario_activo'] !== 1)) {
                continue;
            }
            if ($idPropietario !== null && (int)$full['id_propietario'] !== $idPropietario) {
                continue;
            }
            if ($q !== '') {
                $haystack = mb_strtolower(
                    ($full['nombre'] ?? '') . ' ' .
                    ($full['especie'] ?? '') . ' ' .
                    ($full['raza'] ?? '') . ' ' .
                    ($full['propietario_nombre'] ?? '') . ' ' .
                    ($full['propietario_dui'] ?? '')
                );
                if (!str_contains($haystack, $q)) {
                    continue;
                }
            }
            $res[] = $full;
        }
        return $res;
    }

    public function findByPropietarioId(int $idPropietario, bool $onlyActive = true): array {
        $res = [];
        foreach ($this->store->mascotas as $id => $m) {
            if ((int)$m['id_propietario'] === $idPropietario) {
                if ($onlyActive && (int)($m['activo'] ?? 1) !== 1) {
                    continue;
                }
                $res[] = $this->findById((int)$id);
            }
        }
        return $res;
    }

    public function obtenerRelacionesClinicas(int $idMascota): array {
        $citas        = array_values(array_filter($this->store->citas, fn($c) => (int)$c['id_mascota'] === $idMascota));
        $consultas    = array_values(array_filter($this->store->consultas, fn($cv) => (int)$cv['id_mascota'] === $idMascota));
        $antecedentes = array_values(array_filter($this->store->historial, fn($hm) => (int)$hm['id_mascota'] === $idMascota));
        $prog         = count(array_filter($citas, fn($c) => ($c['estado'] ?? '') === 'Programada'));

        return [
            'citas_total'        => count($citas),
            'citas_programadas'  => $prog,
            'consultas_total'    => count($consultas),
            'historial_total'    => count($antecedentes),
            'tiene_dependencias' => (count($citas) > 0 || count($consultas) > 0 || count($antecedentes) > 0),
            'citas'              => $citas,
            'consultas'          => $consultas,
            'antecedentes'       => $antecedentes,
        ];
    }

    public function cancelarCitasProgramadas(
        int $idMascota,
        string $motivo = 'Cancelada automáticamente por desactivación de la mascota'
    ): int {
        $canceladas = 0;
        foreach ($this->store->citas as $idCita => $c) {
            if ((int)$c['id_mascota'] === $idMascota && ($c['estado'] ?? '') === 'Programada') {
                $this->store->citas[$idCita]['estado'] = 'Cancelada';
                $this->store->citas[$idCita]['motivo_cancelacion'] = $c['motivo_cancelacion'] ?? $motivo;
                $canceladas++;
            }
        }
        return $canceladas;
    }

    public function cancelarCitasProgramadasPorPropietario(
        int $idPropietario,
        string $motivo = 'Cancelada automáticamente por desactivación del propietario'
    ): int {
        $canceladas = 0;
        foreach ($this->store->mascotas as $idM => $m) {
            if ((int)$m['id_propietario'] === $idPropietario) {
                $canceladas += $this->cancelarCitasProgramadas((int)$idM, $motivo);
            }
        }
        return $canceladas;
    }

    public function desactivar(int $id): bool {
        if (!isset($this->store->mascotas[$id])) return false;
        $this->cancelarCitasProgramadas($id);
        $this->store->mascotas[$id]['activo'] = 0;
        return true;
    }

    public function activar(int $id): bool {
        if (!isset($this->store->mascotas[$id])) return false;
        $this->store->mascotas[$id]['activo'] = 1;
        return true;
    }

    public function desactivarPorPropietario(int $idPropietario): int {
        $this->cancelarCitasProgramadasPorPropietario($idPropietario);
        $count = 0;
        foreach ($this->store->mascotas as $id => $m) {
            if ((int)$m['id_propietario'] === $idPropietario && (int)($m['activo'] ?? 1) === 1) {
                $this->store->mascotas[$id]['activo'] = 0;
                $count++;
            }
        }
        return $count;
    }

    public function delete(int $id): bool {
        if (!isset($this->store->mascotas[$id])) return false;
        unset($this->store->mascotas[$id]);
        return true;
    }
}

class MockUsuarioModelUS12 extends UsuarioModel {
    public function __construct(private InMemoryClinicalStore $store) {}

    public function findByIdentifier(string $identifier): ?array {
        foreach ($this->store->usuarios as $u) {
            if ($u['usuario'] === $identifier || $u['correo'] === $identifier) {
                return $u;
            }
        }
        return null;
    }

    public function findById(int $id): ?array {
        return $this->store->usuarios[$id] ?? null;
    }

    public function setActivo(int $id, int $activo): bool {
        if (!isset($this->store->usuarios[$id])) return false;
        $this->store->usuarios[$id]['activo'] = $activo ? 1 : 0;
        return true;
    }

    public function delete(int $id): bool {
        if (!isset($this->store->usuarios[$id])) return false;
        unset($this->store->usuarios[$id]);
        return true;
    }

    public function existsCorreo(string $correo, ?int $excludeId = null): bool {
        foreach ($this->store->usuarios as $u) {
            if ($u['correo'] === $correo && ($excludeId === null || (int)$u['id_usuario'] !== $excludeId)) {
                return true;
            }
        }
        return false;
    }

    public function existsUsuario(string $usuario, ?int $excludeId = null): bool {
        foreach ($this->store->usuarios as $u) {
            if ($u['usuario'] === $usuario && ($excludeId === null || (int)$u['id_usuario'] !== $excludeId)) {
                return true;
            }
        }
        return false;
    }

    public function update(int $id, array $data): bool {
        if (!isset($this->store->usuarios[$id])) return false;
        foreach (['nombre', 'correo', 'usuario'] as $k) {
            if (isset($data[$k])) {
                $this->store->usuarios[$id][$k] = $data[$k];
            }
        }
        return true;
    }
}

class MockVeterinarioModelUS12 extends VeterinarioModel {
    public function __construct(private InMemoryClinicalStore $store) {}

    public function findById(int $id): ?array {
        if (!isset($this->store->veterinarios[$id])) return null;
        $v = $this->store->veterinarios[$id];
        $u = $this->store->usuarios[(int)$v['id_usuario']] ?? [];
        $rel = $this->contarRelaciones($id);
        return array_merge($v, [
            'nombre'          => $u['nombre'] ?? '',
            'correo'          => $u['correo'] ?? '',
            'usuario'         => $u['usuario'] ?? '',
            'activo'          => $u['activo'] ?? 0,
            'citas_total'     => $rel['citas_total'],
            'consultas_total' => $rel['consultas_total'],
        ]);
    }

    public function contarRelaciones(int $idVeterinario): array {
        $citas     = array_filter($this->store->citas, fn($c) => (int)$c['id_veterinario'] === $idVeterinario);
        $consultas = array_filter($this->store->consultas, fn($cv) => (int)$cv['id_veterinario'] === $idVeterinario);
        return [
            'citas_total'        => count($citas),
            'citas_programadas'  => count(array_filter($citas, fn($c) => ($c['estado'] ?? '') === 'Programada')),
            'consultas_total'    => count($consultas),
            'horarios_total'     => 0,
            'tiene_dependencias' => (count($citas) > 0 || count($consultas) > 0),
        ];
    }

    public function existsLicenciaMedica(string $licencia, ?int $excludeIdVeterinario = null): bool {
        $lic = mb_strtolower(trim($licencia));
        if ($lic === '') {
            return false;
        }
        foreach ($this->store->veterinarios as $id => $v) {
            if (mb_strtolower((string) ($v['licencia_medica'] ?? '')) === $lic) {
                if ($excludeIdVeterinario !== null && (int) ($v['id_veterinario'] ?? $id) === $excludeIdVeterinario) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    public function update(int $idVeterinario, array $data): bool {
        if (!isset($this->store->veterinarios[$idVeterinario])) return false;
        foreach (['especialidad', 'telefono', 'licencia_medica'] as $k) {
            if (array_key_exists($k, $data)) {
                $this->store->veterinarios[$idVeterinario][$k] = $data[$k];
            }
        }
        return true;
    }

    public function cancelarCitasProgramadas(
        int $idVeterinario,
        string $motivo = 'Cancelada automáticamente por desactivación del veterinario'
    ): int {
        $canceladas = 0;
        foreach ($this->store->citas as $idCita => $c) {
            if ((int)$c['id_veterinario'] === $idVeterinario && ($c['estado'] ?? '') === 'Programada') {
                $this->store->citas[$idCita]['estado'] = 'Cancelada';
                $this->store->citas[$idCita]['motivo_cancelacion'] = $c['motivo_cancelacion'] ?? $motivo;
                $canceladas++;
            }
        }
        return $canceladas;
    }

    public function delete(int $idVeterinario): bool {
        if (!isset($this->store->veterinarios[$idVeterinario])) return false;
        unset($this->store->veterinarios[$idVeterinario]);
        return true;
    }
}

// ── Inicialización de escenario clínico ────────────────────────── //
$store = new InMemoryClinicalStore();

// Propietario 1: Con mascota y cita/historial (tiene dependencias)
$store->propietarios[1] = [
    'id_propietario'  => 1,
    'dui'             => '01234567-8',
    'nombre_completo' => 'Carlos Alberto Perez',
    'direccion'       => 'Col. Escalon, San Salvador',
    'telefono'        => '7000-1111',
    'correo'          => 'carlos@clinica.sv',
    'activo'          => 1,
];

// Propietario 2: Sin mascotas ni citas (sin dependencias)
$store->propietarios[2] = [
    'id_propietario'  => 2,
    'dui'             => '09876543-2',
    'nombre_completo' => 'Lucia Fernanda Mendez',
    'direccion'       => 'Santa Tecla',
    'telefono'        => '7222-3333',
    'correo'          => 'lucia@clinica.sv',
    'activo'          => 1,
];

// Mascota 1 (de Propietario 1): Con cita, consulta y antecedente (tiene dependencias)
$store->mascotas[1] = [
    'id_mascota'                 => 1,
    'id_propietario'             => 1,
    'nombre'                     => 'Firulais',
    'especie'                    => 'Canino',
    'raza'                       => 'Labrador',
    'sexo'                       => 'Macho',
    'fecha_nacimiento'           => '2022-03-10',
    'caracteristicas_relevantes' => 'Mancha cafe en oreja derecha',
    'activo'                     => 1,
];

// Mascota 2 (de Propietario 1): Recién creada sin citas ni historial (sin dependencias)
$store->mascotas[2] = [
    'id_mascota'                 => 2,
    'id_propietario'             => 1,
    'nombre'                     => 'Michi Solitario',
    'especie'                    => 'Felino',
    'raza'                       => 'Siames',
    'sexo'                       => 'Hembra',
    'fecha_nacimiento'           => '2024-01-15',
    'caracteristicas_relevantes' => null,
    'activo'                     => 1,
];

// Citas (una Atendida y una Programada), consulta e historial asociados a Mascota 1 y Veterinario 1
$store->citas[10] = [
    'id_cita'        => 10,
    'id_mascota'     => 1,
    'id_veterinario' => 1,
    'id_servicio'    => 1,
    'fecha_cita'     => '2026-10-25',
    'hora_cita'      => '09:00:00',
    'estado'         => 'Atendida',
];

$store->citas[11] = [
    'id_cita'        => 11,
    'id_mascota'     => 1,
    'id_veterinario' => 1,
    'id_servicio'    => 1,
    'fecha_cita'     => '2026-11-10',
    'hora_cita'      => '10:30:00',
    'estado'         => 'Programada',
];

$store->consultas[20] = [
    'id_consulta'    => 20,
    'id_cita'        => 10,
    'id_mascota'     => 1,
    'id_veterinario' => 1,
    'fecha_atencion' => '2026-10-25 09:20:00',
    'diagnostico'    => 'Dermatitis leve',
    'tratamiento'    => 'Shampoo medicado cada 3 dias',
];

$store->historial[30] = [
    'id_historial_mascota'       => 30,
    'id_mascota'                 => 1,
    'condicion_medica'           => 'Alergia alimentaria previa',
    'descripcion_problema'       => 'Sensibilidad al pollo',
    'alergias'                   => 'Pollo',
    'fecha_registro_antecedente' => '2023-05-01',
];

// Veterinario 1 (con citas/consultas) y Veterinario 2 (sin dependencias)
$store->usuarios[101] = [
    'id_usuario' => 101,
    'nombre'     => 'Dra. Laura Mendoza',
    'correo'     => 'laura@clinica.sv',
    'usuario'    => 'dra.mendoza',
    'contrasena' => password_hash('Mendoza#2026', PASSWORD_BCRYPT),
    'rol'        => 'veterinario',
    'activo'     => 1,
];
$store->veterinarios[1] = [
    'id_veterinario'  => 1,
    'id_usuario'      => 101,
    'especialidad'    => 'Medicina General',
    'telefono'        => '7888-9999',
    'licencia_medica' => 'JVPMV-101',
];

$store->usuarios[102] = [
    'id_usuario' => 102,
    'nombre'     => 'Dr. Mario SinCitas',
    'correo'     => 'mario@clinica.sv',
    'usuario'    => 'dr.mario',
    'contrasena' => password_hash('Mario#2026', PASSWORD_BCRYPT),
    'rol'        => 'veterinario',
    'activo'     => 1,
];
$store->veterinarios[2] = [
    'id_veterinario'  => 2,
    'id_usuario'      => 102,
    'especialidad'    => 'Cirugia',
    'telefono'        => '7777-6666',
    'licencia_medica' => 'JVPMV-102',
];

$propRepo    = new PropietarioRepository(new MockPropietarioModelUS12($store));
$mascotaRepo = new MascotaRepository(new MockMascotaModelUS12($store));
$usuarioMod  = new MockUsuarioModelUS12($store);
$usuarioRepo = new UsuarioRepository($usuarioMod);
$vetRepo     = new VeterinarioRepository(new MockVeterinarioModelUS12($store), $usuarioMod);

$service     = new PropietariosMascotasService($propRepo, $mascotaRepo);
$vetService  = new VeterinariosService($vetRepo, $usuarioRepo);
$authService = new AuthService($usuarioRepo);

// ────────────────────────────────────────────────────────────────── //
// PRUEBAS AUTOMATIZADAS US-12
// ────────────────────────────────────────────────────────────────── //

runTest('T-076: Busqueda de propietarios por nombre, DUI (con y sin guion), telefono o correo', function () use ($service) {
    $porNombre = $service->buscarPropietarios('Carlos');
    assert(count($porNombre) === 1 && (int)$porNombre[0]['id_propietario'] === 1, 'Debe hallar a Carlos por nombre');

    $porDuiSinGuion = $service->buscarPropietarios('012345678');
    assert(count($porDuiSinGuion) === 1 && $porDuiSinGuion[0]['dui'] === '01234567-8', 'Debe normalizar y hallar por DUI de 9 digitos');

    $sinCoincidencias = $service->buscarPropietarios('TERMINO-INEXISTENTE-XYZ');
    assert($sinCoincidencias === [], 'Debe retornar arreglo vacio si no hay coincidencias');
});

runTest('T-076: Al cargar una mascota se conoce su propietario y todas las relaciones clinicas asociadas', function () use ($service) {
    $expediente = $service->obtenerMascotaConRelaciones(1);
    assert($expediente !== null, 'Debe cargar la mascota #1');
    assert($expediente['propietario_nombre'] === 'Carlos Alberto Perez', 'Se conoce el nombre del propietario');
    assert($expediente['propietario_dui'] === '01234567-8', 'Se conoce el DUI del propietario');
    assert(isset($expediente['relaciones']), 'Incluye estructura de relaciones clinicas');
    assert($expediente['relaciones']['citas_total'] === 2, 'Registra 2 citas (1 Atendida + 1 Programada)');
    assert($expediente['relaciones']['citas_programadas'] === 1, 'Registra 1 cita programada activa');
    assert($expediente['relaciones']['consultas_total'] === 1, 'Registra 1 consulta veterinaria');
    assert($expediente['relaciones']['historial_total'] === 1, 'Registra 1 antecedente en historial_mascota');
    assert($expediente['relaciones']['tiene_dependencias'] === true, 'Marca tiene_dependencias = true');
});

runTest('T-076 / T-081: Actualizacion de contacto y cambio de DUI respetando unicidad (UNI-01)', function () use ($service) {
    // 1. Intentar cambiar el DUI del Propietario 2 al DUI del Propietario 1 (duplicado)
    $falloDuplicado = $service->actualizarPropietario(2, [
        'nombre_completo' => 'Lucia Fernanda Mendez',
        'dui'             => '01234567-8', // Ya pertenece al Propietario 1
        'telefono'        => '7555-9999',
        'correo'          => 'lucia.nueva@clinica.sv',
        'direccion'       => 'Antiguo Cuscatlan',
    ]);
    assert($falloDuplicado['success'] === false, 'Debe rechazar DUI duplicado de otro propietario');
    assert(isset($falloDuplicado['errors']['dui']), 'Debe señalar error en el campo dui');

    // 2. Actualizar contacto y DUI del Propietario 2 a un DUI válido y libre
    $exito = $service->actualizarPropietario(2, [
        'nombre_completo' => 'Lucia Fernanda Mendez Actualizada',
        'dui'             => '055555555', // Se normaliza a 05555555-5
        'telefono'        => '7555-9999',
        'correo'          => 'lucia.nueva@clinica.sv',
        'direccion'       => 'Antiguo Cuscatlan',
    ]);
    assert($exito['success'] === true, 'Debe permitir actualizar contacto y DUI libre');

    $prop2 = $service->obtenerPropietarioPorId(2);
    assert($prop2['dui'] === '05555555-5', 'El DUI debe haberse actualizado y normalizado');
    assert($prop2['telefono'] === '7555-9999', 'El telefono debe haberse actualizado');
    assert($prop2['correo'] === 'lucia.nueva@clinica.sv', 'El correo debe haberse actualizado');
});

runTest('T-076 / T-081: Actualizacion de datos de una mascota existente manteniendo integridad (DAT-01)', function () use ($service) {
    $res = $service->actualizarMascota(1, [
        'id_propietario'             => 1,
        'nombre'                     => 'Firulais Campeon',
        'especie'                    => 'Canino',
        'raza'                       => 'Labrador Retriever',
        'sexo'                       => 'Macho',
        'fecha_nacimiento'           => '2022-03-10',
        'caracteristicas_relevantes' => 'Vacunacion al dia, collar azul',
    ]);
    assert($res['success'] === true, 'Actualizacion de mascota debe ser exitosa');

    $m = $service->obtenerMascotaPorId(1);
    assert($m['nombre'] === 'Firulais Campeon', 'Nombre actualizado');
    assert($m['raza'] === 'Labrador Retriever', 'Raza actualizada');
});

runTest('T-077 / T-081: Eliminacion fisica de mascota SIN dependencias clinicas', function () use ($service, $store) {
    // Mascota 2 no tiene citas, consultas ni historial
    $res = $service->darDeBajaMascota(2);
    assert($res['success'] === true, 'Operacion exitosa');
    assert($res['tipo_baja'] === 'fisica', 'Al no tener relaciones clinicas debe ser eliminacion fisica');
    assert(!isset($store->mascotas[2]), 'La mascota #2 ya no debe existir en la tabla mascotas');
});

runTest('T-077 / T-081 / T-090: Baja logica de mascota CON dependencias cancela citas programadas y preserva historial', function () use ($service, $store) {
    // Mascota 1 tiene cita #10 (Atendida), cita #11 (Programada), consulta #20 e historial #30
    assert($store->citas[11]['estado'] === 'Programada', 'Antes de la baja, la cita #11 esta Programada');
    $res = $service->darDeBajaMascota(1);
    assert($res['success'] === true, 'Operacion exitosa');
    assert($res['tipo_baja'] === 'logica', 'Con relaciones clinicas debe aplicar baja logica');
    assert(isset($store->mascotas[1]), 'El registro de la mascota #1 se conserva en la BD');
    assert((int)$store->mascotas[1]['activo'] === 0, 'La mascota #1 queda con activo = 0');

    // Verificar que las citas en estado Programada se cancelan automáticamente y las Atendidas quedan intactas
    assert(isset($store->citas[10]) && $store->citas[10]['estado'] === 'Atendida', 'La cita #10 Atendida se preserva intacta');
    assert(isset($store->citas[11]) && $store->citas[11]['estado'] === 'Cancelada', 'La cita #11 Programada pasa automaticamente a Cancelada');
    assert(isset($store->consultas[20]), 'La consulta #20 se preserva intacta');
    assert(isset($store->historial[30]), 'El historial #30 se preserva intacto');

    // Verificar T-090: el listado operativo por defecto excluye inactivas
    $activas = $service->listarMascotas(true);
    assert(count($activas) === 0, 'El listado operativo filtra la mascota inactiva por defecto');

    // Reactivar mascota #1 y agregar una nueva cita Programada (#12) para probar la baja en cascada desde el propietario
    $react = $service->reactivarMascota(1);
    assert($react['success'] === true && (int)$store->mascotas[1]['activo'] === 1, 'Mascota #1 reactivada');
    $store->citas[12] = [
        'id_cita'        => 12,
        'id_mascota'     => 1,
        'id_veterinario' => 1,
        'id_servicio'    => 1,
        'fecha_cita'     => '2026-11-15',
        'hora_cita'      => '11:00:00',
        'estado'         => 'Programada',
    ];
});

runTest('T-077 / T-081: Eliminacion fisica de propietario SIN mascotas ni dependencias', function () use ($service, $store) {
    // Propietario 2 no tiene mascotas ni citas
    $res = $service->darDeBajaPropietario(2);
    assert($res['success'] === true, 'Operacion exitosa');
    assert($res['tipo_baja'] === 'fisica', 'Sin dependencias debe ser eliminacion fisica');
    assert(!isset($store->propietarios[2]), 'El propietario #2 fue eliminado fisicamente');
});

runTest('T-077 / T-081 / T-090: Baja logica de propietario CON dependencias desactiva mascotas, cancela citas programadas y conserva historial', function () use ($service, $store) {
    // Propietario 1 tiene a la Mascota 1 (activa) con cita #12 Programada y datos clínicos
    assert($store->citas[12]['estado'] === 'Programada', 'Cita #12 esta Programada antes de desactivar propietario');
    $res = $service->darDeBajaPropietario(1);
    assert($res['success'] === true, 'Operacion exitosa');
    assert($res['tipo_baja'] === 'logica', 'Con mascotas/citas debe aplicar baja logica');
    assert(isset($store->propietarios[1]) && (int)$store->propietarios[1]['activo'] === 0, 'Propietario #1 desactivado logicamente');
    assert((int)$store->mascotas[1]['activo'] === 0, 'Las mascotas asociadas al propietario tambien se desactivan');
    assert($store->citas[12]['estado'] === 'Cancelada', 'Las citas programadas de sus mascotas se cancelan automaticamente');
    assert($store->citas[10]['estado'] === 'Atendida', 'Las citas ya atendidas se conservan en estado Atendida');
    assert(isset($store->citas[10]) && isset($store->consultas[20]) && isset($store->historial[30]), 'Citas e historial clinico intactos');

    // Listado operativo de propietarios excluye inactivos por defecto (T-090)
    $propietariosOperativos = $service->listarPropietarios(true);
    assert(count($propietariosOperativos) === 0, 'Listado operativo de propietarios excluye inactivos por defecto');
});

runTest('T-077: Veterinario con citas/consultas se desactiva logicamente y NO puede iniciar sesion', function () use ($vetService, $authService, $store) {
    // Veterinario 1 tiene cita #10 y consulta #20
    $res = $vetService->darDeBajaVeterinario(1);
    assert($res['success'] === true, 'Baja de veterinario exitosa');
    assert($res['tipo_baja'] === 'logica', 'Debe ser baja logica porque tiene citas y consultas');
    assert((int)$store->usuarios[101]['activo'] === 0, 'Su cuenta de usuario pasa a activo = 0');
    assert(isset($store->citas[10]) && isset($store->consultas[20]), 'Sus citas y consultas se preservan');

    // Comprobar criterio T-077: "los veterinarios desactivados no pueden iniciar sesión"
    $loginBool = $authService->login('dra.mendoza', 'Mendoza#2026');
    $authRes   = $authService->authenticate('dra.mendoza', 'Mendoza#2026');
    assert($loginBool === false && $authRes['success'] === false, 'El veterinario desactivado NO puede iniciar sesion');
    assert(str_contains(mb_strtolower($authRes['message']), 'inactiva'), 'El mensaje informa que la cuenta esta inactiva');
});

runTest('T-077: Veterinario SIN dependencias clinicas se elimina fisicamente', function () use ($vetService, $store) {
    // Veterinario 2 no tiene citas ni consultas
    $res = $vetService->darDeBajaVeterinario(2);
    assert($res['success'] === true, 'Operacion exitosa');
    assert($res['tipo_baja'] === 'fisica', 'Sin citas ni consultas debe eliminarse fisicamente');
    assert(!isset($store->veterinarios[2]) && !isset($store->usuarios[102]), 'Veterinario y usuario eliminados fisicamente');
});

runTest('T-077 / T-078: Edicion de datos profesionales de veterinario y reactivacion de cuenta', function () use ($vetService, $authService, $store) {
    $upd = $vetService->actualizarVeterinario(1, [
        'nombre'          => 'Dra. Laura Mendoza Especialista',
        'correo'          => 'laura@clinica.sv',
        'usuario'         => 'dra.mendoza',
        'especialidad'    => 'Dermatologia Veterinaria',
        'telefono'        => '7999-0000',
        'licencia_medica' => 'JVPMV-101-A',
    ]);
    assert($upd['success'] === true, 'Edicion de veterinario exitosa');
    assert($store->veterinarios[1]['especialidad'] === 'Dermatologia Veterinaria', 'Especialidad actualizada');

    $react = $vetService->reactivarVeterinario(1);
    assert($react['success'] === true && (int)$store->usuarios[101]['activo'] === 1, 'Veterinario reactivado');
    assert($authService->login('dra.mendoza', 'Mendoza#2026') === true, 'Tras reactivar puede iniciar sesion nuevamente');
});

runTest('T-076 / T-077 / T-090: Integracion PDO real contra esquema MySQL (con rollback)', function () {
    $pdo = \App\Config\Database::getConnection();
    // Asegurar que cualquier mascota ya inactiva en BD no conserve citas Programadas huérfanas
    $pdo->exec(
        "UPDATE citas c
     INNER JOIN mascotas m ON m.id_mascota = c.id_mascota
            SET c.estado = 'Cancelada',
                c.motivo_cancelacion = COALESCE(c.motivo_cancelacion, 'Cancelada automáticamente por desactivación de la mascota')
          WHERE m.activo = 0
            AND c.estado = 'Programada'"
    );

    $pdo->beginTransaction();
    try {
        $realPropRepo = new PropietarioRepository(new PropietarioModel());
        $realMascRepo = new MascotaRepository(new MascotaModel());
        $realServ     = new PropietariosMascotasService($realPropRepo, $realMascRepo);

        // 1. Crear propietario temporal y mascota temporal sin citas
        $resP = $realServ->registrarPropietario([
            'nombre_completo' => 'Propietario Test Integracion US12',
            'dui'             => '98765430-1',
            'telefono'        => '7111-2222',
            'correo'          => 'integracion.us12@clinica.sv',
            'direccion'       => 'San Salvador',
        ]);
        assert($resP['success'] === true, 'Insercion real de propietario exitosa');
        $idProp = (int)$resP['id'];

        $busqP = $realServ->buscarPropietarios('987654301', true);
        assert(count($busqP) === 1 && (int)$busqP[0]['id_propietario'] === $idProp, 'Busqueda PDO real por DUI normalizado');

        $updP = $realServ->actualizarPropietario($idProp, [
            'nombre_completo' => 'Propietario Test Editado US12',
            'dui'             => '98765430-2',
            'telefono'        => '7999-8888',
            'correo'          => 'editado.us12@clinica.sv',
            'direccion'       => 'Santa Tecla',
        ]);
        assert($updP['success'] === true, 'Actualizacion PDO real de propietario exitosa');

        $resM = $realServ->registrarMascota([
            'id_propietario'             => $idProp,
            'nombre'                     => 'Mascota Test US12',
            'especie'                    => 'Canino',
            'raza'                       => 'Mestizo',
            'sexo'                       => 'Macho',
            'fecha_nacimiento'           => '2024-02-01',
            'caracteristicas_relevantes' => 'Prueba SQL real',
        ]);
        assert($resM['success'] === true, 'Insercion real de mascota exitosa');
        $idMasc = (int)$resM['id'];

        $expM = $realServ->obtenerMascotaConRelaciones($idMasc);
        assert($expM !== null && $expM['propietario_dui'] === '98765430-2', 'Lectura real de mascota con su propietario y relaciones');

        // Baja de propietario con mascota asociada -> baja lógica de propietario y mascota
        $bajaLogica = $realServ->darDeBajaPropietario($idProp);
        assert($bajaLogica['success'] === true && $bajaLogica['tipo_baja'] === 'logica', 'Con mascota asociada aplica baja logica en MySQL');

        // Baja de mascota sin citas -> eliminacion fisica
        $bajaFisicaM = $realServ->darDeBajaMascota($idMasc);
        assert($bajaFisicaM['success'] === true && $bajaFisicaM['tipo_baja'] === 'fisica', 'Mascota sin citas se elimina fisicamente en MySQL');

        // Ahora el propietario ya no tiene mascotas ni citas -> eliminacion fisica
        $bajaFisicaP = $realServ->darDeBajaPropietario($idProp);
        assert($bajaFisicaP['success'] === true && $bajaFisicaP['tipo_baja'] === 'fisica', 'Propietario sin dependencias se elimina fisicamente en MySQL');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
});

echo "\n--------------------------------------------------------\n";
echo sprintf(" Resumen: %d de %d pruebas superadas (%d%%).\n", $passedTests, $totalTests, $totalTests > 0 ? (int) round($passedTests / $totalTests * 100) : 0);
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
