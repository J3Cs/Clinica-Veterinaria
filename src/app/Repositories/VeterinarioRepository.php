<?php
namespace App\Repositories;

use App\Models\VeterinarioModel;
use App\Models\UsuarioModel;

/**
 * VeterinarioRepository — US-04, US-12 (T-077), ARQ-01, BAJA-01
 * Repositorio para la entidad Veterinario y su cuenta de usuario asociada.
 */
class VeterinarioRepository {

    private VeterinarioModel $model;
    private ?UsuarioModel $usuarioModel;

    public function __construct(?VeterinarioModel $model = null, ?UsuarioModel $usuarioModel = null) {
        $this->model        = $model ?? new VeterinarioModel();
        $this->usuarioModel = $usuarioModel;
    }

    private function getUsuarioModel(): UsuarioModel {
        $this->usuarioModel ??= new UsuarioModel();
        return $this->usuarioModel;
    }

    /** @return array<array<string,mixed>> */
    public function listActivos(): array {
        return $this->model->findAllActivos();
    }

    /** @return array<array<string,mixed>> */
    public function listAll(bool $onlyActive = false): array {
        return $this->model->findAll($onlyActive);
    }

    /** @return array<string,mixed>|null */
    public function findActivoById(int $id): ?array {
        return $this->model->findActivoById($id);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }

    /** @return array<string,mixed>|null */
    public function findActivoByUsuarioId(int $idUsuario): ?array {
        return $this->model->findActivoByUsuarioId($idUsuario);
    }

    /** Bloquea al veterinario dentro de la transacción en curso. */
    public function bloquearActivo(int $id): bool {
        return $this->model->bloquearActivoPorId($id);
    }

    /**
     * Obtiene las relaciones clínicas/citas asociadas al veterinario (T-077).
     *
     * @return array{
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     horarios_total: int,
     *     tiene_dependencias: bool
     * }
     */
    public function obtenerRelaciones(int $idVeterinario): array {
        return $this->model->contarRelaciones($idVeterinario);
    }

    /**
     * Actualiza los datos profesionales y de usuario de un veterinario (T-077).
     *
     * @param int   $idVeterinario
     * @param int   $idUsuario
     * @param array $datos
     * @return bool
     */
    public function update(int $idVeterinario, int $idUsuario, array $datos): bool {
        $okUser = $this->getUsuarioModel()->update($idUsuario, [
            'nombre'     => $datos['nombre'],
            'correo'     => $datos['correo'],
            'usuario'    => $datos['usuario'],
            'contrasena' => $datos['contrasena'] ?? null,
        ]);

        $okVet = $this->model->update($idVeterinario, [
            'especialidad'    => $datos['especialidad'] ?? null,
            'telefono'        => $datos['telefono'] ?? null,
            'licencia_medica' => $datos['licencia_medica'] ?? null,
        ]);

        return $okUser && $okVet;
    }

    /**
     * Desactiva lógicamente al veterinario a través de su cuenta de usuario (BAJA-01, T-077).
     * Al quedar `usuarios.activo = 0`, el veterinario ya no puede iniciar sesión.
     */
    public function desactivar(int $idUsuario): bool {
        return $this->getUsuarioModel()->setActivo($idUsuario, 0);
    }

    /**
     * Cancela automáticamente las citas en estado 'Programada' de un veterinario desactivado.
     */
    public function cancelarCitasProgramadas(
        int $idVeterinario,
        string $motivo = 'Cancelada automáticamente por desactivación del veterinario'
    ): int {
        if (method_exists($this->model, 'cancelarCitasProgramadas')) {
            return $this->model->cancelarCitasProgramadas($idVeterinario, $motivo);
        }
        return 0;
    }

    /**
     * Reactiva la cuenta de usuario del veterinario.
     */
    public function activar(int $idUsuario): bool {
        return $this->getUsuarioModel()->setActivo($idUsuario, 1);
    }

    /**
     * Elimina físicamente al veterinario y su usuario cuando no existen citas ni consultas (T-077).
     */
    public function deleteFisico(int $idVeterinario, int $idUsuario): bool {
        $okVet = $this->model->delete($idVeterinario);
        if (!$okVet) {
            return false;
        }
        return $this->getUsuarioModel()->delete($idUsuario);
    }
}
