<?php
namespace App\Repositories;

use App\Models\PropietarioModel;

/**
 * PropietarioRepository — T-017, T-076, T-090, ARQ-01, UNI-01, BAJA-01
 * Capa intermedia entre el servicio y el modelo de datos para la entidad Propietario.
 */
class PropietarioRepository {

    private PropietarioModel $model;

    public function __construct(?PropietarioModel $model = null) {
        $this->model = $model ?? new PropietarioModel();
    }

    /**
     * Persiste un nuevo propietario.
     *
     * @param array{dui: string, nombre_completo: string, direccion?: ?string, telefono?: ?string, correo?: ?string, activo?: int} $data
     * @return int ID generado.
     */
    public function create(array $data): int {
        return $this->model->insert($data);
    }

    /**
     * Actualiza un propietario existente (T-076).
     *
     * @param int $id
     * @param array{dui: string, nombre_completo: string, direccion?: ?string, telefono?: ?string, correo?: ?string} $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        return $this->model->update($id, $data);
    }

    /**
     * Busca un propietario por su número de DUI.
     *
     * @param string $dui
     * @return array<string,mixed>|null
     */
    public function findByDui(string $dui): ?array {
        return $this->model->findByDui(trim($dui));
    }

    /**
     * Busca un propietario por su identificador primario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }

    /**
     * Comprueba si un DUI ya se encuentra registrado en el sistema (UNI-01).
     *
     * @param string $dui
     * @param int|null $excludeId
     * @return bool
     */
    public function isDuiRegistered(string $dui, ?int $excludeId = null): bool {
        return $this->model->existsDui(trim($dui), $excludeId);
    }

    /**
     * Lista los propietarios registrados (excluye inactivos por defecto — T-090).
     *
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function listAll(bool $onlyActive = true): array {
        return $this->model->findAll($onlyActive);
    }

    /**
     * Busca propietarios usando parámetros preparados (T-076, T-090).
     *
     * @param string|null $termino
     * @param bool        $onlyActive
     * @return array<array<string,mixed>>
     */
    public function search(?string $termino = null, bool $onlyActive = true): array {
        $limpio = trim((string) $termino);
        if ($limpio === '') {
            return $this->model->findAll($onlyActive);
        }
        return $this->model->search($limpio, $onlyActive);
    }

    /**
     * Retorna el resumen de relaciones asociadas al propietario (T-076, T-077).
     *
     * @param int $idPropietario
     * @return array{
     *     mascotas_total: int,
     *     mascotas_activas: int,
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     historial_total: int,
     *     tiene_dependencias: bool,
     *     tiene_datos_clinicos: bool
     * }
     */
    public function obtenerRelaciones(int $idPropietario): array {
        return $this->model->contarRelaciones($idPropietario);
    }

    /**
     * Indica si el propietario tiene dependencias (mascotas o datos clínicos).
     */
    public function tieneDependencias(int $idPropietario): bool {
        $rel = $this->obtenerRelaciones($idPropietario);
        return (bool) ($rel['tiene_dependencias'] ?? false);
    }

    /**
     * Baja lógica del propietario (BAJA-01, T-077).
     */
    public function desactivar(int $id): bool {
        return $this->model->desactivar($id);
    }

    /**
     * Reactivación del propietario.
     */
    public function activar(int $id): bool {
        return $this->model->activar($id);
    }

    /**
     * Eliminación física del propietario (solo cuando no tiene dependencias — T-077).
     */
    public function delete(int $id): bool {
        return $this->model->delete($id);
    }
}
