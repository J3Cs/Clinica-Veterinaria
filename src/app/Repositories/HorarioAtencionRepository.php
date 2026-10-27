<?php
namespace App\Repositories;

use App\Models\HorarioAtencionModel;

/**
 * HorarioAtencionRepository — US-04 (T-031), ARQ-01
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
}
