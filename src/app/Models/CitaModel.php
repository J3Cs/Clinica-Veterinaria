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

    /** Columnas de una cita con los nombres de mascota, propietario, servicio y veterinario. */
    private const SELECT_DETALLE =
        'SELECT c.id_cita, c.id_mascota, c.id_veterinario, c.id_servicio,
                c.fecha_cita, c.hora_cita, c.estado, c.motivo_cancelacion, c.fecha_creacion,
                m.nombre AS mascota_nombre, m.especie AS mascota_especie,
                p.nombre_completo AS propietario_nombre,
                s.nombre_servicio, s.duracion_estimada_min,
                u.nombre AS veterinario_nombre
           FROM citas c
     INNER JOIN mascotas m      ON m.id_mascota = c.id_mascota
     INNER JOIN propietarios p  ON p.id_propietario = m.id_propietario
     INNER JOIN servicios s     ON s.id_servicio = c.id_servicio
     INNER JOIN veterinarios v  ON v.id_veterinario = c.id_veterinario
     INNER JOIN usuarios u      ON u.id_usuario = v.id_usuario';

    /**
     * Una cita con los datos necesarios para mostrarla.
     *
     * @return array<string,mixed>|null
     */
    public function findDetalleById(int $id): ?array {
        $stmt = $this->db->prepare(self::SELECT_DETALLE . ' WHERE c.id_cita = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Listado de citas con detalle, de la más próxima a la más lejana.
     *
     * @param string|null $estado Si es null no filtra por estado.
     * @return array<array<string,mixed>>
     */
    public function findAllDetalle(?string $estado = null, int $limite = 200): array {
        $sql    = self::SELECT_DETALLE;
        $params = [];
        if ($estado !== null) {
            $sql .= ' WHERE c.estado = :estado';
            $params[':estado'] = $estado;
        }
        $sql .= ' ORDER BY c.fecha_cita ASC, c.hora_cita ASC, c.id_cita ASC LIMIT ' . max(1, $limite);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cancela una cita SOLO si sigue Programada (T-037).
     * La condición va dentro del UPDATE: si otra persona la canceló o atendió
     * entre tanto, no se afecta ninguna fila y el llamador lo sabe por el conteo.
     *
     * @return int Filas afectadas: 1 si se canceló, 0 si no estaba Programada o no existe.
     */
    public function cancelarSiProgramada(int $id, ?string $motivo): int {
        $stmt = $this->db->prepare(
            'UPDATE citas
                SET estado = :nuevo, motivo_cancelacion = :motivo
              WHERE id_cita = :id AND estado = :actual'
        );
        $stmt->execute([
            ':nuevo'  => self::ESTADO_CANCELADA,
            ':motivo' => $motivo,
            ':id'     => $id,
            ':actual' => self::ESTADO_PROGRAMADA,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Ejecuta $fn dentro de una transacción: confirma si termina bien y
     * revierte ante cualquier excepción.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */

    /**
     * Citas programadas de un veterinario a partir de una fecha determinada.
     * Incluye duración del servicio para calcular solapamientos.
     *
     * @return array<array<string,mixed>>
     */
    public function findProgramadasFuturasByVeterinario(int $idVeterinario, ?string $fechaDesde = null): array {
        $fechaDesde = $fechaDesde ?? date('Y-m-d');
        $sql = 'SELECT c.id_cita, c.id_mascota, c.id_veterinario, c.id_servicio,
                       c.fecha_cita, c.hora_cita, c.estado,
                       s.duracion_estimada_min, s.nombre_servicio,
                       m.nombre AS mascota_nombre, p.nombre_completo AS propietario_nombre
                  FROM citas c
            INNER JOIN servicios s    ON s.id_servicio = c.id_servicio
            INNER JOIN mascotas m     ON m.id_mascota = c.id_mascota
            INNER JOIN propietarios p ON p.id_propietario = m.id_propietario
                 WHERE c.id_veterinario = :id_veterinario
                   AND c.fecha_cita >= :fecha_desde
                   AND c.estado = :estado
              ORDER BY c.fecha_cita ASC, c.hora_cita ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_veterinario' => $idVeterinario,
            ':fecha_desde'    => $fechaDesde,
            ':estado'         => self::ESTADO_PROGRAMADA,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

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
