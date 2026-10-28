<?php
namespace App\Controllers;

use App\Services\VeterinariosService;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * VeterinarioController — US-12 (T-078), ARQ-01, ACC-01, SEC-01, BAJA-01
 * Controlador para las operaciones de edición y baja controlada de veterinarios.
 */
class VeterinarioController {

    private VeterinariosService $service;

    public function __construct(?VeterinariosService $service = null) {
        $this->service = $service ?? new VeterinariosService();
    }

    /**
     * POST /veterinarios/editar — Actualiza los datos de un veterinario (T-078)
     * Requiere rol Administrador y token CSRF válido.
     */
    public function update(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id = filter_var($_POST['id_veterinario'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de veterinario no válido.';
            header('Location: /admin/dashboard');
            exit;
        }

        $datos = [
            'nombre'          => $_POST['nombre']          ?? '',
            'correo'          => $_POST['correo']          ?? '',
            'usuario'         => $_POST['usuario']         ?? '',
            'especialidad'    => $_POST['especialidad']    ?? '',
            'telefono'        => $_POST['telefono']        ?? '',
            'licencia_medica' => $_POST['licencia_medica'] ?? '',
        ];

        $resultado = $this->service->actualizarVeterinario($id, $datos);

        if ($resultado['success']) {
            $_SESSION['flash_success'] = $resultado['message'];
        } else {
            $_SESSION['flash_error']  = $resultado['message'];
            $_SESSION['field_errors'] = $resultado['errors'];
            $_SESSION['old_data']     = $datos;
        }

        header('Location: /admin/dashboard');
        exit;
    }

    /**
     * POST /veterinarios/baja (y /veterinarios/eliminar) — Baja controlada o reactivación (T-077, T-078, BAJA-01)
     * Requiere rol Administrador y token CSRF válido.
     */
    public function baja(): void {
        AuthMiddleware::requireRole('administrador');
        CsrfMiddleware::validateToken($_POST['csrf_token'] ?? '');

        $id     = filter_var($_POST['id_veterinario'] ?? null, FILTER_VALIDATE_INT);
        $accion = trim((string) ($_POST['accion'] ?? 'baja'));

        if (!$id || $id <= 0) {
            $_SESSION['flash_error'] = 'Identificador de veterinario no válido.';
            header('Location: /admin/dashboard');
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

        header('Location: /admin/dashboard');
        exit;
    }

    /**
     * Alias de baja() para compatibilidad con rutas /eliminar.
     */
    public function delete(): void {
        $this->baja();
    }
}
