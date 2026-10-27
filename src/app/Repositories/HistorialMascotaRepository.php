<?php
namespace App\Repositories;

use App\Models\HistorialMascotaModel;

/**
 * HistorialMascotaRepository — US-06 (T-044), ARQ-01
 * Antecedentes médicos previos de una mascota (solo lectura).
 */
class HistorialMascotaRepository {

    private HistorialMascotaModel $model;

    public function __construct(?HistorialMascotaModel $model = null) {
        $this->model = $model ?? new HistorialMascotaModel();
    }

    /** @return array<array<string,mixed>> */
    public function listByMascota(int $idMascota, int $limite = 50): array {
        return $this->model->findByMascota($idMascota, $limite);
    }
}
