<?php
namespace App\Controllers;

use App\Services\PropietariosMascotasService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * MascotaController — US-03, ARQ-01, ACC-01, SEC-01, DAT-01
 * Controlador para la gestión y registro de mascotas.
 * Acciones de creación restringidas al rol Administrador (ACC-01).
 */
class MascotaController {

    private PropietariosMascotasService $service;

    public function __construct(?PropietariosMascotasService $service = null) {
        $this->service = $service ?? new PropietariosMascotasService();
    }

    /**
     * GET /mascotas — Listado de mascotas registradas
     */
    public function index(): void {
        AuthMiddleware::requireLogin();

        $mascotas = $this->service->listarMascotas(true);

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/mascotas/index.php';
    }

    /**
     * GET /mascotas/crear — Formulario de alta de mascota
     * Requiere autorización de Administrador (ACC-01).
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

        // Preselección si viene de la vista de un propietario específico (?propietario_id=X)
        if (empty($oldData['id_propietario']) && isset($_GET['propietario_id'])) {
            $oldData['id_propietario'] = (int)$_GET['propietario_id'];
        }

        // Obtener lista de propietarios activos para el selector (DAT-01)
        $propietarios = $this->service->listarPropietarios(true);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/mascotas/crear.php';
    }

    /**
     * POST /mascotas — Procesa el registro de una nueva mascota
     * Valida CSRF y delega reglas de negocio al servicio.
     */
    public function store(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $datos = [
            'id_propietario'             => $_POST['id_propietario'] ?? '',
            'nombre'                     => $_POST['nombre'] ?? '',
            'especie'                    => $_POST['especie'] ?? '',
            'raza'                       => $_POST['raza'] ?? '',
            'sexo'                       => $_POST['sexo'] ?? '',
            'fecha_nacimiento'           => $_POST['fecha_nacimiento'] ?? '',
            'caracteristicas_relevantes' => $_POST['caracteristicas_relevantes'] ?? '',
        ];

        $resultado = $this->service->registrarMascota($datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /mascotas');
            exit;
        }

        // En caso de error, persistir datos para repoblación en el formulario
        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /mascotas/crear');
        exit;
    }
}
