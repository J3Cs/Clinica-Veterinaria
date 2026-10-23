<?php
namespace App\Controllers;

use App\Services\CitaService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * CitaController — US-04 (T-033), ARQ-01, ACC-01, SEC-01
 * Coordina el flujo de agendamiento. Acciones restringidas al rol Administrador.
 *
 * Flujo sin JavaScript:
 *   1. GET  /citas/crear                    → formulario de selección.
 *   2. GET  /citas/crear?consultar=1&...    → muestra los horarios disponibles.
 *   3. POST /citas                          → confirma; el servicio revalida y guarda.
 */
class CitaController {

    /** Únicos datos que el controlador acepta del cliente. */
    private const CAMPOS = ['id_mascota', 'id_servicio', 'id_veterinario', 'fecha'];

    private CitaService $service;

    public function __construct(?CitaService $service = null) {
        $this->service = $service ?? new CitaService();
    }

    /**
     * GET /citas/crear — Formulario y, si se pidió, disponibilidad.
     */
    public function create(): void {
        AuthMiddleware::requireRole('administrador');

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        $filtros  = $this->filtrosDesde($_GET);
        $opciones = $this->service->obtenerOpciones();

        $consulta = null;
        if (isset($_GET['consultar'])) {
            $consulta = $this->service->consultarDisponibilidad($filtros);
        }

        // Errores de un intento de guardado anterior (hora ya ocupada, etc.)
        $fieldErrors = $_SESSION['field_errors'] ?? [];
        unset($_SESSION['field_errors']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/citas/crear.php';
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
            header('Location: /citas/crear');
            exit;
        }

        // Volver a la pantalla de horarios, ya actualizada, conservando la selección.
        $_SESSION['flash_error']  = $resultado['message'];
        $_SESSION['field_errors'] = $resultado['errors'];

        $query = http_build_query(['consultar' => 1] + $this->filtrosDesde($datos));
        header('Location: /citas/crear?' . $query);
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
