<?php
namespace App\Models;

use InvalidArgumentException;
use PDO;

/**
 * ConsultaVeterinariaModel — US-06 (T-042), ARQ-01, SEC-01, DAT-01
 * Modelo de datos para la tabla `consultas_veterinarias`.
 *
 * Una consulta pertenece a UNA cita (`id_cita` es UNIQUE en la base) y registra
 * diagnóstico y tratamiento (obligatorios) y observaciones y servicios adicionales
 * (opcionales). Sin lógica de negocio. Sin HTML.
 */
class ConsultaVeterinariaModel extends BaseModel {

    /**
     * Inserta una consulta con sentencia preparada.
     *
     * @param array{
     *     id_cita: int, id_mascota: int, id_veterinario: int, fecha_atencion: string,
     *     diagnostico: string, tratamiento: string,
     *     observaciones?: ?string, servicios_adicionales?: ?string
     * } $data
     * @return int ID generado por MySQL.
     * @throws InvalidArgumentException si falta el diagnóstico o el tratamiento.
     */
    public function insert(array $data): int {
        if (trim((string) ($data['diagnostico'] ?? '')) === '') {
            throw new InvalidArgumentException('El diagnóstico es obligatorio.');
        }
        if (trim((string) ($data['tratamiento'] ?? '')) === '') {
            throw new InvalidArgumentException('El tratamiento es obligatorio.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO consultas_veterinarias
                (id_cita, id_mascota, id_veterinario, fecha_atencion,
                 diagnostico, observaciones, tratamiento, servicios_adicionales)
             VALUES
                (:id_cita, :id_mascota, :id_veterinario, :fecha_atencion,
                 :diagnostico, :observaciones, :tratamiento, :servicios_adicionales)'
        );
        $stmt->execute([
            ':id_cita'               => (int) $data['id_cita'],
            ':id_mascota'            => (int) $data['id_mascota'],
            ':id_veterinario'        => (int) $data['id_veterinario'],
            ':fecha_atencion'        => $data['fecha_atencion'],
            ':diagnostico'           => $data['diagnostico'],
            ':observaciones'         => $data['observaciones'] ?? null,
            ':tratamiento'           => $data['tratamiento'],
            ':servicios_adicionales' => $data['servicios_adicionales'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByCitaId(int $idCita): ?array {
        $stmt = $this->db->prepare(
            'SELECT id_consulta, id_cita, id_mascota, id_veterinario, fecha_atencion,
                    diagnostico, observaciones, tratamiento, servicios_adicionales
               FROM consultas_veterinarias
              WHERE id_cita = :id_cita
              LIMIT 1'
        );
        $stmt->execute([':id_cita' => $idCita]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Consultas de una mascota, de la más reciente a la más antigua, con el
     * veterinario que atendió y el servicio de la cita.
     *
     * @return array<array<string,mixed>>
     */
    public function findByMascota(int $idMascota, int $limite = 50): array {
        $stmt = $this->db->prepare(
            'SELECT cv.id_consulta, cv.id_cita, cv.fecha_atencion,
                    cv.diagnostico, cv.observaciones, cv.tratamiento, cv.servicios_adicionales,
                    u.nombre AS veterinario_nombre, s.nombre_servicio
               FROM consultas_veterinarias cv
         INNER JOIN veterinarios v ON v.id_veterinario = cv.id_veterinario
         INNER JOIN usuarios u     ON u.id_usuario = v.id_usuario
         INNER JOIN citas c        ON c.id_cita = cv.id_cita
         INNER JOIN servicios s    ON s.id_servicio = c.id_servicio
              WHERE cv.id_mascota = :id
              ORDER BY cv.fecha_atencion DESC, cv.id_consulta DESC
              LIMIT ' . max(1, $limite)
        );
        $stmt->execute([':id' => $idMascota]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
