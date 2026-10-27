<?php
namespace App\Models;

use PDO;

/**
 * VeterinarioModel — US-04, ARQ-01, SEC-01
 * Modelo de datos para `veterinarios`. Un veterinario está activo cuando
 * su cuenta de `usuarios` está activa (la tabla no tiene columna propia).
 */
class VeterinarioModel extends BaseModel {

    private const SELECT_ACTIVO =
        'SELECT v.id_veterinario, u.nombre, v.especialidad, v.telefono
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
        $row = $stmt->fetch();
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
        $row = $stmt->fetch();
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
}
