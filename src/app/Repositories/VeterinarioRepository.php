<?php
namespace App\Repositories;

use App\Models\VeterinarioModel;

/**
 * VeterinarioRepository — US-04, ARQ-01
 * Veterinarios con cuenta activa.
 */
class VeterinarioRepository {

    private VeterinarioModel $model;

    public function __construct(?VeterinarioModel $model = null) {
        $this->model = $model ?? new VeterinarioModel();
    }

    /** @return array<array<string,mixed>> */
    public function listActivos(): array {
        return $this->model->findAllActivos();
    }

    /** @return array<string,mixed>|null */
    public function findActivoById(int $id): ?array {
        return $this->model->findActivoById($id);
    }

    /** Bloquea al veterinario dentro de la transacción en curso. */
    public function bloquearActivo(int $id): bool {
        return $this->model->bloquearActivoPorId($id);
    }
}
