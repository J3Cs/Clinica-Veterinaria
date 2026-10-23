<?php
namespace App\Models;

use InvalidArgumentException;
use PDO;

/**
 * CitaModel — US-04 (T-030), ARQ-01, SEC-01, DAT-01
 * Modelo de datos para la tabla `citas`.
 *
 * Responsabilidad exclusiva: acceso a datos mediante sentencias preparadas PDO.
 * Sin lógica de negocio. Sin HTML.
 */
class CitaModel extends BaseModel {

    public const ESTADO_PROGRAMADA = 'Programada';
    public const ESTADO_ATENDIDA   = 'Atendida';
    public const ESTADO_CANCELADA  = 'Cancelada';

    /** Estados permitidos por el ENUM de la tabla (T-030). */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_ATENDIDA,
        self::ESTADO_CANCELADA,
    ];

    /**
     * Inserta una cita. Las referencias (mascota, veterinario, servicio) deben
     * existir: la base de datos lo garantiza con sus claves foráneas (DAT-01).
     *
     * @param array{
     *     id_mascota: int, id_veterinario: int, id_servicio: int,
     *     fecha_cita: string, hora_cita: string,
     *     estado?: string, motivo_cancelacion?: ?string
     * } $data
     * @return int ID generado por MySQL.
     * @throws InvalidArgumentException si el estado no es válido.
     */
    public function insert(array $data): int {
        $estado = $data['estado'] ?? self::ESTADO_PROGRAMADA;
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de cita no válido: ' . $estado);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO citas (id_mascota, id_veterinario, id_servicio, fecha_cita, hora_cita, estado, motivo_cancelacion)
             VALUES (:id_mascota, :id_veterinario, :id_servicio, :fecha_cita, :hora_cita, :estado, :motivo_cancelacion)'
        );
        $stmt->execute([
            ':id_mascota'         => (int) $data['id_mascota'],
            ':id_veterinario'     => (int) $data['id_veterinario'],
            ':id_servicio'        => (int) $data['id_servicio'],
            ':fecha_cita'         => $data['fecha_cita'],
            ':hora_cita'          => $data['hora_cita'],
            ':estado'             => $estado,
            ':motivo_cancelacion' => $data['motivo_cancelacion'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_cita, id_mascota, id_veterinario, id_servicio, fecha_cita, hora_cita,
                    estado, motivo_cancelacion, fecha_creacion
               FROM citas
              WHERE id_cita = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Citas de un veterinario en una fecha, con la duración estimada de su servicio.
     *
     * @param string[]|null $estados   Si es null no filtra por estado.
     * @param bool          $bloquear  Añade FOR UPDATE (solo dentro de una transacción):
     *                                 lee siempre lo último confirmado, no una instantánea.
     * @return array<array<string,mixed>>
     */
    public function findByVeterinarioFecha(int $idVeterinario, string $fecha, ?array $estados = null, bool $bloquear = false): array {
        $sql = 'SELECT c.id_cita, c.id_mascota, c.id_veterinario, c.id_servicio,
                       c.fecha_cita, c.hora_cita, c.estado,
                       s.duracion_estimada_min
                  FROM citas c
            INNER JOIN servicios s ON s.id_servicio = c.id_servicio
                 WHERE c.id_veterinario = :id_veterinario
                   AND c.fecha_cita = :fecha';
        $params = [
            ':id_veterinario' => $idVeterinario,
            ':fecha'          => $fecha,
        ];

        if ($estados !== null) {
            if ($estados === []) {
                return [];
            }
            $marcas = [];
            foreach (array_values($estados) as $i => $estado) {
                $marcas[]            = ':estado' . $i;
                $params[':estado' . $i] = $estado;
            }
            $sql .= ' AND c.estado IN (' . implode(', ', $marcas) . ')';
        }

        $sql .= ' ORDER BY c.hora_cita ASC';
        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ejecuta $fn dentro de una transacción: confirma si termina bien y
     * revierte ante cualquier excepción.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaccion(callable $fn): mixed {
        $this->db->beginTransaction();
        try {
            $resultado = $fn();
            $this->db->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
