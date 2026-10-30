<?php
namespace App\Controllers;

use App\Services\VeterinariosService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * VeterinarioController — US-09 (T-062), US-12 (T-078), ARQ-01, ACC-01, SEC-01, BAJA-01
 * Controlador para el directorio, alta, detalle, edición y baja controlada de veterinarios.
 * Todas las acciones son exclusivas del rol Administrador (ACC-01).
 */
class VeterinarioController {

    private VeterinariosService $service;

    public function __construct(?VeterinariosService $service = null) {
        $this->service = $service ?? new VeterinariosService();
    }

    /**
     * GET /veterinarios — Directorio de veterinarios con búsqueda y filtro de estado (T-062, T-063)
     * Exclusivo del rol Administrador.
     */
    public function index(): void {
        AuthMiddleware::requireRole('administrador');

        $busqueda     = trim((string) ($_GET['q'] ?? ''));
        $estadoFiltro = trim((string) ($_GET['estado'] ?? 'activos'));
        if (!in_array($estadoFiltro, ['activos', 'todos', 'inactivos'], true)) {
            $estadoFiltro = 'activos';
        }

        $veterinarios = $this->service->buscarVeterinarios(
            $busqueda !== '' ? $busqueda : null,
            $estadoFiltro
        );

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/veterinarios/listar.php';
    }

    /**
     * GET /veterinarios/crear — Formulario de alta de veterinario y cuenta inicial (T-062, T-063)
     * Exclusivo del rol Administrador.
     */
    public function create(): void {
        AuthMiddleware::requireRole('administrador');

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

        require_once __DIR__ . '/../Views/veterinarios/crear.php';
    }

    /**
     * POST /veterinarios — Registra en transacción la cuenta de usuario y la ficha del veterinario (T-062)
     * Exclusivo del rol Administrador y protegido con CSRF.
     */
    public function store(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $datos = [
            'nombre'               => $_POST['nombre']               ?? '',
            'correo'               => $_POST['correo']               ?? '',
            'usuario'              => $_POST['usuario']              ?? '',
            'contrasena'           => $_POST['contrasena']           ?? '',
            'confirmar_contrasena' => $_POST['confirmar_contrasena'] ?? '',
            'especialidad'         => $_POST['especialidad']         ?? '',
            'telefono'             => $_POST['telefono']             ?? '',
            'licencia_medica'      => $_POST['licencia_medica']      ?? '',
        ];

        $resultado = $this->service->registrarVeterinario($datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /veterinarios');
            exit;
        }

        // Nunca almacenar contraseñas en sesión al repoblar el formulario (SEC-01)
        $oldData = $datos;
        unset($oldData['contrasena'], $oldData['confirmar_contrasena']);

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $oldData;

        header('Location: /veterinarios/crear');
        exit;
    }

    /**
     * GET /veterinarios/editar?id=N — Detalle de relaciones y formulario de edición (T-062, T-063)
     * Exclusivo del rol Administrador.
     */
    public function edit(): void {
        AuthMiddleware::requireRole('administrador');

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de veterinario no válido.';
            header('Location: /veterinarios');
            exit;
        }

        $veterinario = $this->service->obtenerVeterinarioPorId($id);
        if (!$veterinario) {
            $_SESSION['flash_error'] = 'El veterinario solicitado no existe.';
            header('Location: /veterinarios');
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

        require_once __DIR__ . '/../Views/veterinarios/editar.php';
    }

    /**
     * POST /veterinarios/editar — Actualiza los datos de un veterinario y opcionalmente su contraseña (T-062, T-078)
     * Requiere rol Administrador y token CSRF válido.
     */
    public function update(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id = filter_var($_POST['id_veterinario'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de veterinario no válido.';
            header('Location: /veterinarios');
            exit;
        }

        $datos = [
            'nombre'               => $_POST['nombre']               ?? '',
            'correo'               => $_POST['correo']               ?? '',
            'usuario'              => $_POST['usuario']              ?? '',
            'especialidad'         => $_POST['especialidad']         ?? '',
            'telefono'             => $_POST['telefono']             ?? '',
            'licencia_medica'      => $_POST['licencia_medica']      ?? '',
            'contrasena'           => $_POST['contrasena']           ?? '',
            'confirmar_contrasena' => $_POST['confirmar_contrasena'] ?? '',
        ];

        $resultado = $this->service->actualizarVeterinario($id, $datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /veterinarios');
            exit;
        }

        $oldData = $datos;
        unset($oldData['contrasena'], $oldData['confirmar_contrasena']);

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $oldData;

        header('Location: /veterinarios/editar?id=' . $id);
        exit;
    }

    /**
     * POST /veterinarios/baja (y /veterinarios/eliminar) — Baja controlada o reactivación (T-062, T-077, T-078, BAJA-01)
     * Requiere rol Administrador y token CSRF válido.
     */
    public function baja(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id     = filter_var($_POST['id_veterinario'] ?? null, FILTER_VALIDATE_INT);
        $accion = trim((string) ($_POST['accion'] ?? 'baja'));

        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de veterinario no válido.';
            header('Location: /veterinarios');
            exit;
        }

        if ($accion === 'reactivar') {
            $resultado = $this->service->reactivarVeterinario($id);
        } else {
            $resultado = $this->service->darDeBajaVeterinario($id);
        }

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
        } else {
            $_SESSION['flash_error'] = $resultado['message'];
        }

        header('Location: /veterinarios');
        exit;
    }

    /**
     * Alias de baja() para compatibilidad con rutas /eliminar.
     */
    public function delete(): void {
        $this->baja();
    }
}
