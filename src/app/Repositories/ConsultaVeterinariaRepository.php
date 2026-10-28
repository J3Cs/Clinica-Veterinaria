<?php
namespace App\Repositories;

use App\Models\ConsultaVeterinariaModel;

/**
 * ConsultaVeterinariaRepository — US-06 (T-043), ARQ-01, DAT-01
 * Inserta consultas y comprueba si una cita ya tiene la suya.
 */
class ConsultaVeterinariaRepository {

    private ConsultaVeterinariaModel $model;

    public function __construct(?ConsultaVeterinariaModel $model = null) {
        $this->model = $model ?? new ConsultaVeterinariaModel();
    }

    /**
     * @param array<string,mixed> $data
     * @return int ID de la consulta creada.
     */
    public function create(array $data): int {
        return $this->model->insert($data);
    }

    /** @return array<string,mixed>|null */
    public function findByCitaId(int $idCita): ?array {
        return $this->model->findByCitaId($idCita);
    }

    public function existsForCita(int $idCita): bool {
        return $this->model->findByCitaId($idCita) !== null;
    }

    /** @return array<array<string,mixed>> */
    public function listByMascota(int $idMascota, int $limite = 50): array {
        return $this->model->findByMascota($idMascota, $limite);
    }
}
