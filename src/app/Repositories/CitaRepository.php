<?php
namespace App\Repositories;

use App\Models\CitaModel;

/**
 * CitaRepository — US-04 (T-031), ARQ-01, DAT-01
 * Capa intermedia entre el servicio y el modelo de datos para la entidad Cita.
 */
class CitaRepository {

    private CitaModel $model;

    public function __construct(?CitaModel $model = null) {
        $this->model = $model ?? new CitaModel();
    }

    /**
     * Persiste una cita (queda en estado Programada salvo que se indique otro).
     *
     * @param array<string,mixed> $data
     * @return int ID de la cita creada.
     */
    public function create(array $data): int {
        return $this->model->insert($data);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array {
        return $this->model->findById($id);
    }

    /**
     * Una cita con nombres de mascota, propietario, servicio y veterinario.
     *
     * @return array<string,mixed>|null
     */
    public function findDetalleById(int $id): ?array {
        return $this->model->findDetalleById($id);
    }

    /**
     * Citas con detalle, opcionalmente filtradas por estado.
     *
     * @return array<array<string,mixed>>
     */
    public function listDetalle(?string $estado = null, int $limite = 200): array {
        return $this->model->findAllDetalle($estado, $limite);
    }

    /**
     * Cancela la cita solo si está Programada (T-037).
     *
     * @return bool true si se canceló; false si no existe o ya no estaba Programada.
     */
    public function cancelarSiProgramada(int $id, ?string $motivo): bool {
        return $this->model->cancelarSiProgramada($id, $motivo) === 1;
    }

    /**
     * Citas de un veterinario en una fecha, opcionalmente filtradas por estado.
     *
     * @param string[]|null $estados
     * @return array<array<string,mixed>>
     */
    public function listByVeterinarioFechaEstado(int $idVeterinario, string $fecha, ?array $estados = null): array {
        return $this->model->findByVeterinarioFecha($idVeterinario, $fecha, $estados);
    }

    /**
     * Citas que ocupan agenda: todas excepto las canceladas.
     * Con $bloquear = true la lectura es de bloqueo (solo dentro de una transacción).
     *
     * @return array<array<string,mixed>>
     */
    public function listOcupadas(int $idVeterinario, string $fecha, bool $bloquear = false): array {
        return $this->model->findByVeterinarioFecha(
            $idVeterinario,
            $fecha,
            [CitaModel::ESTADO_PROGRAMADA, CitaModel::ESTADO_ATENDIDA],
            $bloquear
        );
    }

    /**
     * Ejecuta una operación dentro de una transacción.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaccion(callable $fn): mixed {
        return $this->model->transaccion($fn);
    }
}
