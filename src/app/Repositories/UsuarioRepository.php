<?php
namespace App\Repositories;

use App\Models\UsuarioModel;

/**
 * UsuarioRepository — ARQ-01
 * Intermediario entre el servicio y el modelo.
 * Aplica filtros de negocio (ej. activo = 1) sin que el servicio conozca el SQL.
 */
class UsuarioRepository {

    private UsuarioModel $model;

    public function __construct() {
        $this->model = new UsuarioModel();
    }

    /**
     * Retorna el usuario solo si está activo.
     *
     * @return array<string,mixed>|null
     */
    public function findActiveByUsername(string $usuario): ?array {
        $row = $this->model->findByUsername($usuario);
        if ($row === null || (int)$row['activo'] !== 1) {
            return null;
        }
        return $row;
    }
}
