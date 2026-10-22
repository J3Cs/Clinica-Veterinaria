<?php
namespace App\Repositories;

use App\Models\MascotaModel;

/**
 * MascotaRepository — US-03, ARQ-01, DAT-01, BAJA-01
 * Capa intermedia entre el servicio y el modelo de datos para la entidad Mascota.
 */
class MascotaRepository {

    private MascotaModel $model;

    public function __construct(?MascotaModel $model = null) {
        $this->model = $model ?? new MascotaModel();
    }

    /**
     * Persiste una nueva mascota.
     *
     * @param array{
     *     id_propietario: int,
     *     nombre: string,
     *     especie: string,
     *     raza: string,
     *     sexo: string,
     *     fecha_nacimiento?: ?string,
     *     caracteristicas_relevantes?: ?string,
     *     activo?: int
     * } $data
     * @return int ID generado de la mascota.
     */
    public function create(array $data): int {
        return $this->model->insert($data);
    }

    /**
     * Busca una mascota por su identificador primario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }

    /**
     * Lista todas las mascotas (con información de su propietario).
     *
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function listAll(bool $onlyActive = true): array {
        return $this->model->findAll($onlyActive);
    }

    /**
     * Lista las mascotas de un propietario determinado.
     *
     * @param int $idPropietario
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function listByPropietario(int $idPropietario, bool $onlyActive = true): array {
        return $this->model->findByPropietarioId($idPropietario, $onlyActive);
    }

    /**
     * Retorna la cantidad total de mascotas activas.
     *
     * @return int
     */
    public function count(): int {
        return $this->model->countActive();
    }
}
