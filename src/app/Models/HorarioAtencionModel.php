<?php
namespace App\Models;

use PDO;

/**
 * HorarioAtencionModel — US-04, ARQ-01, SEC-01
 * Modelo de datos (solo lectura) para la tabla `horarios_atencion`.
 * `dia_semana`: 1 = lunes … 7 = domingo (mismo criterio que `date('N')`).
 */
class HorarioAtencionModel extends BaseModel {

    /**
     * Bloques de atención de un veterinario en un día de la semana.
     *
     * @return array<array{hora_inicio:string,hora_fin:string}>
     */
    public function findByVeterinarioYDia(int $idVeterinario, int $diaSemana): array {
        $stmt = $this->db->prepare(
            'SELECT hora_inicio, hora_fin
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
}
