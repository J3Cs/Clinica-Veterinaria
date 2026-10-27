<?php
namespace App\Controllers;

use App\Services\HorariosServicio;
use App\Repositories\VeterinarioRepository;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

/**
 * HorarioAtencionControlador — US-11 (T-073), ARQ-01, ACC-01, SEC-01
 * Controlador para la gestión y asignación de horarios de atención médica.
 * Todas las acciones requieren rol Administrador (ACC-01).
 */
class HorarioAtencionControlador {

    private HorariosServicio $service;
    private VeterinarioRepository $vetRepo;

    public function __construct(
        ?HorariosServicio $service = null,
        ?VeterinarioRepository $vetRepo = null
    ) {
        $this->service = $service ?? new HorariosServicio();
        $this->vetRepo = $vetRepo ?? new VeterinarioRepository();
    }

    // ────────────────────────────────────────────────────────────── //
    //  GET /horarios — Listado y matriz semanal
    // ────────────────────────────────────────────────────────────── //

    public function index(): void {
        AuthMiddleware::requireRole('administrador');

        $filtroVet = filter_var($_GET['id_veterinario'] ?? null, FILTER_VALIDATE_INT);
        $veterinarios = $this->vetRepo->listActivos();

        if ($filtroVet && $filtroVet > 0) {
            $horarios = $this->service->listarPorVeterinario($filtroVet);
        } else {
            $horarios = $this->service->listarTodos();
            $filtroVet = null;
        }

        $csrfToken    = CsrfMiddleware::generateToken();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/horarios/listar.php';
    }

    // ────────────────────────────────────────────────────────────── //
    //  GET /horarios/crear — Formulario de asignación
    // ────────────────────────────────────────────────────────────── //

    public function create(): void {
        AuthMiddleware::requireRole('administrador');

        $veterinarios = $this->vetRepo->listActivos();
        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error']  ?? null;
        $fieldErrors  = $_SESSION['field_errors'] ?? [];
        $oldData      = $_SESSION['old_data']     ?? [];
        $modoEdicion  = false;
        $horario      = null;

        unset($_SESSION['flash_error'], $_SESSION['field_errors'], $_SESSION['old_data']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/horarios/formulario.php';
    }

    // ────────────────────────────────────────────────────────────── //
    //  POST /horarios — Guardar nueva franja
    // ────────────────────────────────────────────────────────────── //

    public function store(): void {
        AuthMiddleware::requireRole('administrador');

        $token = $_POST['csrf_token'] ?? '';
        if (!CsrfMiddleware::validateToken($token)) {
            $_SESSION['flash_error'] = 'Token de seguridad inválido o expirado. Intente nuevamente.';
            header('Location: /horarios/crear');
            exit;
        }

        $datos = [
            'id_veterinario' => $_POST['id_veterinario'] ?? null,
            'dia_semana'     => $_POST['dia_semana']     ?? null,
            'hora_inicio'    => $_POST['hora_inicio']    ?? '',
            'hora_fin'       => $_POST['hora_fin']       ?? '',
        ];

        $resultado = $this->service->crearHorario($datos);

        if (!$resultado['ok']) {
            $_SESSION['flash_error']  = $resultado['error'] ?? 'No se pudo guardar la franja horaria.';
            $_SESSION['field_errors'] = $resultado['field_errors'] ?? [];
            $_SESSION['old_data']     = $datos;
            header('Location: /horarios/crear');
            exit;
        }

        $_SESSION['flash_success'] = 'Franja horaria asignada exitosamente.';
        header('Location: /horarios');
        exit;
    }

    // ────────────────────────────────────────────────────────────── //
    //  GET /horarios/editar — Formulario de edición
    // ────────────────────────────────────────────────────────────── //

    public function edit(): void {
        AuthMiddleware::requireRole('administrador');

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            $_SESSION['flash_error'] = 'Identificador de horario no válido.';
            header('Location: /horarios');
            exit;
        }

        $horario = $this->service->obtenerPorId($id);
        if (!$horario) {
            $_SESSION['flash_error'] = 'Franja horaria no encontrada.';
            header('Location: /horarios');
            exit;
        }

        $veterinarios = $this->vetRepo->listActivos();
        $csrfToken    = CsrfMiddleware::generateToken();
        $flashError   = $_SESSION['flash_error']  ?? null;
        $fieldErrors  = $_SESSION['field_errors'] ?? [];
        $oldData      = $_SESSION['old_data']     ?? [];
        $modoEdicion  = true;

        unset($_SESSION['flash_error'], $_SESSION['field_errors'], $_SESSION['old_data']);

        $nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $rolUsuario    = htmlspecialchars($_SESSION['rol']    ?? '', ENT_QUOTES, 'UTF-8');

        require_once __DIR__ . '/../Views/horarios/formulario.php';
    }

    // ────────────────────────────────────────────────────────────── //
    //  POST /horarios/editar — Procesar actualización
    // ────────────────────────────────────────────────────────────── //

    public function update(): void {
        AuthMiddleware::requireRole('administrador');

        $token = $_POST['csrf_token'] ?? '';
        $id    = filter_var($_POST['id_horario'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            $_SESSION['flash_error'] = 'Identificador de franja no válido.';
            header('Location: /horarios');
            exit;
        }

        if (!CsrfMiddleware::validateToken($token)) {
            $_SESSION['flash_error'] = 'Token de seguridad inválido o expirado. Intente nuevamente.';
            header('Location: /horarios/editar?id=' . $id);
            exit;
        }

        $datos = [
            'dia_semana'  => $_POST['dia_semana']  ?? null,
            'hora_inicio' => $_POST['hora_inicio'] ?? '',
            'hora_fin'    => $_POST['hora_fin']    ?? '',
        ];

        $resultado = $this->service->actualizarHorario($id, $datos);

        if (!$resultado['ok']) {
            $_SESSION['flash_error']  = $resultado['error'] ?? 'No se pudo actualizar la franja horaria.';
            $_SESSION['field_errors'] = $resultado['field_errors'] ?? [];
            $_SESSION['old_data']     = $datos;
            header('Location: /horarios/editar?id=' . $id);
            exit;
        }

        $_SESSION['flash_success'] = 'Franja horaria actualizada correctamente.';
        header('Location: /horarios');
        exit;
    }

    // ────────────────────────────────────────────────────────────── //
    //  POST /horarios/eliminar — Eliminación física controlada
    // ────────────────────────────────────────────────────────────── //

    public function delete(): void {
        AuthMiddleware::requireRole('administrador');

        $token = $_POST['csrf_token'] ?? '';
        $id    = filter_var($_POST['id_horario'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            $_SESSION['flash_error'] = 'Identificador de franja no válido.';
            header('Location: /horarios');
            exit;
        }

        if (!CsrfMiddleware::validateToken($token)) {
            $_SESSION['flash_error'] = 'Token de seguridad inválido o expirado. Intente nuevamente.';
            header('Location: /horarios');
            exit;
        }

        $resultado = $this->service->eliminarHorario($id);

        if (!$resultado['ok']) {
            $_SESSION['flash_error'] = $resultado['error'] ?? 'No se pudo eliminar la franja.';
        } else {
            $_SESSION['flash_success'] = 'Franja horaria eliminada exitosamente.';
        }

        header('Location: /horarios');
        exit;
    }
}
