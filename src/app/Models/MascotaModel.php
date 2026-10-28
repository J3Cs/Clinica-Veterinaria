<?php
namespace App\Models;

use PDO;

/**
 * MascotaModel — US-03, US-12 (T-076, T-090), ARQ-01, SEC-01, DAT-01, BAJA-01
 * Modelo de datos para la tabla `mascotas`.
 *
 * Responsabilidad exclusiva: acceso a datos mediante sentencias preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class MascotaModel extends BaseModel {

    /**
     * Fragmento SELECT con datos del propietario y conteo de relaciones clínicas (T-076).
     */
    private const SELECT_CON_RELACIONES =
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
                p.telefono        AS propietario_telefono,
                p.correo          AS propietario_correo,
                p.direccion       AS propietario_direccion,
                p.activo          AS propietario_activo,
                (SELECT COUNT(*) FROM citas c WHERE c.id_mascota = m.id_mascota) AS citas_total,
                (SELECT COUNT(*) FROM citas c WHERE c.id_mascota = m.id_mascota AND c.estado = \'Programada\') AS citas_programadas,
                (SELECT COUNT(*) FROM consultas_veterinarias cv WHERE cv.id_mascota = m.id_mascota) AS consultas_total,
                (SELECT COUNT(*) FROM historial_mascota hm WHERE hm.id_mascota = m.id_mascota) AS historial_total
           FROM mascotas m
     INNER JOIN propietarios p ON m.id_propietario = p.id_propietario';

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
            ':id_propietario'             => (int) $data['id_propietario'],
            ':nombre'                     => $data['nombre'],
            ':especie'                    => $data['especie'],
            ':raza'                       => $data['raza'],
            ':sexo'                       => $data['sexo'],
            ':fecha_nacimiento'           => !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null,
            ':caracteristicas_relevantes' => !empty($data['caracteristicas_relevantes']) ? $data['caracteristicas_relevantes'] : null,
            ':activo'                     => $data['activo'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza los datos de una mascota existente mediante sentencia preparada (T-076).
     *
     * @param int $id
     * @param array{
     *     id_propietario: int,
     *     nombre: string,
     *     especie: string,
     *     raza: string,
     *     sexo: string,
     *     fecha_nacimiento?: ?string,
     *     caracteristicas_relevantes?: ?string
     * } $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE mascotas
                SET id_propietario             = :id_propietario,
                    nombre                     = :nombre,
                    especie                    = :especie,
                    raza                       = :raza,
                    sexo                       = :sexo,
                    fecha_nacimiento           = :fecha_nacimiento,
                    caracteristicas_relevantes = :caracteristicas_relevantes
              WHERE id_mascota                 = :id'
        );

        return $stmt->execute([
            ':id_propietario'             => (int) $data['id_propietario'],
            ':nombre'                     => $data['nombre'],
            ':especie'                    => $data['especie'],
            ':raza'                       => $data['raza'],
            ':sexo'                       => $data['sexo'],
            ':fecha_nacimiento'           => !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null,
            ':caracteristicas_relevantes' => !empty($data['caracteristicas_relevantes']) ? $data['caracteristicas_relevantes'] : null,
            ':id'                         => $id,
        ]);
    }

    /**
     * Busca una mascota por su identificador primario junto con su propietario y conteo clínico (T-076).
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            self::SELECT_CON_RELACIONES . ' WHERE m.id_mascota = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retorna todas las mascotas registradas con los datos de su propietario (T-090).
     *
     * @param bool $onlyActive Si es true, solo retorna mascotas activas de propietarios activos (BAJA-01).
     * @return array<array<string,mixed>>
     */
    public function findAll(bool $onlyActive = true): array {
        return $this->search(null, $onlyActive, null);
    }

    /**
     * Busca mascotas por nombre, especie, raza, nombre de propietario o DUI usando parámetros preparados (T-076, T-090).
     *
     * @param string|null $termino
     * @param bool        $onlyActive
     * @param int|null    $idPropietario
     * @return array<array<string,mixed>>
     */
    public function search(?string $termino = null, bool $onlyActive = true, ?int $idPropietario = null): array {
        $sql = self::SELECT_CON_RELACIONES;
        $condiciones = [];
        $params = [];

        if ($onlyActive) {
            $condiciones[] = 'm.activo = :m_activo AND p.activo = :p_activo';
            $params[':m_activo'] = 1;
            $params[':p_activo'] = 1;
        }

        if ($idPropietario !== null && $idPropietario > 0) {
            $condiciones[] = 'm.id_propietario = :id_propietario';
            $params[':id_propietario'] = $idPropietario;
        }

        $terminoLimpio = trim((string) $termino);
        if ($terminoLimpio !== '') {
            $like = '%' . $terminoLimpio . '%';
            $condiciones[] = '(m.nombre LIKE :t_nombre OR m.especie LIKE :t_especie OR m.raza LIKE :t_raza OR p.nombre_completo LIKE :t_prop OR p.dui LIKE :t_dui)';
            $params[':t_nombre']  = $like;
            $params[':t_especie'] = $like;
            $params[':t_raza']    = $like;
            $params[':t_prop']    = $like;
            $params[':t_dui']     = $like;
        }

        if (!empty($condiciones)) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $sql .= ' ORDER BY m.id_mascota DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lista todas las mascotas pertenecientes a un propietario específico.
     *
     * @param int $idPropietario
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function findByPropietarioId(int $idPropietario, bool $onlyActive = true): array {
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
                       (SELECT COUNT(*) FROM citas c WHERE c.id_mascota = m.id_mascota) AS citas_total,
                       (SELECT COUNT(*) FROM consultas_veterinarias cv WHERE cv.id_mascota = m.id_mascota) AS consultas_total,
                       (SELECT COUNT(*) FROM historial_mascota hm WHERE hm.id_mascota = m.id_mascota) AS historial_total
                  FROM mascotas m
                 WHERE m.id_propietario = :id_propietario';

        if ($onlyActive) {
            $sql .= ' AND m.activo = 1';
        }

        $sql .= ' ORDER BY m.nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_propietario' => $idPropietario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene las relaciones clínicas detalladas de una mascota: citas, consultas veterinarias y antecedentes (T-076, T-077).
     *
     * @param int $idMascota
     * @return array{
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     historial_total: int,
     *     tiene_dependencias: bool,
     *     citas: array<array<string,mixed>>,
     *     consultas: array<array<string,mixed>>,
     *     antecedentes: array<array<string,mixed>>
     * }
     */
    public function obtenerRelacionesClinicas(int $idMascota): array {
        // 1. Citas asociadas
        $stmtCitas = $this->db->prepare(
            'SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado, c.motivo_cancelacion,
                    s.nombre_servicio, u.nombre AS veterinario_nombre
               FROM citas c
         INNER JOIN servicios s    ON s.id_servicio = c.id_servicio
         INNER JOIN veterinarios v ON v.id_veterinario = c.id_veterinario
         INNER JOIN usuarios u     ON u.id_usuario = v.id_usuario
              WHERE c.id_mascota = :id_mascota
           ORDER BY c.fecha_cita DESC, c.hora_cita DESC'
        );
        $stmtCitas->execute([':id_mascota' => $idMascota]);
        $citas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $citasProgramadas = 0;
        foreach ($citas as $c) {
            if (($c['estado'] ?? '') === 'Programada') {
                $citasProgramadas++;
            }
        }

        // 2. Consultas veterinarias asociadas
        $stmtConsultas = $this->db->prepare(
            'SELECT cv.id_consulta, cv.id_cita, cv.fecha_atencion,
                    cv.diagnostico, cv.observaciones, cv.tratamiento, cv.servicios_adicionales,
                    u.nombre AS veterinario_nombre
               FROM consultas_veterinarias cv
         INNER JOIN veterinarios v ON v.id_veterinario = cv.id_veterinario
         INNER JOIN usuarios u     ON u.id_usuario = v.id_usuario
              WHERE cv.id_mascota = :id_mascota
           ORDER BY cv.fecha_atencion DESC, cv.id_consulta DESC'
        );
        $stmtConsultas->execute([':id_mascota' => $idMascota]);
        $consultas = $stmtConsultas->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Historial / antecedentes previos
        $stmtHistorial = $this->db->prepare(
            'SELECT id_historial_mascota, condicion_medica, descripcion_problema,
                    alergias, fecha_registro_antecedente
               FROM historial_mascota
              WHERE id_mascota = :id_mascota
           ORDER BY fecha_registro_antecedente DESC, id_historial_mascota DESC'
        );
        $stmtHistorial->execute([':id_mascota' => $idMascota]);
        $antecedentes = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $citasTotal     = count($citas);
        $consultasTotal = count($consultas);
        $historialTotal = count($antecedentes);

        return [
            'citas_total'        => $citasTotal,
            'citas_programadas'  => $citasProgramadas,
            'consultas_total'    => $consultasTotal,
            'historial_total'    => $historialTotal,
            'tiene_dependencias' => ($citasTotal > 0 || $consultasTotal > 0 || $historialTotal > 0),
            'citas'              => $citas,
            'consultas'          => $consultas,
            'antecedentes'       => $antecedentes,
        ];
    }

    /**
     * Cancela automáticamente todas las citas en estado 'Programada' asociadas a una mascota
     * cuando ésta es desactivada (T-077, BAJA-01).
     *
     * @param int    $idMascota
     * @param string $motivo
     * @return int Cantidad de citas programadas canceladas automáticamente.
     */
    public function cancelarCitasProgramadas(
        int $idMascota,
        string $motivo = 'Cancelada automáticamente por desactivación de la mascota'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE citas
                SET estado = 'Cancelada',
                    motivo_cancelacion = COALESCE(motivo_cancelacion, :motivo)
              WHERE id_mascota = :id_mascota
                AND estado = 'Programada'"
        );
        $stmt->execute([
            ':id_mascota' => $idMascota,
            ':motivo'     => $motivo,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Cancela automáticamente todas las citas en estado 'Programada' de las mascotas
     * pertenecientes a un propietario que es desactivado (T-077, BAJA-01).
     *
     * @param int    $idPropietario
     * @param string $motivo
     * @return int Cantidad de citas programadas canceladas automáticamente.
     */
    public function cancelarCitasProgramadasPorPropietario(
        int $idPropietario,
        string $motivo = 'Cancelada automáticamente por desactivación del propietario'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE citas c
         INNER JOIN mascotas m ON m.id_mascota = c.id_mascota
                SET c.estado = 'Cancelada',
                    c.motivo_cancelacion = COALESCE(c.motivo_cancelacion, :motivo)
              WHERE m.id_propietario = :id_propietario
                AND c.estado = 'Programada'"
        );
        $stmt->execute([
            ':id_propietario' => $idPropietario,
            ':motivo'         => $motivo,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Desactiva lógicamente una mascota y cancela automáticamente sus citas programadas (BAJA-01, T-077, T-090).
     */
    public function desactivar(int $id): bool {
        $this->cancelarCitasProgramadas($id);
        $stmt = $this->db->prepare('UPDATE mascotas SET activo = 0 WHERE id_mascota = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Reactiva una mascota previamente desactivada.
     */
    public function activar(int $id): bool {
        $stmt = $this->db->prepare('UPDATE mascotas SET activo = 1 WHERE id_mascota = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Desactiva todas las mascotas pertenecientes a un propietario que pasa a baja lógica
     * y cancela automáticamente sus citas en estado 'Programada' (T-077).
     *
     * @return int Cantidad de mascotas desactivadas.
     */
    public function desactivarPorPropietario(int $idPropietario): int {
        $this->cancelarCitasProgramadasPorPropietario($idPropietario);
        $stmt = $this->db->prepare(
            'UPDATE mascotas SET activo = 0 WHERE id_propietario = :id_propietario AND activo = 1'
        );
        $stmt->execute([':id_propietario' => $idPropietario]);
        return $stmt->rowCount();
    }

    /**
     * Elimina físicamente una mascota (solo cuando no tiene dependencias clínicas — T-077).
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM mascotas WHERE id_mascota = :id');
        return $stmt->execute([':id' => $id]);
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
