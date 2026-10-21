<?php
namespace App\Models;

use PDO;

/**
 * PropietarioModel — T-016, ARQ-01, SEC-01, UNI-01
 * Modelo de datos para la tabla `propietarios`.
 *
 * Responsabilidad exclusiva: acceso a datos mediante sentencias preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class PropietarioModel extends BaseModel {

    /**
     * Inserta un nuevo propietario en la base de datos.
     *
     * @param array{dui: string, nombre_completo: string, direccion?: ?string, telefono?: ?string, correo?: ?string, activo?: int} $data
     * @return int ID generado por MySQL.
     */
    public function insert(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO propietarios (dui, nombre_completo, direccion, telefono, correo, activo)
             VALUES (:dui, :nombre_completo, :direccion, :telefono, :correo, :activo)'
        );

        $stmt->execute([
            ':dui'             => $data['dui'],
            ':nombre_completo' => $data['nombre_completo'],
            ':direccion'       => $data['direccion'] ?? null,
            ':telefono'        => $data['telefono'] ?? null,
            ':correo'          => $data['correo'] ?? null,
            ':activo'          => $data['activo'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca un propietario por su número de DUI único (UNI-01).
     *
     * @param string $dui Formato ########-#
     * @return array<string,mixed>|null
     */
    public function findByDui(string $dui): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_propietario, dui, nombre_completo, direccion, telefono, correo, activo, fecha_registro
               FROM propietarios
              WHERE dui = :dui
              LIMIT 1'
        );
        $stmt->execute([':dui' => $dui]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Busca un propietario por su ID interno primario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_propietario, dui, nombre_completo, direccion, telefono, correo, activo, fecha_registro
               FROM propietarios
              WHERE id_propietario = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Verifica si un DUI ya existe en la base de datos (UNI-01).
     *
     * @param string $dui
     * @param int|null $excludeId ID a excluir (útil para edición futura)
     * @return bool
     */
    public function existsDui(string $dui, ?int $excludeId = null): bool {
        $sql = 'SELECT COUNT(*) AS total FROM propietarios WHERE dui = :dui';
        $params = [':dui' => $dui];

        if ($excludeId !== null) {
            $sql .= ' AND id_propietario != :excludeId';
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0) > 0;
    }

    /**
     * Retorna la lista de propietarios registrados.
     *
     * @param bool $onlyActive Si es true, filtra solo propietarios con activo = 1 (BAJA-01)
     * @return array<array<string,mixed>>
     */
    public function findAll(bool $onlyActive = true): array {
        $sql = 'SELECT id_propietario, dui, nombre_completo, direccion, telefono, correo, activo, fecha_registro
                  FROM propietarios';
        if ($onlyActive) {
            $sql .= ' WHERE activo = 1';
        }
        $sql .= ' ORDER BY nombre_completo ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll() ?: [];
    }
}

