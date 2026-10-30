<?php
namespace App\Models;

use PDO;

/**
 * VeterinarioModel — US-04, US-09 (T-059), US-12 (T-077), ARQ-01, SEC-01, BAJA-01
 * Modelo de datos para `veterinarios`. Un veterinario está activo cuando
 * su cuenta de `usuarios` está activa (la tabla `usuarios` tiene `activo`).
 * Refleja `id_usuario` como referencia uno a uno de la cuenta y respeta
 * los campos del diccionario (`especialidad`, `telefono`, `licencia_medica`).
 */
class VeterinarioModel extends BaseModel {

    private const SELECT_ACTIVO =
        'SELECT v.id_veterinario, u.nombre, v.especialidad, v.telefono
           FROM veterinarios v
     INNER JOIN usuarios u ON u.id_usuario = v.id_usuario';

    private const SELECT_DETALLE =
        'SELECT v.id_veterinario,
                v.id_usuario,
                u.nombre,
                u.correo,
                u.usuario,
                u.activo,
                v.especialidad,
                v.telefono,
                v.licencia_medica,
                (SELECT COUNT(*) FROM citas c WHERE c.id_veterinario = v.id_veterinario) AS citas_total,
                (SELECT COUNT(*) FROM citas c WHERE c.id_veterinario = v.id_veterinario AND c.estado = \'Programada\') AS citas_programadas,
                (SELECT COUNT(*) FROM consultas_veterinarias cv WHERE cv.id_veterinario = v.id_veterinario) AS consultas_total,
                (SELECT COUNT(*) FROM horarios_atencion h WHERE h.id_veterinario = v.id_veterinario) AS horarios_total
           FROM veterinarios v
     INNER JOIN usuarios u ON u.id_usuario = v.id_usuario';

    /** @return array<array<string,mixed>> */
    public function findAllActivos(): array {
        $stmt = $this->db->query(self::SELECT_ACTIVO . ' WHERE u.activo = 1 ORDER BY u.nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function findActivoById(int $id): ?array {
        $stmt = $this->db->prepare(self::SELECT_ACTIVO . ' WHERE v.id_veterinario = :id AND u.activo = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Perfil de veterinario (activo) de un usuario autenticado.
     *
     * @return array<string,mixed>|null null si el usuario no tiene perfil o está inactivo.
     */
    public function findActivoByUsuarioId(int $idUsuario): ?array {
        $stmt = $this->db->prepare(self::SELECT_ACTIVO . ' WHERE u.id_usuario = :id AND u.activo = 1 LIMIT 1');
        $stmt->execute([':id' => $idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Bloquea la fila del veterinario (SELECT ... FOR UPDATE) para serializar
     * las reservas concurrentes sobre él. Debe llamarse dentro de una transacción.
     *
     * @return bool true si el veterinario existe y está activo.
     */
    public function bloquearActivoPorId(int $id): bool {
        $stmt = $this->db->prepare(
            'SELECT v.id_veterinario
               FROM veterinarios v
         INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
              WHERE v.id_veterinario = :id AND u.activo = 1
              FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Obtiene un veterinario por su ID (activo o inactivo) con sus datos de usuario y conteo de relaciones.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(self::SELECT_DETALLE . ' WHERE v.id_veterinario = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene la ficha de veterinario (activa o inactiva) por el ID de la cuenta de usuario (relación 1:1 — T-059).
     *
     * @return array<string,mixed>|null
     */
    public function findByUsuarioId(int $idUsuario): ?array {
        $stmt = $this->db->prepare(self::SELECT_DETALLE . ' WHERE v.id_usuario = :id_usuario LIMIT 1');
        $stmt->execute([':id_usuario' => $idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lista todos los veterinarios con datos de usuario y relaciones.
     *
     * @param bool $onlyActive
     * @return array<array<string,mixed>>
     */
    public function findAll(bool $onlyActive = false): array {
        $sql = self::SELECT_DETALLE;
        if ($onlyActive) {
            $sql .= ' WHERE u.activo = 1';
        }
        $sql .= ' ORDER BY u.nombre ASC';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca veterinarios por nombre, usuario, correo, especialidad, teléfono o licencia médica (T-059, T-060).
     *
     * @param string|null $termino
     * @param bool        $onlyActive
     * @return array<array<string,mixed>>
     */
    public function search(?string $termino = null, bool $onlyActive = false): array {
        $termino = trim((string) $termino);
        if ($termino === '') {
            return $this->findAll($onlyActive);
        }

        $sql = self::SELECT_DETALLE . ' WHERE (
            u.nombre LIKE :q1
            OR u.correo LIKE :q2
            OR u.usuario LIKE :q3
            OR COALESCE(v.especialidad, \'\') LIKE :q4
            OR COALESCE(v.telefono, \'\') LIKE :q5
            OR COALESCE(v.licencia_medica, \'\') LIKE :q6
        )';
        if ($onlyActive) {
            $sql .= ' AND u.activo = 1';
        }
        $sql .= ' ORDER BY u.nombre ASC';

        $like = '%' . $termino . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':q1' => $like,
            ':q2' => $like,
            ':q3' => $like,
            ':q4' => $like,
            ':q5' => $like,
            ':q6' => $like,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Verifica si una licencia médica ya está registrada por otro veterinario (T-059, T-061).
     */
    public function existsLicenciaMedica(string $licencia, ?int $excludeIdVeterinario = null): bool {
        $licencia = trim($licencia);
        if ($licencia === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) AS total FROM veterinarios WHERE LOWER(licencia_medica) = LOWER(:licencia)';
        $params = [':licencia' => $licencia];
        if ($excludeIdVeterinario !== null) {
            $sql .= ' AND id_veterinario != :excludeId';
            $params[':excludeId'] = $excludeIdVeterinario;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * Inserta el perfil de un veterinario vinculado a un usuario existente.
     *
     * @param array{id_usuario: int, especialidad?: ?string, telefono?: ?string, licencia_medica?: ?string} $data
     * @return int ID generado.
     */
    public function insert(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO veterinarios (id_usuario, especialidad, telefono, licencia_medica)
             VALUES (:id_usuario, :especialidad, :telefono, :licencia_medica)'
        );
        $stmt->execute([
            ':id_usuario'      => (int) $data['id_usuario'],
            ':especialidad'    => $data['especialidad'] ?? null,
            ':telefono'        => $data['telefono'] ?? null,
            ':licencia_medica' => $data['licencia_medica'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza los datos profesionales del veterinario (T-077, T-078).
     *
     * @param int $idVeterinario
     * @param array{especialidad?: ?string, telefono?: ?string, licencia_medica?: ?string} $data
     * @return bool
     */
    public function update(int $idVeterinario, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE veterinarios
                SET especialidad    = :especialidad,
                    telefono        = :telefono,
                    licencia_medica = :licencia_medica
              WHERE id_veterinario  = :id'
        );
        return $stmt->execute([
            ':especialidad'    => $data['especialidad'] ?? null,
            ':telefono'        => $data['telefono'] ?? null,
            ':licencia_medica' => $data['licencia_medica'] ?? null,
            ':id'              => $idVeterinario,
        ]);
    }

    /**
     * Cuenta las relaciones clínicas y de citas de un veterinario (T-077).
     *
     * @param int $idVeterinario
     * @return array{
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     horarios_total: int,
     *     tiene_dependencias: bool
     * }
     */
    public function contarRelaciones(int $idVeterinario): array {
        $stmt = $this->db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM citas c WHERE c.id_veterinario = :id1) AS citas_total,
                (SELECT COUNT(*) FROM citas c WHERE c.id_veterinario = :id2 AND c.estado = \'Programada\') AS citas_programadas,
                (SELECT COUNT(*) FROM consultas_veterinarias cv WHERE cv.id_veterinario = :id3) AS consultas_total,
                (SELECT COUNT(*) FROM horarios_atencion h WHERE h.id_veterinario = :id4) AS horarios_total'
        );
        $stmt->execute([
            ':id1' => $idVeterinario,
            ':id2' => $idVeterinario,
            ':id3' => $idVeterinario,
            ':id4' => $idVeterinario,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $citasTotal       = (int) ($row['citas_total'] ?? 0);
        $citasProgramadas = (int) ($row['citas_programadas'] ?? 0);
        $consultasTotal   = (int) ($row['consultas_total'] ?? 0);
        $horariosTotal    = (int) ($row['horarios_total'] ?? 0);

        return [
            'citas_total'        => $citasTotal,
            'citas_programadas'  => $citasProgramadas,
            'consultas_total'    => $consultasTotal,
            'horarios_total'     => $horariosTotal,
            'tiene_dependencias' => ($citasTotal > 0 || $consultasTotal > 0),
        ];
    }

    /**
     * Cancela automáticamente todas las citas en estado 'Programada' asociadas a un veterinario
     * cuando su cuenta es desactivada (T-077, BAJA-01).
     *
     * @param int    $idVeterinario
     * @param string $motivo
     * @return int Cantidad de citas programadas canceladas automáticamente.
     */
    public function cancelarCitasProgramadas(
        int $idVeterinario,
        string $motivo = 'Cancelada automáticamente por desactivación del veterinario'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE citas
                SET estado = 'Cancelada',
                    motivo_cancelacion = COALESCE(motivo_cancelacion, :motivo)
              WHERE id_veterinario = :id_veterinario
                AND estado = 'Programada'"
        );
        $stmt->execute([
            ':id_veterinario' => $idVeterinario,
            ':motivo'         => $motivo,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Elimina físicamente la fila de `veterinarios` (solo cuando no tiene citas ni consultas — T-077).
     */
    public function delete(int $idVeterinario): bool {
        $stmt = $this->db->prepare('DELETE FROM veterinarios WHERE id_veterinario = :id');
        return $stmt->execute([':id' => $idVeterinario]);
    }

    /**
     * Ejecuta una operación compuesta (ej. alta de usuario + ficha de veterinario)
     * dentro de una transacción PDO atómica (T-060).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaccion(callable $fn): mixed {
        if (!isset($this->db)) {
            return $fn();
        }
        $iniciadaAqui = !$this->db->inTransaction();
        if ($iniciadaAqui) {
            $this->db->beginTransaction();
        }
        try {
            $resultado = $fn();
            if ($iniciadaAqui && $this->db->inTransaction()) {
                $this->db->commit();
            }
            return $resultado;
        } catch (\Throwable $e) {
            if ($iniciadaAqui && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
