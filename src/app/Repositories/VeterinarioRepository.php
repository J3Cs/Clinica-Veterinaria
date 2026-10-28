<?php
namespace App\Repositories;

use App\Models\VeterinarioModel;
use App\Models\UsuarioModel;

/**
 * VeterinarioRepository — US-04, US-09 (T-060), US-12 (T-077), ARQ-01, BAJA-01
 * Repositorio para la entidad Veterinario y su cuenta de usuario asociada.
 * El alta de usuario y ficha se ejecuta en transacción; las consultas permiten
 * identificar relaciones con citas, consultas y horarios.
 */
class VeterinarioRepository {

    private VeterinarioModel $model;
    private ?UsuarioModel $usuarioModel;

    public function __construct(?VeterinarioModel $model = null, ?UsuarioModel $usuarioModel = null) {
        $this->model        = $model ?? new VeterinarioModel();
        $this->usuarioModel = $usuarioModel;
    }

    private function getUsuarioModel(): UsuarioModel {
        $this->usuarioModel ??= new UsuarioModel();
        return $this->usuarioModel;
    }

    /** @return array<array<string,mixed>> */
    public function listActivos(): array {
        return $this->model->findAllActivos();
    }

    /** @return array<array<string,mixed>> */
    public function listAll(bool $onlyActive = false): array {
        return $this->model->findAll($onlyActive);
    }

    /**
     * Busca veterinarios por nombre, usuario, correo, especialidad, teléfono o licencia (T-060).
     *
     * @return array<array<string,mixed>>
     */
    public function search(?string $termino = null, bool $onlyActive = false): array {
        if (method_exists($this->model, 'search')) {
            return $this->model->search($termino, $onlyActive);
        }
        return $this->model->findAll($onlyActive);
    }

    /** @return array<string,mixed>|null */
    public function findActivoById(int $id): ?array {
        return $this->model->findActivoById($id);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }

    /** @return array<string,mixed>|null */
    public function findByUsuarioId(int $idUsuario): ?array {
        if (method_exists($this->model, 'findByUsuarioId')) {
            return $this->model->findByUsuarioId($idUsuario);
        }
        return $this->model->findActivoByUsuarioId($idUsuario);
    }

    /** @return array<string,mixed>|null */
    public function findActivoByUsuarioId(int $idUsuario): ?array {
        return $this->model->findActivoByUsuarioId($idUsuario);
    }

    /**
     * Verifica si una licencia médica ya está registrada por otro veterinario (T-060, T-061).
     */
    public function isLicenciaRegistered(string $licencia, ?int $excludeIdVeterinario = null): bool {
        if (method_exists($this->model, 'existsLicenciaMedica')) {
            return $this->model->existsLicenciaMedica(trim($licencia), $excludeIdVeterinario);
        }
        return false;
    }

    /** Bloquea al veterinario dentro de la transacción en curso. */
    public function bloquearActivo(int $id): bool {
        return $this->model->bloquearActivoPorId($id);
    }

    /**
     * Crea la cuenta de usuario (`rol = 'veterinario'`) y su ficha en `veterinarios`
     * de forma atómica dentro de una transacción PDO (T-060).
     *
     * @param array{nombre: string, correo: string, usuario: string, contrasena: string, rol?: string, activo?: int} $datosUsuario
     * @param array{especialidad?: ?string, telefono?: ?string, licencia_medica?: ?string} $datosVeterinario
     * @return array{id_veterinario: int, id_usuario: int}
     */
    public function createConUsuario(array $datosUsuario, array $datosVeterinario): array {
        return $this->model->transaccion(function () use ($datosUsuario, $datosVeterinario): array {
            $idUsuario = $this->getUsuarioModel()->insert([
                'nombre'     => $datosUsuario['nombre'],
                'correo'     => $datosUsuario['correo'],
                'usuario'    => $datosUsuario['usuario'],
                'contrasena' => $datosUsuario['contrasena'],
                'rol'        => $datosUsuario['rol'] ?? 'veterinario',
                'activo'     => $datosUsuario['activo'] ?? 1,
            ]);

            $idVeterinario = $this->model->insert([
                'id_usuario'      => $idUsuario,
                'especialidad'    => $datosVeterinario['especialidad'] ?? null,
                'telefono'        => $datosVeterinario['telefono'] ?? null,
                'licencia_medica' => $datosVeterinario['licencia_medica'] ?? null,
            ]);

            return [
                'id_veterinario' => $idVeterinario,
                'id_usuario'     => $idUsuario,
            ];
        });
    }

    /**
     * Obtiene las relaciones clínicas/citas asociadas al veterinario (T-060, T-077).
     *
     * @return array{
     *     citas_total: int,
     *     citas_programadas: int,
     *     consultas_total: int,
     *     horarios_total: int,
     *     tiene_dependencias: bool
     * }
     */
    public function obtenerRelaciones(int $idVeterinario): array {
        return $this->model->contarRelaciones($idVeterinario);
    }

    /**
     * Actualiza los datos profesionales y de usuario de un veterinario en transacción (T-060, T-077).
     *
     * @param int   $idVeterinario
     * @param int   $idUsuario
     * @param array $datos
     * @return bool
     */
    public function update(int $idVeterinario, int $idUsuario, array $datos): bool {
        return $this->model->transaccion(function () use ($idVeterinario, $idUsuario, $datos): bool {
            $okUser = $this->getUsuarioModel()->update($idUsuario, [
                'nombre'     => $datos['nombre'],
                'correo'     => $datos['correo'],
                'usuario'    => $datos['usuario'],
                'contrasena' => $datos['contrasena'] ?? null,
            ]);

            $okVet = $this->model->update($idVeterinario, [
                'especialidad'    => $datos['especialidad'] ?? null,
                'telefono'        => $datos['telefono'] ?? null,
                'licencia_medica' => $datos['licencia_medica'] ?? null,
            ]);

            return $okUser && $okVet;
        });
    }

    /**
     * Desactiva lógicamente al veterinario a través de su cuenta de usuario (BAJA-01, T-060, T-077).
     * Al quedar `usuarios.activo = 0`, el veterinario ya no puede iniciar sesión.
     */
    public function desactivar(int $idUsuario): bool {
        return $this->getUsuarioModel()->setActivo($idUsuario, 0);
    }

    /**
     * Cancela automáticamente las citas en estado 'Programada' de un veterinario desactivado.
     */
    public function cancelarCitasProgramadas(
        int $idVeterinario,
        string $motivo = 'Cancelada automáticamente por desactivación del veterinario'
    ): int {
        if (method_exists($this->model, 'cancelarCitasProgramadas')) {
            return $this->model->cancelarCitasProgramadas($idVeterinario, $motivo);
        }
        return 0;
    }

    /**
     * Reactiva la cuenta de usuario del veterinario.
     */
    public function activar(int $idUsuario): bool {
        return $this->getUsuarioModel()->setActivo($idUsuario, 1);
    }

    /**
     * Elimina físicamente al veterinario y su usuario en transacción cuando no existen citas ni consultas (T-060, T-077).
     */
    public function deleteFisico(int $idVeterinario, int $idUsuario): bool {
        return $this->model->transaccion(function () use ($idVeterinario, $idUsuario): bool {
            $okVet = $this->model->delete($idVeterinario);
            if (!$okVet) {
                return false;
            }
            return $this->getUsuarioModel()->delete($idUsuario);
        });
    }
}
