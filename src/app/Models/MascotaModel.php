<?php
namespace App\Models;

use PDO;

/**
 * MascotaModel — US-03, ARQ-01, SEC-01, DAT-01, BAJA-01
 * Modelo de datos para la tabla `mascotas`.
 *
 * Responsabilidad exclusiva: acceso a datos mediante sentencias preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class MascotaModel extends BaseModel {

    /**
     * Inserta una nueva mascota asociada a un propietario existente.
     *
     * @param array{
     *     id_propietario: int,
     *     nombre: string,
     *     especie: string,
     *     raza: string,
     *     sexo: string,
     *     fecha_nacimiento?: ?string,
     *     caracteristicas_relevantes?: ?string,
     *     activo?: int
     * } $data
     * @return int ID generado por MySQL.
     */
    public function insert(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO mascotas (
                id_propietario,
                nombre,
                especie,
                raza,
                sexo,
                fecha_nacimiento,
                caracteristicas_relevantes,
                activo
            ) VALUES (
                :id_propietario,
                :nombre,
                :especie,
                :raza,
                :sexo,
                :fecha_nacimiento,
                :caracteristicas_relevantes,
                :activo
            )'
        );

        $stmt->execute([
            ':id_propietario'            => (int) $data['id_propietario'],
            ':nombre'                    => $data['nombre'],
            ':especie'                   => $data['especie'],
            ':raza'                      => $data['raza'],
            ':sexo'                      => $data['sexo'],
            ':fecha_nacimiento'          => !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null,
            ':caracteristicas_relevantes'=> !empty($data['caracteristicas_relevantes']) ? $data['caracteristicas_relevantes'] : null,
            ':activo'                    => $data['activo'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca una mascota por su identificador primario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT m.id_mascota,
                    m.id_propietario,
                    m.nombre,
                    m.especie,
                    m.raza,
                    m.sexo,
                    m.fecha_nacimiento,
                    m.caracteristicas_relevantes,
                    m.activo,
                    m.fecha_registro,
                    p.nombre_completo AS propietario_nombre,
                    p.dui             AS propietario_dui,
                    p.telefono        AS propietario_telefono
               FROM mascotas m
         INNER JOIN propietarios p ON m.id_propietario = p.id_propietario
              WHERE m.id_mascota = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Retorna todas las mascotas registradas con los datos de su propietario.
     *
     * @param bool $onlyActive Si es true, solo retorna mascotas activas (BAJA-01).
     * @return array<array<string,mixed>>
     */
    public function findAll(bool $onlyActive = true): array {
        $sql = 'SELECT m.id_mascota,
                       m.id_propietario,
                       m.nombre,
                       m.especie,
                       m.raza,
                       m.sexo,
                       m.fecha_nacimiento,
                       m.caracteristicas_relevantes,
                       m.activo,
                       m.fecha_registro,
                       p.nombre_completo AS propietario_nombre,
                       p.dui             AS propietario_dui
                  FROM mascotas m
            INNER JOIN propietarios p ON m.id_propietario = p.id_propietario';

        if ($onlyActive) {
            $sql .= ' WHERE m.activo = 1 AND p.activo = 1';
        }

        $sql .= ' ORDER BY m.id_mascota DESC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todas las mascotas pertenecientes a un propietario específico.
     *
     * @param int $idPropietario
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function findByPropietarioId(int $idPropietario, bool $onlyActive = true): array {
        $sql = 'SELECT id_mascota,
                       id_propietario,
                       nombre,
                       especie,
                       raza,
                       sexo,
                       fecha_nacimiento,
                       caracteristicas_relevantes,
                       activo,
                       fecha_registro
                  FROM mascotas
                 WHERE id_propietario = :id_propietario';

        if ($onlyActive) {
            $sql .= ' AND activo = 1';
        }

        $sql .= ' ORDER BY nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_propietario' => $idPropietario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de mascotas activas.
     *
     * @return int
     */
    public function countActive(): int {
        $stmt = $this->db->query('SELECT COUNT(*) FROM mascotas WHERE activo = 1');
        return (int) $stmt->fetchColumn();
    }
}
