<?php
namespace App\Models;

use PDO;

/**
 * HorarioAtencionModel — US-04, US-11 (T-070), ARQ-01, SEC-01
 * Modelo de datos para la tabla `horarios_atencion`.
 * `dia_semana`: 1 = lunes … 7 = domingo (mismo criterio que `date('N')`).
 *
 * Responsabilidad exclusiva: acceso a datos mediante sentencias preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class HorarioAtencionModel extends BaseModel {

    private const SELECT_DETALLADO =
        'SELECT h.id_horario, h.id_veterinario, h.dia_semana, h.hora_inicio, h.hora_fin,
                u.nombre AS veterinario_nombre, v.especialidad, u.activo AS usuario_activo
           FROM horarios_atencion h
     INNER JOIN veterinarios v ON v.id_veterinario = h.id_veterinario
     INNER JOIN usuarios u     ON u.id_usuario = v.id_usuario';

    /**
     * Bloques de atención de un veterinario en un día de la semana.
     * Mantiene compatibilidad con US-04.
     *
     * @return array<array{hora_inicio:string,hora_fin:string}>
     */
    public function findByVeterinarioYDia(int $idVeterinario, int $diaSemana): array {
        $stmt = $this->db->prepare(
            'SELECT id_horario, id_veterinario, dia_semana, hora_inicio, hora_fin
               FROM horarios_atencion
              WHERE id_veterinario = :id_veterinario
                AND dia_semana = :dia
              ORDER BY hora_inicio ASC'
        );
        $stmt->execute([
            ':id_veterinario' => $idVeterinario,
            ':dia'            => $diaSemana,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listado general de todas las franjas horarias con datos del veterinario.
     *
     * @return array<array<string,mixed>>
     */
    public function findAll(): array {
        $stmt = $this->db->query(
            self::SELECT_DETALLADO . ' ORDER BY h.dia_semana ASC, h.hora_inicio ASC, u.nombre ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Franjas de un veterinario ordenadas por día y hora de inicio.
     *
     * @return array<array<string,mixed>>
     */
    public function findByVeterinario(int $idVeterinario): array {
        $stmt = $this->db->prepare(
            self::SELECT_DETALLADO . ' WHERE h.id_veterinario = :id ORDER BY h.dia_semana ASC, h.hora_inicio ASC'
        );
        $stmt->execute([':id' => $idVeterinario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una franja por su identificador primario.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $idHorario): ?array {
        $stmt = $this->db->prepare(
            self::SELECT_DETALLADO . ' WHERE h.id_horario = :id LIMIT 1'
        );
        $stmt->execute([':id' => $idHorario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca si existen franjas solapadas para un veterinario y día determinado.
     * Dos intervalos [A, B) y [C, D) se solapan si A < D AND B > C.
     * Si se pasa $excluirIdHorario, se ignora ese id (útil para edición).
     *
     * @return array<array<string,mixed>>
     */
    public function findSolapadas(int $idVeterinario, int $diaSemana, string $horaInicio, string $horaFin, ?int $excluirIdHorario = null): array {
        $sql = 'SELECT id_horario, id_veterinario, dia_semana, hora_inicio, hora_fin
                  FROM horarios_atencion
                 WHERE id_veterinario = :id_veterinario
                   AND dia_semana = :dia_semana
                   AND hora_inicio < :hora_fin
                   AND hora_fin > :hora_inicio';
        $params = [
            ':id_veterinario' => $idVeterinario,
            ':dia_semana'     => $diaSemana,
            ':hora_fin'       => $horaFin,
            ':hora_inicio'    => $horaInicio,
        ];

        if ($excluirIdHorario !== null) {
            $sql .= ' AND id_horario != :excluir_id';
            $params[':excluir_id'] = $excluirIdHorario;
        }

        $sql .= ' ORDER BY hora_inicio ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta una nueva franja horaria.
     */
    public function insert(int $idVeterinario, int $diaSemana, string $horaInicio, string $horaFin): int {
        $stmt = $this->db->prepare(
            'INSERT INTO horarios_atencion (id_veterinario, dia_semana, hora_inicio, hora_fin)
             VALUES (:id_veterinario, :dia_semana, :hora_inicio, :hora_fin)'
        );
        $stmt->execute([
            ':id_veterinario' => $idVeterinario,
            ':dia_semana'     => $diaSemana,
            ':hora_inicio'    => $horaInicio,
            ':hora_fin'       => $horaFin,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza una franja horaria existente.
     */
    public function update(int $idHorario, int $diaSemana, string $horaInicio, string $horaFin): bool {
        $stmt = $this->db->prepare(
            'UPDATE horarios_atencion
                SET dia_semana = :dia_semana,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin
              WHERE id_horario = :id_horario'
        );
        return $stmt->execute([
            ':id_horario'  => $idHorario,
            ':dia_semana'  => $diaSemana,
            ':hora_inicio' => $horaInicio,
            ':hora_fin'    => $horaFin,
        ]);
    }

    /**
     * Elimina físicamente una franja horaria.
     */
    public function delete(int $idHorario): bool {
        $stmt = $this->db->prepare('DELETE FROM horarios_atencion WHERE id_horario = :id_horario');
        return $stmt->execute([':id_horario' => $idHorario]);
    }
}
