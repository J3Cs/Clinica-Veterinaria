<?php
namespace App\Models;

use PDO;

/**
 * UsuarioModel — T-009, T-077, ARQ-01, SEC-01, BAJA-01
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Inserta un nuevo usuario en la tabla `usuarios`.
     *
     * @param array{nombre: string, correo: string, usuario: string, contrasena: string, rol: string, activo?: int} $data
     * @return int ID generado.
     */
    public function insert(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, correo, usuario, contrasena, rol, activo)
             VALUES (:nombre, :correo, :usuario, :contrasena, :rol, :activo)'
        );
        $stmt->execute([
            ':nombre'     => $data['nombre'],
            ':correo'     => $data['correo'],
            ':usuario'    => $data['usuario'],
            ':contrasena' => $data['contrasena'],
            ':rol'        => $data['rol'],
            ':activo'     => $data['activo'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza datos básicos de un usuario.
     *
     * @param int $id
     * @param array{nombre: string, correo: string, usuario: string, contrasena?: ?string} $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        if (!empty($data['contrasena'])) {
            $stmt = $this->db->prepare(
                'UPDATE usuarios
                    SET nombre     = :nombre,
                        correo     = :correo,
                        usuario    = :usuario,
                        contrasena = :contrasena
                  WHERE id_usuario = :id'
            );
            return $stmt->execute([
                ':nombre'     => $data['nombre'],
                ':correo'     => $data['correo'],
                ':usuario'    => $data['usuario'],
                ':contrasena' => $data['contrasena'],
                ':id'         => $id,
            ]);
        }

        $stmt = $this->db->prepare(
            'UPDATE usuarios
                SET nombre  = :nombre,
                    correo  = :correo,
                    usuario = :usuario
              WHERE id_usuario = :id'
        );
        return $stmt->execute([
            ':nombre'  => $data['nombre'],
            ':correo'  => $data['correo'],
            ':usuario' => $data['usuario'],
            ':id'      => $id,
        ]);
    }

    /**
     * Verifica si un correo ya está registrado por otro usuario.
     */
    public function existsCorreo(string $correo, ?int $excludeId = null): bool {
        $sql = 'SELECT COUNT(*) AS total FROM usuarios WHERE correo = :correo';
        $params = [':correo' => $correo];
        if ($excludeId !== null) {
            $sql .= ' AND id_usuario != :excludeId';
            $params[':excludeId'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0) > 0;
    }

    /**
     * Verifica si un nombre de usuario ya está registrado por otro usuario.
     */
    public function existsUsuario(string $usuario, ?int $excludeId = null): bool {
        $sql = 'SELECT COUNT(*) AS total FROM usuarios WHERE usuario = :usuario';
        $params = [':usuario' => $usuario];
        if ($excludeId !== null) {
            $sql .= ' AND id_usuario != :excludeId';
            $params[':excludeId'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0) > 0;
    }

    /**
     * Cambia el estado activo de un usuario (1 = activo, 0 = inactivo / baja lógica — BAJA-01, T-077).
     */
    public function setActivo(int $id, int $activo): bool {
        $stmt = $this->db->prepare('UPDATE usuarios SET activo = :activo WHERE id_usuario = :id');
        return $stmt->execute([
            ':activo' => $activo ? 1 : 0,
            ':id'     => $id,
        ]);
    }

    /**
     * Elimina físicamente un usuario (solo cuando no tiene dependencias — T-077).
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id_usuario = :id');
        return $stmt->execute([':id' => $id]);
    }
}
