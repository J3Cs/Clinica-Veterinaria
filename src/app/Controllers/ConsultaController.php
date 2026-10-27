<?php
namespace App\Controllers;

use App\Services\ConsultaService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * ConsultaController — US-06 (T-045), ARQ-01, ACC-01, SEC-01
 * Pantalla de registro de la consulta y su guardado. Solo rol Veterinario.
 *
 * Rutas:
 *   GET  /consultas/crear?id_cita=N → formulario de la consulta de esa cita.
 *   POST /consultas                 → guarda la consulta y pasa la cita a Atendida.
 *
 * El cliente solo aporta el identificador de la cita y los cuatro textos clínicos.
 * La mascota, el veterinario, el estado y la fecha se toman de la base de datos;
 * cualquier otro campo enviado se ignora.
 */
class ConsultaController {

    /** Únicos campos de texto que se aceptan del formulario. */
    private const CAMPOS = ['diagnostico', 'observaciones', 'tratamiento', 'servicios_adicionales'];

    private ConsultaService $service;

    public function __construct(?ConsultaService $service = null) {
        $this->service = $service ?? new ConsultaService();
    }

    /**
     * GET /consultas/crear?id_cita=N
     */
    public function crear(): void {
        AuthMiddleware::requireRole('veterinario');

        $idCita = is_string($_GET['id_cita'] ?? null) ? $_GET['id_cita'] : '';
        $res    = $this->service->prepararConsulta($_SESSION['id_usuario'] ?? 0, $idCita);

        if (!$res['success']) {
            $_SESSION['flash_error'] = $res['message'];
            header('Location: /veterinario/agenda');
            exit;
        }

        $cita      = $res['cita'];
        $historial = $res['historial'];
        $csrfToken = CsrfMiddleware::generateToken();

        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        // Lo escrito en un intento anterior que falló (solo si es de esta misma cita)
        $fieldErrors = $_SESSION['consulta_errors'] ?? [];
        $old         = $_SESSION['consulta_old'] ?? [];
        unset($_SESSION['consulta_errors'], $_SESSION['consulta_old']);
        if ((string) ($old['id_cita'] ?? '') !== (string) $cita['id_cita']) {
            $old = [];
            $fieldErrors = [];
        }

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/consultas/crear.php';
    }

    /**
     * POST /consultas
     */
    public function store(): void {
        AuthMiddleware::requireRole('veterinario');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $idCita = is_string($_POST['id_cita'] ?? null) ? $_POST['id_cita'] : '';

        $datos = [];
        foreach (self::CAMPOS as $campo) {
            $valor = $_POST[$campo] ?? null;
            $datos[$campo] = is_string($valor) ? $valor : null;
        }

        $res = $this->service->registrarConsulta($_SESSION['id_usuario'] ?? 0, $idCita, $datos);

        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
            header('Location: /veterinario/agenda');
            exit;
        }

        $_SESSION['flash_error'] = $res['message'];

        if ($res['reintentable'] && ctype_digit($idCita)) {
            // Volver al formulario conservando lo escrito
            $_SESSION['consulta_errors'] = $res['errors'];
            $_SESSION['consulta_old']    = ['id_cita' => $idCita] + $datos;
            header('Location: /consultas/crear?id_cita=' . (int) $idCita);
            exit;
        }

        header('Location: /veterinario/agenda');
        exit;
    }
}
