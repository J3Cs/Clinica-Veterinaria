<?php
namespace App\Controllers;

use App\Services\CitaService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * CitaController — US-04 (T-033) y US-05 (T-039), ARQ-01, ACC-01, SEC-01
 * Coordina la pestaña «Citas»: formulario de agendamiento, listado y cancelación.
 * Todas las acciones están restringidas al rol Administrador.
 *
 * Rutas:
 *   GET  /citas                    → formulario de agendar + listado de citas.
 *   GET  /citas?consultar=1&...    → igual, mostrando los horarios disponibles.
 *   POST /citas                    → confirma una cita nueva.
 *   GET  /citas/cancelar?id=N      → pantalla de confirmación de cancelación.
 *   POST /citas/cancelar           → cancela la cita (CSRF).
 *   GET  /citas/crear              → redirige a /citas (compatibilidad).
 */
class CitaController {

    /** Únicos datos de agendamiento que el controlador acepta del cliente. */
    private const CAMPOS = ['id_mascota', 'id_servicio', 'id_veterinario', 'fecha'];

    private CitaService $service;

    public function __construct(?CitaService $service = null) {
        $this->service = $service ?? new CitaService();
    }

    /**
     * GET /citas — Pestaña de citas: formulario de agendar y listado.
     */
    public function index(): void {
        AuthMiddleware::requireRole('administrador');

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        // Formulario de agendamiento
        $filtros  = $this->filtrosDesde($_GET);
        $opciones = $this->service->obtenerOpciones();

        $consulta = null;
        if (isset($_GET['consultar'])) {
            $consulta = $this->service->consultarDisponibilidad($filtros);
        }

        // Errores de un intento de guardado anterior (hora ya ocupada, etc.)
        $fieldErrors = $_SESSION['field_errors'] ?? [];
        unset($_SESSION['field_errors']);

        // Criterios para el listado filtrable de citas (T-056, RF08)
        $criteriosListado = [
            'estado'         => $_GET['estado'] ?? 'Programada',
            'fecha_desde'    => $_GET['fecha_desde'] ?? null,
            'fecha_hasta'    => $_GET['fecha_hasta'] ?? null,
            'id_veterinario' => $_GET['filtro_veterinario'] ?? ($_GET['id_veterinario_filtro'] ?? ($_GET['id_veterinario'] ?? null)),
            'q'              => $_GET['q'] ?? null,
        ];

        // Consulta filtrada en servicio con validación previa (T-055, T-056)
        $listado = $this->service->listarCitas($criteriosListado);

        // Si la validación de filtros arrojó errores, notificar al usuario
        if (!empty($listado['errors'])) {
            $flashError = implode(' ', $listado['errors']);
        }

        $citas          = $listado['citas'];
        $estadoFiltro   = $listado['estado'];
        $filtrosListado = $listado['filtros'] ?? [];
        $busqueda       = isset($filtrosListado['q']) && is_string($filtrosListado['q']) ? $filtrosListado['q'] : (isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '');

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/citas/listar.php';
    }

    /**
     * GET /citas/crear — El formulario ahora vive en /citas.
     */
    public function create(): void {
        AuthMiddleware::requireRole('administrador');

        $query = array_filter($this->filtrosDesde($_GET), static fn(string $v): bool => $v !== '');
        if (isset($_GET['consultar'])) {
            $query = ['consultar' => 1] + $query;
        }

        header('Location: /citas' . ($query !== [] ? '?' . http_build_query($query) : ''));
        exit;
    }

    /**
     * POST /citas — Crea la cita. La validación definitiva ocurre en el servicio.
     */
    public function store(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $datos = $this->filtrosDesde($_POST);
        $datos['hora_cita'] = is_string($_POST['hora_cita'] ?? null) ? $_POST['hora_cita'] : '';

        $resultado = $this->service->agendarCita($datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
            header('Location: /citas');
            exit;
        }

        // Volver a la pantalla de horarios, ya actualizada, conservando la selección.
        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];

        $query = http_build_query(['consultar' => 1] + $this->filtrosDesde($datos));
        header('Location: /citas?' . $query);
        exit;
    }

    /**
     * GET /citas/cancelar?id=N — Pide confirmación (y motivo opcional) antes de cancelar.
     */
    public function confirmarCancelacion(): void {
        AuthMiddleware::requireRole('administrador');

        $id  = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
        $res = $this->service->obtenerParaCancelar($id);

        if (!$res['success']) {
            $_SESSION['flash_error'] = $res['message'];
            header('Location: /citas');
            exit;
        }

        $cita         = $res['cita'];
        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/citas/confirmar_cancelacion.php';
    }

    /**
     * POST /citas/cancelar — Cancela la cita. Sin permiso o con token inválido no cambia datos.
     */
    public function cancelar(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id     = is_string($_POST['id_cita'] ?? null) ? $_POST['id_cita'] : '';
        $motivo = is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : null;

        $resultado = $this->service->cancelarCita($id, $motivo);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
        } else {
            $_SESSION['flash_error'] = $resultado['message'];
        }

        header('Location: /citas');
        exit;
    }

    /**
     * Copia solo los campos esperados y solo si son texto (ignora arrays y extras).
     *
     * @param array<string,mixed> $origen
     * @return array<string,string>
     */
    private function filtrosDesde(array $origen): array {
        $filtros = [];
        foreach (self::CAMPOS as $campo) {
            $valor = $origen[$campo] ?? '';
            $filtros[$campo] = is_string($valor) ? trim($valor) : '';
        }
        return $filtros;
    }
}
