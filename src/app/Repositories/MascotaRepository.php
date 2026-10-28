<?php
namespace App\Repositories;

use App\Models\MascotaModel;

/**
 * MascotaRepository — US-03, US-12 (T-076, T-090), ARQ-01, DAT-01, BAJA-01
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
     * Actualiza una mascota existente (T-076).
     *
     * @param int $id
     * @param array{
     *     id_propietario: int,
     *     nombre: string,
     *     especie: string,
     *     raza: string,
     *     sexo: string,
     *     fecha_nacimiento?: ?string,
     *     caracteristicas_relevantes?: ?string
     * } $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        return $this->model->update($id, $data);
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
     * Carga una mascota junto con su propietario y el detalle completo de sus relaciones clínicas (T-076).
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findConRelaciones(int $id): ?array {
        $mascota = $this->model->findById($id);
        if (!$mascota) {
            return null;
        }
        $relaciones = $this->obtenerRelaciones($id);
        $mascota['relaciones'] = $relaciones;
        return $mascota;
    }

    /**
     * Lista todas las mascotas (con información de su propietario y exclusión de inactivas por defecto — T-090).
     *
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function listAll(bool $onlyActive = true): array {
        return $this->model->findAll($onlyActive);
    }

    /**
     * Busca mascotas con parámetros preparados (T-076, T-090).
     *
     * @param string|null $termino
     * @param bool        $onlyActive
     * @param int|null    $idPropietario
     * @return array<array<string,mixed>>
     */
    public function search(?string $termino = null, bool $onlyActive = true, ?int $idPropietario = null): array {
        $limpio = trim((string) $termino);
        if ($limpio === '' && $idPropietario === null) {
            return $this->model->findAll($onlyActive);
        }
        return $this->model->search($limpio, $onlyActive, $idPropietario);
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
     * Obtiene las relaciones clínicas asociadas a una mascota (citas, consultas, historial) (T-076, T-077).
     *
     * @param int $idMascota
     * @return array{
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     historial_total: int,
     *     tiene_dependencias: bool,
     *     citas: array<array<string,mixed>>,
     *     consultas: array<array<string,mixed>>,
     *     antecedentes: array<array<string,mixed>>
     * }
     */
    public function obtenerRelaciones(int $idMascota): array {
        return $this->model->obtenerRelacionesClinicas($idMascota);
    }

    /**
     * Indica si la mascota posee citas, consultas o antecedentes médicos.
     */
    public function tieneDependenciasClinicas(int $idMascota): bool {
        $rel = $this->obtenerRelaciones($idMascota);
        return (bool) ($rel['tiene_dependencias'] ?? false);
    }

    /**
     * Baja lógica de una mascota (BAJA-01, T-077).
     */
    public function desactivar(int $id): bool {
        return $this->model->desactivar($id);
    }

    /**
     * Reactivación de una mascota.
     */
    public function activar(int $id): bool {
        return $this->model->activar($id);
    }

    /**
     * Desactiva todas las mascotas de un propietario cuando éste se da de baja lógicamente (T-077).
     */
    public function desactivarPorPropietario(int $idPropietario): int {
        return $this->model->desactivarPorPropietario($idPropietario);
    }

    /**
     * Eliminación física de una mascota (solo cuando no tiene dependencias clínicas — T-077).
     */
    public function delete(int $id): bool {
        return $this->model->delete($id);
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
