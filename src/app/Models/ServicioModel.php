<?php
namespace App\Models;

use PDO;

/**
 * ServicioModel — US-04, US-10 (T-065), ARQ-01, SEC-01
 * Modelo de datos para la tabla `servicios`.
 * CRUD completo con sentencias preparadas.
 */
class ServicioModel extends BaseModel {

    // ────────────────────────────────────────────────────────────── //
    //  Lectura
    // ────────────────────────────────────────────────────────────── //

    /**
     * Lista servicios. Si $soloActivos = true filtra por activo = 1.
     * @return array<array<string,mixed>>
     */
    public function findAll(bool $soloActivos = true): array {
        $sql = 'SELECT id_servicio, nombre_servicio, descripcion, duracion_estimada_min, activo
                  FROM servicios';
        if ($soloActivos) {
            $sql .= ' WHERE activo = 1';
        }
        $sql .= ' ORDER BY nombre_servicio ASC';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Alias de compatibilidad con US-04 (solo activos).
     * @return array<array<string,mixed>>
     */
    public function findAllActivos(): array {
        return $this->findAll(true);
    }

    /**
     * Busca un servicio por ID sin filtrar por estado.
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_servicio, nombre_servicio, descripcion, duracion_estimada_min, activo
               FROM servicios
              WHERE id_servicio = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Alias de compatibilidad con US-04 (solo activos).
     * @return array<string,mixed>|null
     */
    public function findActivoById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_servicio, nombre_servicio, descripcion, duracion_estimada_min, activo
               FROM servicios
              WHERE id_servicio = :id AND activo = 1
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca un servicio por nombre exacto (case-insensitive).
     * Opcionalmente excluye un ID (para validar unicidad en actualización).
     * @return array<string,mixed>|null
     */
    public function findByNombre(string $nombre, ?int $excludeId = null): ?array {
        $sql = 'SELECT id_servicio, nombre_servicio, descripcion, duracion_estimada_min, activo
                  FROM servicios
                 WHERE LOWER(nombre_servicio) = LOWER(:nombre)';
        $params = [':nombre' => $nombre];
        if ($excludeId !== null) {
            $sql .= ' AND id_servicio != :excl';
            $params[':excl'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica si un servicio tiene citas asociadas (BAJA-01).
     */
    public function tieneCitasAsociadas(int $id): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM citas WHERE id_servicio = :id'
        );
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // ────────────────────────────────────────────────────────────── //
    //  Escritura
    // ────────────────────────────────────────────────────────────── //

    /**
     * Inserta un nuevo servicio.
     * @param array{nombre_servicio:string, descripcion:?string, duracion_estimada_min:int} $data
     * @return int ID del servicio creado
     */
    public function insert(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO servicios (nombre_servicio, descripcion, duracion_estimada_min, activo)
             VALUES (:nombre, :descripcion, :duracion, 1)'
        );
        $stmt->execute([
            ':nombre'      => $data['nombre_servicio'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':duracion'    => (int) $data['duracion_estimada_min'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza nombre, descripción y duración de un servicio.
     * @param array{nombre_servicio:string, descripcion:?string, duracion_estimada_min:int} $data
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE servicios
                SET nombre_servicio = :nombre,
                    descripcion = :descripcion,
                    duracion_estimada_min = :duracion
              WHERE id_servicio = :id'
        );
        $stmt->execute([
            ':nombre'      => $data['nombre_servicio'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':duracion'    => (int) $data['duracion_estimada_min'],
            ':id'          => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Desactiva un servicio (baja lógica — BAJA-01).
     */
    public function desactivar(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE servicios SET activo = 0 WHERE id_servicio = :id AND activo = 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reactiva un servicio inactivo.
     */
    public function activar(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE servicios SET activo = 1 WHERE id_servicio = :id AND activo = 0'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
