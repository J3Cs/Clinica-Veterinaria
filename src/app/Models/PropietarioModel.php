<?php
namespace App\Models;

use PDO;

/**
 * PropietarioModel — T-016, T-076, T-090, ARQ-01, SEC-01, UNI-01, BAJA-01
 * Modelo de datos para la tabla `propietarios`.
 *
 * Responsabilidad exclusiva: acceso a datos mediante sentencias preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class PropietarioModel extends BaseModel {

    /**
     * Fragmento SELECT con conteo de relaciones asociadas (mascotas y citas).
     */
    private const SELECT_CON_RELACIONES =
        'SELECT p.id_propietario,
                p.dui,
                p.nombre_completo,
                p.direccion,
                p.telefono,
                p.correo,
                p.activo,
                p.fecha_registro,
                (SELECT COUNT(*) FROM mascotas m WHERE m.id_propietario = p.id_propietario) AS mascotas_total,
                (SELECT COUNT(*) FROM mascotas m WHERE m.id_propietario = p.id_propietario AND m.activo = 1) AS mascotas_activas,
                (SELECT COUNT(*) FROM citas c INNER JOIN mascotas m ON m.id_mascota = c.id_mascota WHERE m.id_propietario = p.id_propietario) AS citas_total,
                (SELECT COUNT(*) FROM consultas_veterinarias cv INNER JOIN mascotas m ON m.id_mascota = cv.id_mascota WHERE m.id_propietario = p.id_propietario) AS consultas_total
           FROM propietarios p';

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
     * Actualiza los datos de contacto e identificación de un propietario existente (T-076).
     *
     * @param int $id
     * @param array{dui: string, nombre_completo: string, direccion?: ?string, telefono?: ?string, correo?: ?string} $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE propietarios
                SET dui             = :dui,
                    nombre_completo = :nombre_completo,
                    direccion       = :direccion,
                    telefono        = :telefono,
                    correo          = :correo
              WHERE id_propietario  = :id'
        );

        return $stmt->execute([
            ':dui'             => $data['dui'],
            ':nombre_completo' => $data['nombre_completo'],
            ':direccion'       => $data['direccion'] ?? null,
            ':telefono'        => $data['telefono'] ?? null,
            ':correo'          => $data['correo'] ?? null,
            ':id'              => $id,
        ]);
    }

    /**
     * Busca un propietario por su número de DUI único (UNI-01).
     *
     * @param string $dui Formato ########-#
     * @return array<string,mixed>|null
     */
    public function findByDui(string $dui): ?array {
        $stmt = $this->db->prepare(
            self::SELECT_CON_RELACIONES . ' WHERE p.dui = :dui LIMIT 1'
        );
        $stmt->execute([':dui' => $dui]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
            self::SELECT_CON_RELACIONES . ' WHERE p.id_propietario = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica si un DUI ya existe en la base de datos (UNI-01).
     *
     * @param string $dui
     * @param int|null $excludeId ID a excluir (para validación durante edición)
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
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0) > 0;
    }

    /**
     * Retorna la lista de propietarios registrados (T-090: filtra inactivos por defecto).
     *
     * @param bool $onlyActive Si es true, filtra solo propietarios con activo = 1 (BAJA-01)
     * @return array<array<string,mixed>>
     */
    public function findAll(bool $onlyActive = true): array {
        return $this->search(null, $onlyActive);
    }

    /**
     * Busca propietarios por nombre, DUI, teléfono o correo utilizando parámetros preparados (T-076, T-090).
     * Excluye inactivos por defecto en listados operativos.
     *
     * @param string|null $termino
     * @param bool        $onlyActive
     * @return array<array<string,mixed>>
     */
    public function search(?string $termino = null, bool $onlyActive = true): array {
        $sql = self::SELECT_CON_RELACIONES;
        $condiciones = [];
        $params = [];

        if ($onlyActive) {
            $condiciones[] = 'p.activo = :activo';
            $params[':activo'] = 1;
        }

        $terminoLimpio = trim((string) $termino);
        if ($terminoLimpio !== '') {
            $like = '%' . $terminoLimpio . '%';
            $condiciones[] = '(p.nombre_completo LIKE :t_nombre OR p.dui LIKE :t_dui OR p.telefono LIKE :t_tel OR p.correo LIKE :t_correo)';
            $params[':t_nombre'] = $like;
            $params[':t_dui']    = $like;
            $params[':t_tel']    = $like;
            $params[':t_correo'] = $like;
        }

        if (!empty($condiciones)) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $sql .= ' ORDER BY p.nombre_completo ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Consulta las relaciones clínicas y de mascotas asociadas a un propietario (T-076, T-077).
     *
     * @param int $idPropietario
     * @return array{
     *     mascotas_total: int,
     *     mascotas_activas: int,
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     historial_total: int,
     *     tiene_dependencias: bool,
     *     tiene_datos_clinicos: bool
     * }
     */
    public function contarRelaciones(int $idPropietario): array {
        $stmt = $this->db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM mascotas m WHERE m.id_propietario = :id1) AS mascotas_total,
                (SELECT COUNT(*) FROM mascotas m WHERE m.id_propietario = :id2 AND m.activo = 1) AS mascotas_activas,
                (SELECT COUNT(*) FROM citas c INNER JOIN mascotas m ON m.id_mascota = c.id_mascota WHERE m.id_propietario = :id3) AS citas_total,
                (SELECT COUNT(*) FROM citas c INNER JOIN mascotas m ON m.id_mascota = c.id_mascota WHERE m.id_propietario = :id4 AND c.estado = \'Programada\') AS citas_programadas,
                (SELECT COUNT(*) FROM consultas_veterinarias cv INNER JOIN mascotas m ON m.id_mascota = cv.id_mascota WHERE m.id_propietario = :id5) AS consultas_total,
                (SELECT COUNT(*) FROM historial_mascota hm INNER JOIN mascotas m ON m.id_mascota = hm.id_mascota WHERE m.id_propietario = :id6) AS historial_total'
        );
        $stmt->execute([
            ':id1' => $idPropietario,
            ':id2' => $idPropietario,
            ':id3' => $idPropietario,
            ':id4' => $idPropietario,
            ':id5' => $idPropietario,
            ':id6' => $idPropietario,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $mascotasTotal    = (int) ($row['mascotas_total'] ?? 0);
        $mascotasActivas  = (int) ($row['mascotas_activas'] ?? 0);
        $citasTotal       = (int) ($row['citas_total'] ?? 0);
        $citasProgramadas = (int) ($row['citas_programadas'] ?? 0);
        $consultasTotal   = (int) ($row['consultas_total'] ?? 0);
        $historialTotal   = (int) ($row['historial_total'] ?? 0);

        $tieneDatosClinicos = ($citasTotal > 0 || $consultasTotal > 0 || $historialTotal > 0);
        $tieneDependencias  = ($mascotasTotal > 0 || $tieneDatosClinicos);

        return [
            'mascotas_total'       => $mascotasTotal,
            'mascotas_activas'     => $mascotasActivas,
            'citas_total'          => $citasTotal,
            'citas_programadas'    => $citasProgramadas,
            'consultas_total'      => $consultasTotal,
            'historial_total'      => $historialTotal,
            'tiene_dependencias'   => $tieneDependencias,
            'tiene_datos_clinicos' => $tieneDatosClinicos,
        ];
    }

    /**
     * Desactiva lógicamente a un propietario (BAJA-01, T-077, T-090).
     */
    public function desactivar(int $id): bool {
        $stmt = $this->db->prepare('UPDATE propietarios SET activo = 0 WHERE id_propietario = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Reactiva a un propietario previamente desactivado.
     */
    public function activar(int $id): bool {
        $stmt = $this->db->prepare('UPDATE propietarios SET activo = 1 WHERE id_propietario = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Elimina físicamente a un propietario (solo cuando no existen dependencias — T-077).
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM propietarios WHERE id_propietario = :id');
        return $stmt->execute([':id' => $id]);
    }
}
