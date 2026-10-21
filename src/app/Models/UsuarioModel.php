<?php
namespace App\Models;

/**
 * UsuarioModel — ARQ-01, SEC-01
 * Solo acceso a datos. Sin lógica de negocio. Sin HTML.
 */
class UsuarioModel extends BaseModel {

    /**
     * Busca un usuario por su nombre de usuario.
     * Usa prepared statement — SEC-01.
     *
     * @return array<string,mixed>|null
     */
    public function findByUsername(string $usuario): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_usuario, nombre, correo, usuario, contrasena, rol, activo
               FROM usuarios
              WHERE usuario = :usuario
              LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
