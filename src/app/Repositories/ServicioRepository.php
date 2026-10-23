<?php
namespace App\Repositories;

use App\Models\ServicioModel;

/**
 * ServicioRepository — US-04 (T-031), ARQ-01
 * Servicios activos y su duración estimada.
 */
class ServicioRepository {

    private ServicioModel $model;

    public function __construct(?ServicioModel $model = null) {
        $this->model = $model ?? new ServicioModel();
    }

    /** @return array<array<string,mixed>> */
    public function listActivos(): array {
        return $this->model->findAllActivos();
    }

    /** @return array<string,mixed>|null Solo si existe y está activo. */
    public function findActivoById(int $id): ?array {
        return $this->model->findActivoById($id);
    }
}
