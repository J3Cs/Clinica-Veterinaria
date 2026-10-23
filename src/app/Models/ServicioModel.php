<?php
namespace App\Models;

use PDO;

/**
 * ServicioModel — US-04, ARQ-01, SEC-01
 * Modelo de datos (solo lectura) para la tabla `servicios`.
 */
class ServicioModel extends BaseModel {

    /** @return array<array<string,mixed>> */
    public function findAllActivos(): array {
        $stmt = $this->db->query(
            'SELECT id_servicio, nombre_servicio, descripcion, duracion_estimada_min, activo
               FROM servicios
              WHERE activo = 1
              ORDER BY nombre_servicio ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function findActivoById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_servicio, nombre_servicio, descripcion, duracion_estimada_min, activo
               FROM servicios
              WHERE id_servicio = :id AND activo = 1
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
