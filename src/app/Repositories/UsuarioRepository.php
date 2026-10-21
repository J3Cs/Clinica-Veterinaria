<?php
namespace App\Repositories;

use App\Models\UsuarioModel;

/**
 * UsuarioRepository — T-010, ARQ-01
 * Capa de persistencia intermedia entre el servicio y el modelo.
 * Aplica filtros de negocio (ej. activo = 1) sin que el servicio conozca el SQL.
 */
class UsuarioRepository {

    private UsuarioModel $model;

    public function __construct(?UsuarioModel $model = null) {
        $this->model = $model ?? new UsuarioModel();
    }

    /**
     * Busca un usuario por nombre de usuario o correo, independientemente de su estado.
     *
     * @param string $identifier Nombre de usuario o correo electrónico.
     * @return array<string,mixed>|null
     */
    public function findByIdentifier(string $identifier): ?array {
        return $this->model->findByIdentifier(trim($identifier));
    }

    /**
     * Retorna el usuario solo si existe y su estado es activo (activo = 1).
     *
     * @param string $identifier Nombre de usuario o correo electrónico.
     * @return array<string,mixed>|null
     */
    public function findActiveByIdentifier(string $identifier): ?array {
        $row = $this->findByIdentifier($identifier);
        if ($row === null || (int)$row['activo'] !== 1) {
            return null;
        }
        return $row;
    }

    /**
     * Retorna el usuario solo si está activo buscando por nombre de usuario.
     * Mantiene retrocompatibilidad delegando en findActiveByIdentifier.
     *
     * @param string $usuario Nombre de usuario.
     * @return array<string,mixed>|null
     */
    public function findActiveByUsername(string $usuario): ?array {
        return $this->findActiveByIdentifier($usuario);
    }

    /**
     * Consulta el estado de activación de una cuenta.
     *
     * @param string $identifier Nombre de usuario o correo.
     * @return bool|null true si está activo, false si está inactivo, null si no existe.
     */
    public function isActive(string $identifier): ?bool {
        $row = $this->findByIdentifier($identifier);
        if ($row === null) {
            return null;
        }
        return (int)$row['activo'] === 1;
    }

    /**
     * Busca un usuario por su identificador primario id_usuario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }
}
