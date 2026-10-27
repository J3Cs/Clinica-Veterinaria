<?php
namespace App\Repositories;

use App\Models\ServicioModel;

/**
 * ServicioRepository — US-04 (T-031), US-10 (T-066), ARQ-01
 * Repositorio desacoplado para la entidad Servicio.
 * CRUD completo con verificación de integridad referencial (BAJA-01).
 */
class ServicioRepository {

    private ServicioModel $model;

    public function __construct(?ServicioModel $model = null) {
        $this->model = $model ?? new ServicioModel();
    }

    // ────────────────────────────────────────────────────────────── //
    //  Lectura
    // ────────────────────────────────────────────────────────────── //

    /**
     * Lista servicios. $soloActivos = false incluye todos para la vista admin.
     * @return array<array<string,mixed>>
     */
    public function listAll(bool $soloActivos = true): array {
        return $this->model->findAll($soloActivos);
    }

    /** Alias de compatibilidad con US-04. */
    public function listActivos(): array {
        return $this->model->findAllActivos();
    }

    /**
     * Busca por ID sin filtro de estado (para edición).
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }

    /** Solo si existe y está activo (compatibilidad US-04). */
    public function findActivoById(int $id): ?array {
        return $this->model->findActivoById($id);
    }

    /**
     * Busca servicio por nombre (case-insensitive), excluyendo opcionalmente un ID.
     * @return array<string,mixed>|null
     */
    public function findByNombre(string $nombre, ?int $excludeId = null): ?array {
        return $this->model->findByNombre($nombre, $excludeId);
    }

    /**
     * Verifica si el servicio tiene citas asociadas (BAJA-01).
     */
    public function tieneCitasAsociadas(int $id): bool {
        return $this->model->tieneCitasAsociadas($id);
    }

    // ────────────────────────────────────────────────────────────── //
    //  Escritura
    // ────────────────────────────────────────────────────────────── //

    /**
     * Crea un nuevo servicio.
     * @return int ID del servicio creado
     */
    public function create(array $data): int {
        return $this->model->insert($data);
    }

    /**
     * Actualiza un servicio existente.
     */
    public function update(int $id, array $data): bool {
        return $this->model->update($id, $data);
    }

    /**
     * Desactiva un servicio (baja lógica — BAJA-01).
     */
    public function desactivar(int $id): bool {
        return $this->model->desactivar($id);
    }

    /**
     * Reactiva un servicio inactivo.
     */
    public function activar(int $id): bool {
        return $this->model->activar($id);
    }
}
