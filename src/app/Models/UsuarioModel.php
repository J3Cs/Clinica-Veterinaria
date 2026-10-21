<?php
namespace App\Models;

/**
 * UsuarioModel — T-009, ARQ-01, SEC-01
 * Modelo de datos para la tabla `usuarios`.
 *
 * Responsabilidad exclusiva: acceso a datos mediante consultas preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class UsuarioModel extends BaseModel {

    /**
     * Busca un usuario por su identificador único (nombre de usuario o correo electrónico).
     * Usa consultas preparadas para mitigar inyecciones SQL (SEC-01).
     *
     * @param string $identifier Nombre de usuario o correo electrónico.
     * @return array<string,mixed>|null Registro del usuario o null si no existe.
     */
    public function findByIdentifier(string $identifier): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_usuario, nombre, correo, usuario, contrasena, rol, activo, fecha_creacion
               FROM usuarios
              WHERE usuario = :ident_u OR correo = :ident_c
              LIMIT 1'
        );
        $stmt->execute([
            ':ident_u' => $identifier,
            ':ident_c' => $identifier,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Busca un usuario por su nombre de usuario.
     * Mantiene retrocompatibilidad delegando en findByIdentifier (SEC-01).
     *
     * @param string $usuario Nombre de usuario.
     * @return array<string,mixed>|null
     */
    public function findByUsername(string $usuario): ?array {
        return $this->findByIdentifier($usuario);
    }

    /**
     * Busca un usuario por su clave primaria id_usuario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_usuario, nombre, correo, usuario, contrasena, rol, activo, fecha_creacion
               FROM usuarios
              WHERE id_usuario = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
