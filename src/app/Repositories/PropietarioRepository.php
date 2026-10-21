<?php
namespace App\Repositories;

use App\Models\PropietarioModel;

/**
 * PropietarioRepository — T-017, ARQ-01, UNI-01
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
     * Lista los propietarios registrados.
     *
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function listAll(bool $onlyActive = true): array {
        return $this->model->findAll($onlyActive);
    }
}

