<?php
namespace App\Controllers;

use App\Services\CatalogoServiciosService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * ServicioController — US-10 (T-068), ARQ-01, ACC-01, SEC-01
 * Controlador para el catálogo de servicios veterinarios.
 * Todas las acciones requieren rol Administrador (ACC-01).
 */
class ServicioController {

    private CatalogoServiciosService $service;

    public function __construct(?CatalogoServiciosService $service = null) {
        $this->service = $service ?? new CatalogoServiciosService();
    }

    // ────────────────────────────────────────────────────────────── //
    //  GET /servicios — Listado
    // ────────────────────────────────────────────────────────────── //

    public function index(): void {
        AuthMiddleware::requireRole('administrador');

        $todosServicios = $this->service->listarServicios();
        $busqueda       = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $estadoFiltro   = isset($_GET['estado']) && is_string($_GET['estado']) ? trim($_GET['estado']) : 'todos';
        if (!in_array($estadoFiltro, ['todos', 'activos', 'inactivos'], true)) {
            $estadoFiltro = 'todos';
        }

        $servicios = array_values(array_filter(
            $todosServicios,
            static function (array $s) use ($busqueda, $estadoFiltro): bool {
                $activo = (int) ($s['activo'] ?? 0) === 1;
                if ($estadoFiltro === 'activos' && !$activo) {
                    return false;
                }
                if ($estadoFiltro === 'inactivos' && $activo) {
                    return false;
                }
                if ($busqueda !== '') {
                    $q      = mb_strtolower($busqueda, 'UTF-8');
                    $nombre = mb_strtolower((string) ($s['nombre_servicio'] ?? ''), 'UTF-8');
                    $desc   = mb_strtolower((string) ($s['descripcion'] ?? ''), 'UTF-8');
                    $dur    = (string) ($s['duracion_estimada_min'] ?? '');
                    return str_contains($nombre, $q) || str_contains($desc, $q) || str_contains($dur, $q);
                }
                return true;
            }
        ));

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/servicios/listar.php';
    }

    // ────────────────────────────────────────────────────────────── //
    //  GET /servicios/crear — Formulario de alta
    // ────────────────────────────────────────────────────────────── //

    public function create(): void {
        AuthMiddleware::requireRole('administrador');

        $csrfToken   = CsrfMiddleware::generateToken();
        $flashError  = $_SESSION['flash_error']  ?? null;
        $fieldErrors = $_SESSION['field_errors'] ?? [];
        $oldData     = $_SESSION['old_data']     ?? [];
        $modoEdicion = false;
        $servicio    = null;

        unset($_SESSION['flash_error'], $_SESSION['field_errors'], $_SESSION['old_data']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/servicios/formulario.php';
    }

    // ────────────────────────────────────────────────────────────── //
    //  POST /servicios — Alta de servicio
    // ────────────────────────────────────────────────────────────── //

    public function store(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $datos = [
            'nombre_servicio'       => $_POST['nombre_servicio']      ?? '',
            'descripcion'           => $_POST['descripcion']          ?? '',
            'duracion_estimada_min' => $_POST['duracion_estimada_min'] ?? '',
        ];

        $resultado = $this->service->registrarServicio($datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /servicios');
            exit;
        }

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /servicios/crear');
        exit;
    }

    // ────────────────────────────────────────────────────────────── //
    //  GET /servicios/editar?id=N — Formulario de edición
    // ────────────────────────────────────────────────────────────── //

    public function edit(): void {
        AuthMiddleware::requireRole('administrador');

        $id       = $_GET['id'] ?? '';
        $servicio = $this->service->obtenerServicioPorId($id);

        if (!$servicio) {
            $_SESSION['flash_error'] = 'El servicio indicado no existe.';
            header('Location: /servicios');
            exit;
        }

        $csrfToken   = CsrfMiddleware::generateToken();
        $flashError  = $_SESSION['flash_error']  ?? null;
        $fieldErrors = $_SESSION['field_errors'] ?? [];
        $oldData     = $_SESSION['old_data']     ?? [];
        $modoEdicion = true;

        unset($_SESSION['flash_error'], $_SESSION['field_errors'], $_SESSION['old_data']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/servicios/formulario.php';
    }

    // ────────────────────────────────────────────────────────────── //
    //  POST /servicios/editar — Actualización de servicio
    // ────────────────────────────────────────────────────────────── //

    public function update(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id = $_POST['id_servicio'] ?? '';

        $datos = [
            'nombre_servicio'       => $_POST['nombre_servicio']       ?? '',
            'descripcion'           => $_POST['descripcion']           ?? '',
            'duracion_estimada_min' => $_POST['duracion_estimada_min'] ?? '',
        ];

        $resultado = $this->service->actualizarServicio($id, $datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /servicios');
            exit;
        }

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'] ?? [];
        $_SESSION['old_data']     = $datos;

        header('Location: /servicios/editar?id=' . urlencode((string) $id));
        exit;
    }

    // ────────────────────────────────────────────────────────────── //
    //  POST /servicios/estado — Desactivar / Activar (baja lógica)
    // ────────────────────────────────────────────────────────────── //

    public function toggleEstado(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id     = $_POST['id_servicio'] ?? '';
        $accion = $_POST['accion']      ?? '';

        if ($accion === 'desactivar') {
            $resultado = $this->service->desactivarServicio($id);
        } elseif ($accion === 'activar') {
            $resultado = $this->service->activarServicio($id);
        } else {
            $_SESSION['flash_error'] = 'Acción no reconocida.';
            header('Location: /servicios');
            exit;
        }

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
        } else {
            $_SESSION['flash_error'] = $resultado['message'];
        }

        header('Location: /servicios');
        exit;
    }
}
