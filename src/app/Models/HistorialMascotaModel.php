<?php
namespace App\Models;

use PDO;

/**
 * HistorialMascotaModel — US-06 (T-044), ARQ-01, SEC-01
 * Modelo de SOLO LECTURA para `historial_mascota` (antecedentes médicos previos:
 * condiciones, alergias). Las consultas NO se copian aquí: el historial
 * consolidado las toma directamente de `consultas_veterinarias`.
 */
class HistorialMascotaModel extends BaseModel {

    /**
     * @return array<array<string,mixed>>
     */
    public function findByMascota(int $idMascota, int $limite = 50): array {
        $stmt = $this->db->prepare(
            'SELECT id_historial_mascota, condicion_medica, descripcion_problema,
                    alergias, fecha_registro_antecedente
               FROM historial_mascota
              WHERE id_mascota = :id
              ORDER BY fecha_registro_antecedente DESC, id_historial_mascota DESC
              LIMIT ' . max(1, $limite)
        );
        $stmt->execute([':id' => $idMascota]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
