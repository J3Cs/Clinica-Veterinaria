<?php
namespace App\Repositories;

use App\Models\UsuarioModel;

/**
 * UsuarioRepository — T-010, T-077, ARQ-01, BAJA-01
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
     * Los veterinarios o usuarios desactivados no pueden iniciar sesión (T-077).
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

    /**
     * Crea un nuevo usuario.
     *
     * @param array{nombre: string, correo: string, usuario: string, contrasena: string, rol: string, activo?: int} $data
     * @return int
     */
    public function create(array $data): int {
        return $this->model->insert($data);
    }

    /**
     * Actualiza un usuario existente.
     */
    public function update(int $id, array $data): bool {
        return $this->model->update($id, $data);
    }

    /**
     * Verifica duplicidad de correo.
     */
    public function isCorreoRegistered(string $correo, ?int $excludeId = null): bool {
        return $this->model->existsCorreo(trim($correo), $excludeId);
    }

    /**
     * Verifica duplicidad de nombre de usuario.
     */
    public function isUsuarioRegistered(string $usuario, ?int $excludeId = null): bool {
        return $this->model->existsUsuario(trim($usuario), $excludeId);
    }

    /**
     * Desactiva lógicamente una cuenta de usuario (BAJA-01, T-077).
     */
    public function desactivar(int $id): bool {
        return $this->model->setActivo($id, 0);
    }

    /**
     * Reactiva una cuenta de usuario.
     */
    public function activar(int $id): bool {
        return $this->model->setActivo($id, 1);
    }

    /**
     * Elimina físicamente una cuenta de usuario.
     */
    public function delete(int $id): bool {
        return $this->model->delete($id);
    }
}
