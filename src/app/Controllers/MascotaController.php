<?php
namespace App\Controllers;

use App\Services\PropietariosMascotasService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * MascotaController — US-03, US-12 (T-078), ARQ-01, ACC-01, SEC-01, DAT-01, BAJA-01
 * Controlador para el directorio, búsqueda, registro, edición y baja controlada de mascotas.
 * Operaciones de mutación restringidas al rol Administrador con token CSRF (ACC-01, SEC-01).
 */
class MascotaController {

    private PropietariosMascotasService $service;

    public function __construct(?PropietariosMascotasService $service = null) {
        $this->service = $service ?? new PropietariosMascotasService();
    }

    /**
     * GET /mascotas — Listado de mascotas con búsqueda parametrizada y relaciones (T-076, T-079, T-090)
     */
    public function index(): void {
        AuthMiddleware::requireLogin();

        $rolSesion         = (string) ($_SESSION['rol'] ?? '');
        $busqueda          = trim((string) ($_GET['q'] ?? ''));
        $estadoFiltro      = trim((string) ($_GET['estado'] ?? 'activos'));
        $filtroPropietario = filter_var($_GET['id_propietario'] ?? null, FILTER_VALIDATE_INT) ?: null;

        // Por defecto se excluyen inactivos (T-090); el administrador puede consultar todos o inactivos
        $soloActivos = true;
        if ($rolSesion === 'administrador' && in_array($estadoFiltro, ['todos', 'inactivos'], true)) {
            $soloActivos = false;
        } else {
            $estadoFiltro = 'activos';
        }

        $mascotas = $this->service->buscarMascotas(
            $busqueda !== '' ? $busqueda : null,
            $soloActivos,
            $filtroPropietario
        );

        if (!$soloActivos && $estadoFiltro === 'inactivos') {
            $mascotas = array_values(array_filter(
                $mascotas,
                static fn(array $m): bool => (int) ($m['activo'] ?? 1) === 0
            ));
        }

        $propietarioFiltrado = null;
        if ($filtroPropietario !== null) {
            $propietarioFiltrado = $this->service->obtenerPropietarioPorId($filtroPropietario);
        }

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($rolSesion, ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/mascotas/listar.php';
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

        if (empty($oldData['id_propietario']) && isset($_GET['propietario_id'])) {
            $oldData['id_propietario'] = (int)$_GET['propietario_id'];
        }

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

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /mascotas/crear');
        exit;
    }

    /**
     * GET /mascotas/editar?id=N — Formulario de edición y detalle de relaciones clínicas (T-076, T-078, T-079)
     * Requiere autorización de Administrador (ACC-01).
     */
    public function edit(): void {
        AuthMiddleware::requireRole('administrador');

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de mascota no válido.';
            header('Location: /mascotas');
            exit;
        }

        $mascota = $this->service->obtenerMascotaConRelaciones($id);
        if (!$mascota) {
            $_SESSION['flash_error'] = 'La mascota solicitada no existe.';
            header('Location: /mascotas');
            exit;
        }

        $propietarios = $this->service->listarPropietarios(true);

        // Si el propietario actual está inactivo, incluirlo en la lista para visualización
        $idPropActual = (int) ($mascota['id_propietario'] ?? 0);
        $encontrado = false;
        foreach ($propietarios as $p) {
            if ((int) $p['id_propietario'] === $idPropActual) {
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado && $idPropActual > 0) {
            $propActual = $this->service->obtenerPropietarioPorId($idPropActual);
            if ($propActual) {
                $propietarios[] = $propActual;
            }
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

        require_once __DIR__ . '/../Views/mascotas/editar.php';
    }

    /**
     * POST /mascotas/editar — Procesa la actualización de una mascota (T-078)
     * Requiere rol Administrador y token CSRF válido.
     */
    public function update(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id = filter_var($_POST['id_mascota'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de mascota no válido.';
            header('Location: /mascotas');
            exit;
        }

        $datos = [
            'id_propietario'             => $_POST['id_propietario'] ?? '',
            'nombre'                     => $_POST['nombre'] ?? '',
            'especie'                    => $_POST['especie'] ?? '',
            'raza'                       => $_POST['raza'] ?? '',
            'sexo'                       => $_POST['sexo'] ?? '',
            'fecha_nacimiento'           => $_POST['fecha_nacimiento'] ?? '',
            'caracteristicas_relevantes' => $_POST['caracteristicas_relevantes'] ?? '',
        ];

        $resultado = $this->service->actualizarMascota($id, $datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /mascotas');
            exit;
        }

        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];
        $_SESSION['old_data']     = $datos;

        header('Location: /mascotas/editar?id=' . $id);
        exit;
    }

    /**
     * POST /mascotas/baja (y /mascotas/eliminar) — Baja controlada o reactivación de mascota (T-077, T-078, BAJA-01)
     * Distingue desactivación lógica (con citas/consultas/historial) de eliminación física (sin dependencias).
     */
    public function baja(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id     = filter_var($_POST['id_mascota'] ?? null, FILTER_VALIDATE_INT);
        $accion = trim((string) ($_POST['accion'] ?? 'baja'));

        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de mascota no válido.';
            header('Location: /mascotas');
            exit;
        }

        if ($accion === 'reactivar') {
            $resultado = $this->service->reactivarMascota($id);
        } else {
            $resultado = $this->service->darDeBajaMascota($id);
        }

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
        } else {
            $_SESSION['flash_error'] = $resultado['message'];
        }

        header('Location: /mascotas');
        exit;
    }

    /**
     * Alias de baja() para compatibilidad con rutas /eliminar.
     */
    public function delete(): void {
        $this->baja();
    }
}
