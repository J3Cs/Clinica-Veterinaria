<?php
namespace App\Repositories;

use App\Models\HorarioAtencionModel;

/**
 * HorarioAtencionRepository — US-04 (T-031), US-11 (T-071), ARQ-01
 * Franjas de atención definidas para cada veterinario.
 */
class HorarioAtencionRepository {

    private HorarioAtencionModel $model;

    public function __construct(?HorarioAtencionModel $model = null) {
        $this->model = $model ?? new HorarioAtencionModel();
    }

    /**
     * @param int $diaSemana 1 = lunes … 7 = domingo
     * @return array<array{hora_inicio:string,hora_fin:string}>
     */
    public function listByVeterinarioYDia(int $idVeterinario, int $diaSemana): array {
        return $this->model->findByVeterinarioYDia($idVeterinario, $diaSemana);
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function listAll(): array {
        return $this->model->findAll();
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function listByVeterinario(int $idVeterinario): array {
        return $this->model->findByVeterinario($idVeterinario);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $idHorario): ?array {
        return $this->model->findById($idHorario);
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function findSolapadas(int $idVeterinario, int $diaSemana, string $horaInicio, string $horaFin, ?int $excluirIdHorario = null): array {
        return $this->model->findSolapadas($idVeterinario, $diaSemana, $horaInicio, $horaFin, $excluirIdHorario);
    }

    /**
     * Crea una nueva franja horaria.
     */
    public function create(int $idVeterinario, int $diaSemana, string $horaInicio, string $horaFin): int {
        return $this->model->insert($idVeterinario, $diaSemana, $horaInicio, $horaFin);
    }

    /**
     * Actualiza una franja horaria.
     */
    public function update(int $idHorario, int $diaSemana, string $horaInicio, string $horaFin): bool {
        return $this->model->update($idHorario, $diaSemana, $horaInicio, $horaFin);
    }

    /**
     * Elimina una franja horaria.
     */
    public function delete(int $idHorario): bool {
        return $this->model->delete($idHorario);
    }
}
