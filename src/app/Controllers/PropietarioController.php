<?php
namespace App\Controllers;

use App\Services\PropietariosMascotasService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * PropietarioController — T-019, ARQ-01, ACC-01, SEC-01
 * Controlador para la gestión y alta de propietarios.
 * Acciones restringidas al rol Administrador (ACC-01).
 */
class PropietarioController {

    private PropietariosMascotasService $service;

    public function __construct(?PropietariosMascotasService $service = null) {
        $this->service = $service ?? new PropietariosMascotasService();
    }

    /**
     * GET /propietarios — Listado de propietarios
     */
    public function index(): void {
        AuthMiddleware::requireLogin();

        $propietarios = $this->service->listarPropietarios(true);
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/propietarios/index.php';
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

        // Si existen errores, preservar datos ingresados para no perderlos
        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /propietarios/crear');
        exit;
    }
}

