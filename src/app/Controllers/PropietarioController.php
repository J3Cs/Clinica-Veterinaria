<?php
namespace App\Controllers;

use App\Services\PropietariosMascotasService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * PropietarioController — T-019, T-078, ARQ-01, ACC-01, SEC-01, BAJA-01
 * Controlador para el directorio, búsqueda, alta, edición y baja controlada de propietarios.
 * Operaciones de mutación restringidas al rol Administrador con token CSRF (ACC-01, SEC-01).
 */
class PropietarioController {

    private PropietariosMascotasService $service;

    public function __construct(?PropietariosMascotasService $service = null) {
        $this->service = $service ?? new PropietariosMascotasService();
    }

    /**
     * GET /propietarios — Directorio de propietarios con búsqueda parametrizada (T-076, T-079, T-090)
     */
    public function index(): void {
        AuthMiddleware::requireLogin();

        $rolSesion  = (string) ($_SESSION['rol'] ?? '');
        $busqueda   = trim((string) ($_GET['q'] ?? ''));
        $estadoFiltro = trim((string) ($_GET['estado'] ?? 'activos'));

        // Solo el administrador puede consultar registros inactivos; por defecto se filtran inactivos (T-090)
        $soloActivos = true;
        if ($rolSesion === 'administrador' && in_array($estadoFiltro, ['todos', 'inactivos'], true)) {
            $soloActivos = false;
        } else {
            $estadoFiltro = 'activos';
        }

        $propietarios = $this->service->buscarPropietarios($busqueda !== '' ? $busqueda : null, $soloActivos);

        if (!$soloActivos && $estadoFiltro === 'inactivos') {
            $propietarios = array_values(array_filter(
                $propietarios,
                static fn(array $p): bool => (int) ($p['activo'] ?? 1) === 0
            ));
        }

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($rolSesion, ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/propietarios/listar.php';
    }

    /**
     * GET /propietarios/crear — Formulario de alta de propietario
     * Requiere autorización exclusiva de Administrador (ACC-01).
     */
    public function create(): void {
        AuthMiddleware::requireRole('administrador');

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $fieldErrors  = $_SESSION['field_errors'] ?? [];
        $oldData      = $_SESSION['old_data'] ?? [];

        unset(
            $_SESSION['flash_error'],
            $_SESSION['flash_success'],
            $_SESSION['field_errors'],
            $_SESSION['old_data']
        );

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/propietarios/crear.php';
    }

    /**
     * POST /propietarios — Procesa el registro de un nuevo propietario.
     * Valida CSRF y delega reglas de negocio al servicio.
     */
    public function store(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $datos = [
            'nombre_completo' => $_POST['nombre_completo'] ?? '',
            'dui'             => $_POST['dui'] ?? '',
            'telefono'        => $_POST['telefono'] ?? '',
            'correo'          => $_POST['correo'] ?? '',
            'direccion'       => $_POST['direccion'] ?? '',
        ];

        $resultado = $this->service->registrarPropietario($datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = 'Propietario registrado exitosamente.';
            header('Location: /propietarios');
            exit;
        }

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /propietarios/crear');
        exit;
    }

    /**
     * GET /propietarios/editar?id=N — Formulario de edición y detalle de relaciones (T-078, T-079)
     * Requiere autorización exclusiva de Administrador (ACC-01).
     */
    public function edit(): void {
        AuthMiddleware::requireRole('administrador');

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de propietario no válido.';
            header('Location: /propietarios');
            exit;
        }

        $propietario = $this->service->obtenerPropietarioConRelaciones($id);
        if (!$propietario) {
            $_SESSION['flash_error'] = 'El propietario solicitado no existe.';
            header('Location: /propietarios');
            exit;
        }

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error']   ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $fieldErrors  = $_SESSION['field_errors']  ?? [];
        $oldData      = $_SESSION['old_data']      ?? [];

        unset(
            $_SESSION['flash_error'],
            $_SESSION['flash_success'],
            $_SESSION['field_errors'],
            $_SESSION['old_data']
        );

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/propietarios/editar.php';
    }

    /**
     * POST /propietarios/editar — Procesa la edición de un propietario (T-078)
     * Requiere rol Administrador y token CSRF válido.
     */
    public function update(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id = filter_var($_POST['id_propietario'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de propietario no válido.';
            header('Location: /propietarios');
            exit;
        }

        $datos = [
            'nombre_completo' => $_POST['nombre_completo'] ?? '',
            'dui'             => $_POST['dui'] ?? '',
            'telefono'        => $_POST['telefono'] ?? '',
            'correo'          => $_POST['correo'] ?? '',
            'direccion'       => $_POST['direccion'] ?? '',
        ];

        $resultado = $this->service->actualizarPropietario($id, $datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /propietarios');
            exit;
        }

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /propietarios/editar?id=' . $id);
        exit;
    }

    /**
     * POST /propietarios/baja (y /propietarios/eliminar) — Baja controlada o reactivación (T-077, T-078, BAJA-01)
     * Distingue baja lógica (cuando existen mascotas/citas/historial) de eliminación física (sin dependencias).
     */
    public function baja(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id     = filter_var($_POST['id_propietario'] ?? null, FILTER_VALIDATE_INT);
        $accion = trim((string) ($_POST['accion'] ?? 'baja'));

        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de propietario no válido.';
            header('Location: /propietarios');
            exit;
        }

        if ($accion === 'reactivar') {
            $resultado = $this->service->reactivarPropietario($id);
        } else {
            $resultado = $this->service->darDeBajaPropietario($id);
        }

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
        } else {
            $_SESSION['flash_error'] = $resultado['message'];
        }

        header('Location: /propietarios');
        exit;
    }

    /**
     * Alias de baja() para compatibilidad con rutas /eliminar.
     */
    public function delete(): void {
        $this->baja();
    }
}
