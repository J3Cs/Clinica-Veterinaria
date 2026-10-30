<?php
namespace App\Controllers;

use App\Middlewares\AuthMiddleware;
use App\Repositories\MascotaRepository;
use App\Services\ConsultaService;

/**
 * US-07: consulta del expediente clínico consolidado de una mascota.
 * Acceso de solo lectura para veterinario y administrador.
 */
class HistorialController {
    private MascotaRepository $mascotas;
    private ConsultaService $consultas;

    public function __construct(?MascotaRepository $mascotas = null, ?ConsultaService $consultas = null) {
        $this->mascotas = $mascotas ?? new MascotaRepository();
        $this->consultas = $consultas ?? new ConsultaService();
    }

    /** GET /mascotas/historial?id=ID */
    public function detalle(): void {
        AuthMiddleware::requireLogin();
        $rol = (string) ($_SESSION['rol'] ?? '');
        if (!in_array($rol, ['veterinario', 'administrador'], true)) {
            http_response_code(403);
            echo 'No tienes permiso para consultar historiales clínicos.';
            return;
        }

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(400);
            $_SESSION['flash_error'] = 'El identificador de la mascota no es válido.';
            header('Location: /mascotas');
            exit;
        }

        $mascota = $this->mascotas->findById($id);
        if (!$mascota) {
            http_response_code(404);
            echo 'Mascota no encontrada.';
            return;
        }

        $historial = $this->consultas->historialClinico((int) $mascota['id_mascota'], 200);
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        require __DIR__ . '/../Views/historial/detalle.php';
    }
}
