<?php
/**
 * Suite de Pruebas Automatizadas: US-09 (RF09) — Gestión de Veterinarios y Usuarios (T-059 a T-064)
 * Cumple con TEC-01: PHP Nativo con assert(), sin Composer ni dependencias externas.
 * Valida reglas: ARQ-01, TEC-01, UNI-01, DAT-01, BAJA-01, ACC-01, SEC-01, UI-01
 *
 * Ejecución:
 *   docker exec -i php_mvc_dual php /var/www/html/../pruebas/test_veterinarios.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

echo "========================================================\n";
echo " Clinica Veterinaria -- Suite de Pruebas: US-09 (RF09)\n";
echo " Modulo: Gestion de Veterinarios y Cuentas de Usuario\n";
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

use App\Services\VeterinariosService;
use App\Services\AuthService;
use App\Repositories\VeterinarioRepository;
use App\Repositories\UsuarioRepository;
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
// Dobles de Prueba en Memoria para Usuarios, Veterinarios, Citas y
// Consultas con soporte de Rollback Transaccional (T-059 a T-064)
// ────────────────────────────────────────────────────────────────── //

class InMemoryVetStore {
    public array $usuarios     = [];
    public array $veterinarios = [];
    public array $citas        = [];
    public array $consultas    = [];
    public bool $failNextVetInsert = false;
}

class MockUsuarioModelUS09 extends UsuarioModel {
    public function __construct(private InMemoryVetStore $store) {}

    public function insert(array $data): int {
        $id = count($this->store->usuarios) + 100;
        while (isset($this->store->usuarios[$id])) {
            $id++;
        }
        $this->store->usuarios[$id] = [
            'id_usuario'     => $id,
            'nombre'         => $data['nombre'],
            'correo'         => $data['correo'],
            'usuario'        => $data['usuario'],
            'contrasena'     => $data['contrasena'],
            'rol'            => $data['rol'] ?? 'veterinario',
            'activo'         => $data['activo'] ?? 1,
            'fecha_creacion' => date('Y-m-d H:i:s'),
        ];
        return $id;
    }

    public function update(int $id, array $data): bool {
        if (!isset($this->store->usuarios[$id])) {
            return false;
        }
        foreach (['nombre', 'correo', 'usuario'] as $campo) {
            if (isset($data[$campo])) {
                $this->store->usuarios[$id][$campo] = $data[$campo];
            }
        }
        if (!empty($data['contrasena'])) {
            $this->store->usuarios[$id]['contrasena'] = $data['contrasena'];
        }
        return true;
    }

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

    public function existsCorreo(string $correo, ?int $excludeId = null): bool {
        foreach ($this->store->usuarios as $u) {
            if (mb_strtolower($u['correo']) === mb_strtolower($correo)) {
                if ($excludeId !== null && (int) $u['id_usuario'] === $excludeId) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    public function existsUsuario(string $usuario, ?int $excludeId = null): bool {
        foreach ($this->store->usuarios as $u) {
            if (mb_strtolower($u['usuario']) === mb_strtolower($usuario)) {
                if ($excludeId !== null && (int) $u['id_usuario'] === $excludeId) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    public function setActivo(int $id, int $activo): bool {
        if (!isset($this->store->usuarios[$id])) {
            return false;
        }
        $this->store->usuarios[$id]['activo'] = $activo ? 1 : 0;
        return true;
    }

    public function delete(int $id): bool {
        if (!isset($this->store->usuarios[$id])) {
            return false;
        }
        unset($this->store->usuarios[$id]);
        return true;
    }
}

class MockVeterinarioModelUS09 extends VeterinarioModel {
    public function __construct(private InMemoryVetStore $store) {}

    public function transaccion(callable $fn): mixed {
        $snapshotUsuarios     = $this->store->usuarios;
        $snapshotVeterinarios = $this->store->veterinarios;
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->store->usuarios     = $snapshotUsuarios;
            $this->store->veterinarios = $snapshotVeterinarios;
            throw $e;
        }
    }

    public function insert(array $data): int {
        if ($this->store->failNextVetInsert) {
            $this->store->failNextVetInsert = false;
            throw new \RuntimeException('Fallo simulado al insertar ficha de veterinario');
        }
        $id = count($this->store->veterinarios) + 1;
        while (isset($this->store->veterinarios[$id])) {
            $id++;
        }
        $this->store->veterinarios[$id] = [
            'id_veterinario'  => $id,
            'id_usuario'      => (int) $data['id_usuario'],
            'especialidad'    => $data['especialidad'] ?? null,
            'telefono'        => $data['telefono'] ?? null,
            'licencia_medica' => $data['licencia_medica'] ?? null,
        ];
        return $id;
    }

    public function update(int $idVeterinario, array $data): bool {
        if (!isset($this->store->veterinarios[$idVeterinario])) {
            return false;
        }
        foreach (['especialidad', 'telefono', 'licencia_medica'] as $k) {
            if (array_key_exists($k, $data)) {
                $this->store->veterinarios[$idVeterinario][$k] = $data[$k];
            }
        }
        return true;
    }

    public function findById(int $id): ?array {
        if (!isset($this->store->veterinarios[$id])) {
            return null;
        }
        $v   = $this->store->veterinarios[$id];
        $u   = $this->store->usuarios[(int) $v['id_usuario']] ?? [];
        $rel = $this->contarRelaciones($id);

        return array_merge($v, [
            'nombre'            => $u['nombre'] ?? '',
            'correo'            => $u['correo'] ?? '',
            'usuario'           => $u['usuario'] ?? '',
            'activo'            => $u['activo'] ?? 0,
            'citas_total'       => $rel['citas_total'],
            'citas_programadas' => $rel['citas_programadas'],
            'consultas_total'   => $rel['consultas_total'],
            'horarios_total'    => $rel['horarios_total'],
        ]);
    }

    public function findByUsuarioId(int $idUsuario): ?array {
        foreach ($this->store->veterinarios as $id => $v) {
            if ((int) $v['id_usuario'] === $idUsuario) {
                return $this->findById((int) $id);
            }
        }
        return null;
    }

    public function findAll(bool $onlyActive = false): array {
        $res = [];
        foreach ($this->store->veterinarios as $id => $v) {
            $full = $this->findById((int) $id);
            if (!$full) {
                continue;
            }
            if ($onlyActive && (int) $full['activo'] !== 1) {
                continue;
            }
            $res[] = $full;
        }
        return $res;
    }

    public function search(?string $termino = null, bool $onlyActive = false): array {
        $q = mb_strtolower(trim((string) $termino));
        if ($q === '') {
            return $this->findAll($onlyActive);
        }
        $res = [];
        foreach ($this->findAll($onlyActive) as $v) {
            $haystack = mb_strtolower(
                ($v['nombre'] ?? '') . ' ' .
                ($v['usuario'] ?? '') . ' ' .
                ($v['correo'] ?? '') . ' ' .
                ($v['especialidad'] ?? '') . ' ' .
                ($v['licencia_medica'] ?? '') . ' ' .
                ($v['telefono'] ?? '')
            );
            if (str_contains($haystack, $q)) {
                $res[] = $v;
            }
        }
        return $res;
    }

    public function existsLicenciaMedica(string $licencia, ?int $excludeIdVeterinario = null): bool {
        $lic = mb_strtolower(trim($licencia));
        if ($lic === '') {
            return false;
        }
        foreach ($this->store->veterinarios as $v) {
            if (mb_strtolower((string) ($v['licencia_medica'] ?? '')) === $lic) {
                if ($excludeIdVeterinario !== null && (int) $v['id_veterinario'] === $excludeIdVeterinario) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    public function contarRelaciones(int $idVeterinario): array {
        $citas     = array_filter($this->store->citas, static fn($c) => (int) $c['id_veterinario'] === $idVeterinario);
        $consultas = array_filter($this->store->consultas, static fn($cv) => (int) $cv['id_veterinario'] === $idVeterinario);
        $prog      = array_filter($citas, static fn($c) => ($c['estado'] ?? '') === 'Programada');
        return [
            'citas_total'        => count($citas),
            'citas_programadas'  => count($prog),
            'consultas_total'    => count($consultas),
            'horarios_total'     => 0,
            'tiene_dependencias' => (count($citas) > 0 || count($consultas) > 0),
        ];
    }

    public function cancelarCitasProgramadas(
        int $idVeterinario,
        string $motivo = 'Cancelada automáticamente por desactivación del veterinario'
    ): int {
        $canceladas = 0;
        foreach ($this->store->citas as $idCita => $c) {
            if ((int) $c['id_veterinario'] === $idVeterinario && ($c['estado'] ?? '') === 'Programada') {
                $this->store->citas[$idCita]['estado'] = 'Cancelada';
                $this->store->citas[$idCita]['motivo_cancelacion'] = $c['motivo_cancelacion'] ?? $motivo;
                $canceladas++;
            }
        }
        return $canceladas;
    }

    public function delete(int $idVeterinario): bool {
        if (!isset($this->store->veterinarios[$idVeterinario])) {
            return false;
        }
        unset($this->store->veterinarios[$idVeterinario]);
        return true;
    }
}

$store       = new InMemoryVetStore();
$usuarioMod  = new MockUsuarioModelUS09($store);
$vetMod      = new MockVeterinarioModelUS09($store);
$usuarioRepo = new UsuarioRepository($usuarioMod);
$vetRepo     = new VeterinarioRepository($vetMod, $usuarioMod);
$vetService  = new VeterinariosService($vetRepo, $usuarioRepo);
$authService = new AuthService($usuarioRepo);

// ────────────────────────────────────────────────────────────────── //
// PRUEBAS AUTOMATIZADAS US-09 (T-059 a T-064)
// ────────────────────────────────────────────────────────────────── //

runTest('T-059 / T-060 / T-061: Alta valida de veterinario crea cuenta y ficha en relacion 1:1 y permite login', function () use ($vetService, $authService, $vetRepo, $store) {
    $res = $vetService->registrarVeterinario([
        'nombre'               => 'Dra. Sofia Alejandra Rivas',
        'correo'               => 'sofia.rivas@clinica.sv',
        'usuario'              => 'dra.rivas',
        'contrasena'           => 'Sofia#2026!',
        'confirmar_contrasena' => 'Sofia#2026!',
        'especialidad'         => 'Cirugia de Tejidos Blandos',
        'telefono'             => '7890-1122',
        'licencia_medica'      => 'JVPMV-501',
    ]);

    assert($res['success'] === true, 'Registro de veterinario debe ser exitoso');
    assert(isset($res['id_veterinario'], $res['id_usuario']), 'Retorna id_veterinario e id_usuario');

    $idVet  = (int) $res['id_veterinario'];
    $idUser = (int) $res['id_usuario'];

    $ficha = $vetRepo->findByUsuarioId($idUser);
    assert($ficha !== null && (int) $ficha['id_veterinario'] === $idVet, 'Relacion 1:1 id_usuario -> id_veterinario verificada');
    assert($ficha['especialidad'] === 'Cirugia de Tejidos Blandos', 'Especialidad guardada correctamente');
    assert($ficha['licencia_medica'] === 'JVPMV-501', 'Licencia medica guardada correctamente');
    assert($store->usuarios[$idUser]['rol'] === 'veterinario', 'Cuenta creada con rol veterinario');
    assert((int) $store->usuarios[$idUser]['activo'] === 1, 'Cuenta creada activa por defecto');

    // Verificar que la contraseña se hasheó con Bcrypt y permite iniciar sesión
    assert($authService->login('dra.rivas', 'Sofia#2026!') === true, 'El nuevo veterinario puede iniciar sesion con su cuenta inicial');
});

runTest('T-060: Atomicidad transaccional — si falla el alta de la ficha no queda usuario huerfano', function () use ($vetService, $store) {
    $usuariosAntes = count($store->usuarios);
    $store->failNextVetInsert = true;

    $res = $vetService->registrarVeterinario([
        'nombre'               => 'Dr. Fallo Transaccion',
        'correo'               => 'fallo.tx@clinica.sv',
        'usuario'              => 'dr.fallotx',
        'contrasena'           => 'ClaveSegura#123',
        'confirmar_contrasena' => 'ClaveSegura#123',
        'especialidad'         => 'Medicina Interna',
    ]);

    assert($res['success'] === false, 'Debe reportar fallo controlado sin exponer SQL');
    assert(count($store->usuarios) === $usuariosAntes, 'Rollback verificado: no quedo ningun usuario huerfano en memoria');
});

runTest('T-061: Rechazo de duplicados en usuario, correo electronico y licencia medica', function () use ($vetService) {
    // 1. Usuario duplicado
    $dupUser = $vetService->registrarVeterinario([
        'nombre'          => 'Dr. Otro Medico',
        'correo'          => 'otro.medico@clinica.sv',
        'usuario'         => 'dra.rivas', // Ya existe
        'contrasena'      => 'ClaveSegura#123',
        'licencia_medica' => 'JVPMV-999',
    ]);
    assert($dupUser['success'] === false && isset($dupUser['errors']['usuario']), 'Debe rechazar usuario duplicado');

    // 2. Correo duplicado
    $dupMail = $vetService->registrarVeterinario([
        'nombre'          => 'Dr. Otro Medico',
        'correo'          => 'sofia.rivas@clinica.sv', // Ya existe
        'usuario'         => 'dr.otromedico',
        'contrasena'      => 'ClaveSegura#123',
        'licencia_medica' => 'JVPMV-999',
    ]);
    assert($dupMail['success'] === false && isset($dupMail['errors']['correo']), 'Debe rechazar correo duplicado');

    // 3. Licencia médica duplicada
    $dupLic = $vetService->registrarVeterinario([
        'nombre'          => 'Dr. Otro Medico',
        'correo'          => 'otro.medico@clinica.sv',
        'usuario'         => 'dr.otromedico',
        'contrasena'      => 'ClaveSegura#123',
        'licencia_medica' => 'JVPMV-501', // Ya pertenece a Dra. Sofia
    ]);
    assert($dupLic['success'] === false && isset($dupLic['errors']['licencia_medica']), 'Debe rechazar licencia medica duplicada');
});

runTest('T-061 / T-063: Validacion de campos obligatorios y contrasena minima al crear', function () use ($vetService) {
    $invalido = $vetService->registrarVeterinario([
        'nombre'               => 'AB',
        'correo'               => 'correo-invalido',
        'usuario'              => 'u!',
        'contrasena'           => '12345',
        'confirmar_contrasena' => 'distinta',
    ]);
    assert($invalido['success'] === false, 'Debe rechazar datos invalidos');
    assert(isset($invalido['errors']['nombre']), 'Error en nombre corto');
    assert(isset($invalido['errors']['correo']), 'Error en correo invalido');
    assert(isset($invalido['errors']['usuario']), 'Error en usuario invalido');
    assert(isset($invalido['errors']['contrasena']), 'Error en contrasena menor a 8 caracteres');
    assert(isset($invalido['errors']['confirmar_contrasena']), 'Error cuando confirmacion no coincide');
});

runTest('T-063: En edicion la contrasena NO se exige si no se cambian credenciales', function () use ($vetService, $authService) {
    // Actualizar especialidad y teléfono dejando contraseña vacía
    $upd = $vetService->actualizarVeterinario(1, [
        'nombre'               => 'Dra. Sofia Alejandra Rivas',
        'correo'               => 'sofia.rivas@clinica.sv',
        'usuario'              => 'dra.rivas',
        'especialidad'         => 'Cirugia y Ortopedia',
        'telefono'             => '7890-9999',
        'licencia_medica'      => 'JVPMV-501',
        'contrasena'           => '',
        'confirmar_contrasena' => '',
    ]);

    assert($upd['success'] === true, 'Debe permitir editar perfil sin ingresar contrasena');
    assert($authService->login('dra.rivas', 'Sofia#2026!') === true, 'La contrasena original se conserva intacta');
});

runTest('T-063: En edicion se actualiza la contrasena cuando se ingresan nuevas credenciales validas', function () use ($vetService, $authService) {
    $updPass = $vetService->actualizarVeterinario(1, [
        'nombre'               => 'Dra. Sofia Alejandra Rivas',
        'correo'               => 'sofia.rivas@clinica.sv',
        'usuario'              => 'dra.rivas',
        'especialidad'         => 'Cirugia y Ortopedia',
        'telefono'             => '7890-9999',
        'licencia_medica'      => 'JVPMV-501',
        'contrasena'           => 'NuevaSofia#2027',
        'confirmar_contrasena' => 'NuevaSofia#2027',
    ]);

    assert($updPass['success'] === true, 'Debe actualizar las credenciales');
    assert($authService->login('dra.rivas', 'Sofia#2026!') === false, 'La contrasena anterior ya no es valida');
    assert($authService->login('dra.rivas', 'NuevaSofia#2027') === true, 'La nueva contrasena permite iniciar sesion');
});

runTest('T-061 / T-064: Restricciones por citas — veterinario con citas se desactiva logicamente (no se elimina)', function () use ($vetService, $authService, $store) {
    // Asociar una cita Programada y una Atendida a la Dra. Sofia (id_veterinario = 1)
    $store->citas[1] = [
        'id_cita'        => 1,
        'id_mascota'     => 1,
        'id_veterinario' => 1,
        'id_servicio'    => 1,
        'fecha_cita'     => '2026-11-20',
        'hora_cita'      => '10:00:00',
        'estado'         => 'Programada',
    ];
    $store->citas[2] = [
        'id_cita'        => 2,
        'id_mascota'     => 1,
        'id_veterinario' => 1,
        'id_servicio'    => 1,
        'fecha_cita'     => '2026-10-15',
        'hora_cita'      => '09:00:00',
        'estado'         => 'Atendida',
    ];

    $baja = $vetService->darDeBajaVeterinario(1);
    assert($baja['success'] === true, 'Operacion de baja exitosa');
    assert($baja['tipo_baja'] === 'logica', 'Al tener citas debe aplicar baja logica y bloquear eliminacion fisica');
    assert(isset($store->veterinarios[1]), 'La ficha del veterinario se preserva en BD');
    assert($store->citas[1]['estado'] === 'Cancelada', 'La cita Programada pendiente se cancela automaticamente');
    assert($store->citas[2]['estado'] === 'Atendida', 'La cita Atendida historica permanece intacta');
    assert($authService->login('dra.rivas', 'NuevaSofia#2027') === false, 'El veterinario desactivado NO puede iniciar sesion');
});

runTest('T-061 / T-063: Busqueda y filtrado de veterinarios por estado (activos, inactivos, todos)', function () use ($vetService) {
    // Registrar un segundo veterinario activo sin citas
    $res2 = $vetService->registrarVeterinario([
        'nombre'          => 'Dr. Roberto Carlos Mena',
        'correo'          => 'roberto.mena@clinica.sv',
        'usuario'         => 'dr.mena',
        'contrasena'      => 'Mena#2026!',
        'especialidad'    => 'Dermatologia',
        'licencia_medica' => 'JVPMV-502',
    ]);
    assert($res2['success'] === true, 'Segundo veterinario registrado');

    $activos   = $vetService->buscarVeterinarios(null, 'activos');
    $inactivos = $vetService->buscarVeterinarios(null, 'inactivos');
    $todos     = $vetService->buscarVeterinarios(null, 'todos');

    assert(count($activos) === 1 && $activos[0]['usuario'] === 'dr.mena', 'Filtro activos devuelve solo a dr.mena');
    assert(count($inactivos) === 1 && $inactivos[0]['usuario'] === 'dra.rivas', 'Filtro inactivos devuelve solo a dra.rivas');
    assert(count($todos) === 2, 'Filtro todos devuelve los 2 veterinarios');

    // Búsqueda por especialidad
    $porEspec = $vetService->buscarVeterinarios('Dermatologia', 'activos');
    assert(count($porEspec) === 1 && $porEspec[0]['usuario'] === 'dr.mena', 'Busqueda por especialidad encuentra a dr.mena');
});

runTest('T-061: Veterinario SIN citas ni consultas se elimina fisicamente junto con su usuario', function () use ($vetService, $store) {
    // Dr. Roberto Mena (id_veterinario = 2) no tiene citas ni consultas
    $baja = $vetService->darDeBajaVeterinario(2);
    assert($baja['success'] === true && $baja['tipo_baja'] === 'fisica', 'Sin citas ni consultas aplica eliminacion fisica');
    assert(!isset($store->veterinarios[2]), 'Ficha de veterinario #2 eliminada');
});

runTest('T-061 / T-062: Reactivacion de veterinario inactivo restablece acceso al sistema', function () use ($vetService, $authService, $store) {
    // Dra. Sofia (id_veterinario = 1) estaba inactiva tras la prueba de baja lógica
    assert((int) $store->usuarios[100]['activo'] === 0, 'Dra. Sofia inicia inactiva');
    $react = $vetService->reactivarVeterinario(1);
    assert($react['success'] === true, 'Reactivacion exitosa');
    assert((int) $store->usuarios[100]['activo'] === 1, 'Estado activo = 1 restablecido');
    assert($authService->login('dra.rivas', 'NuevaSofia#2027') === true, 'Puede volver a iniciar sesion tras reactivarse');
});

runTest('T-062 / T-063 / T-064: Verificacion de autorizacion en controlador, vistas y confirmacion JS', function () {
    $ctrlCode = (string) file_get_contents(__DIR__ . '/../src/app/Controllers/VeterinarioController.php');
    assert(substr_count($ctrlCode, "AuthMiddleware::requireRole('administrador')") >= 6, 'Todas las acciones del controlador validan rol administrador');
    assert(substr_count($ctrlCode, 'CsrfMiddleware::validateToken') >= 3, 'Todas las acciones POST validan token CSRF');

    $crearView  = (string) file_get_contents(__DIR__ . '/../src/app/Views/veterinarios/crear.php');
    $editarView = (string) file_get_contents(__DIR__ . '/../src/app/Views/veterinarios/editar.php');
    $listarView = (string) file_get_contents(__DIR__ . '/../src/app/Views/veterinarios/listar.php');
    $jsCode     = (string) file_get_contents(__DIR__ . '/../src/public/assets/js/veterinarios.js');

    assert(str_contains($listarView, 'badge-vet') && str_contains($listarView, 'badge-inactivo'), 'El listado distingue estado activo/inactivo');
    assert(str_contains($crearView, 'required') && str_contains($crearView, 'name="contrasena"'), 'Al crear se solicita contrasena inicial obligatoria');
    assert(str_contains($editarView, 'Dejar en blanco para no modificar'), 'En edicion la contrasena solo se solicita si se cambian credenciales');
    assert(str_contains($jsCode, 'window.confirm'), 'El script cliente incluye confirmacion previa antes de desactivar o eliminar');
});

runTest('T-059 a T-064: Integracion PDO contra MySQL (si el servidor MySQL esta activo) o verificacion de esquema SQL', function () {
    try {
        $pdo = \App\Config\Database::getConnection();
    } catch (\Throwable $e) {
        // Entorno CLI offline (sin contenedor MySQL activo): verificar definicion en init.sql y modelo PDO
        $initSql = (string) file_get_contents(__DIR__ . '/../init.sql');
        assert(str_contains($initSql, 'CREATE TABLE IF NOT EXISTS `veterinarios`'), 'Tabla veterinarios definida en init.sql');
        assert(str_contains($initSql, '`id_usuario` INT NOT NULL UNIQUE'), 'Relacion 1:1 id_usuario UNIQUE en init.sql');
        assert(str_contains($initSql, 'ON DELETE RESTRICT ON UPDATE CASCADE'), 'FK con ON DELETE RESTRICT en init.sql');
        return;
    }

    $pdo->beginTransaction();
    try {
        $realUserMod  = new UsuarioModel();
        $realVetMod   = new VeterinarioModel();
        $realUserRepo = new UsuarioRepository($realUserMod);
        $realVetRepo  = new VeterinarioRepository($realVetMod, $realUserMod);
        $realService  = new VeterinariosService($realVetRepo, $realUserRepo);
        $realAuth     = new AuthService($realUserRepo);

        // 1. Alta real en transacción (usuarios + veterinarios)
        $alta = $realService->registrarVeterinario([
            'nombre'               => 'Dr. Integracion US09',
            'correo'               => 'integracion.us09@clinica.sv',
            'usuario'              => 'dr.integracion.us09',
            'contrasena'           => 'Integracion#2026',
            'confirmar_contrasena' => 'Integracion#2026',
            'especialidad'         => 'Cardiologia Veterinaria',
            'telefono'             => '7555-1234',
            'licencia_medica'      => 'JVPMV-US09-TEST',
        ]);
        assert($alta['success'] === true, 'Alta PDO real exitosa');
        $idVet  = (int) $alta['id_veterinario'];
        $idUser = (int) $alta['id_usuario'];

        // 2. Comprobar que no permite duplicar la licencia ni el usuario en MySQL
        $dup = $realService->registrarVeterinario([
            'nombre'          => 'Dr. Duplicado US09',
            'correo'          => 'otro.us09@clinica.sv',
            'usuario'         => 'dr.integracion.us09',
            'contrasena'      => 'Integracion#2026',
            'licencia_medica' => 'JVPMV-US09-TEST',
        ]);
        assert($dup['success'] === false && isset($dup['errors']['usuario'], $dup['errors']['licencia_medica']), 'Rechaza duplicados en BD real');

        // 3. Búsqueda parametrizada en MySQL
        $hallados = $realService->buscarVeterinarios('JVPMV-US09-TEST', 'activos');
        assert(count($hallados) === 1 && (int) $hallados[0]['id_veterinario'] === $idVet, 'Busqueda PDO por licencia medica exitosa');

        // 4. Edición sin cambiar contraseña
        $edicion = $realService->actualizarVeterinario($idVet, [
            'nombre'          => 'Dr. Integracion US09 Editado',
            'correo'          => 'integracion.us09@clinica.sv',
            'usuario'         => 'dr.integracion.us09',
            'especialidad'    => 'Cardiologia y Ecografia',
            'telefono'        => '7555-9999',
            'licencia_medica' => 'JVPMV-US09-TEST',
            'contrasena'      => '',
        ]);
        assert($edicion['success'] === true, 'Edicion PDO sin cambio de contrasena exitosa');
        assert($realAuth->authenticate('dr.integracion.us09', 'Integracion#2026')['success'] === true, 'Conserva contrasena tras edicion');

        // 5. Eliminación física al no tener citas ni consultas
        $del = $realService->darDeBajaVeterinario($idVet);
        assert($del['success'] === true && $del['tipo_baja'] === 'fisica', 'Eliminacion fisica PDO exitosa');
        assert($realVetRepo->findById($idVet) === null && $realUserRepo->findById($idUser) === null, 'Ambos registros eliminados de MySQL');
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
