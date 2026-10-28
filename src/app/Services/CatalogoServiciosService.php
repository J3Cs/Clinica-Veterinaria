<?php
namespace App\Services;

use App\Repositories\ServicioRepository;

/**
 * CatalogoServiciosService — US-10 (T-067), ARQ-01
 * Reglas de negocio del catálogo de servicios:
 *
 *  - Nombre obligatorio (3-100 chars), único (case-insensitive).
 *  - Descripción opcional (máx 500 chars).
 *  - Duración estimada obligatoria, entero positivo (minutos).
 *  - Desactivación lógica (BAJA-01): servicios con citas no se borran.
 *  - Servicios inactivos siguen disponibles para historial de citas.
 */
class CatalogoServiciosService {

    public const NOMBRE_MIN     = 3;
    public const NOMBRE_MAX     = 100;
    public const DESC_MAX       = 500;
    public const DURACION_MIN   = 1;

    public function __construct(
        private ?ServicioRepository $repo = null
    ) {
        $this->repo ??= new ServicioRepository();
    }

    // ────────────────────────────────────────────────────────────── //
    //  Consultas
    // ────────────────────────────────────────────────────────────── //

    /**
     * Lista todos los servicios (activos e inactivos) para la vista de admin.
     * @return array<array<string,mixed>>
     */
    public function listarServicios(): array {
        return $this->repo->listAll(false);
    }

    /**
     * Retorna un servicio por ID sin filtro de estado (para edición).
     * @return array<string,mixed>|null
     */
    public function obtenerServicioPorId(mixed $id): ?array {
        $idInt = self::aEntero($id);
        if ($idInt <= 0) {
            return null;
        }
        return $this->repo->findById($idInt);
    }

    // ────────────────────────────────────────────────────────────── //
    //  Alta
    // ────────────────────────────────────────────────────────────── //

    /**
     * Registra un nuevo servicio en el catálogo.
     *
     * @param array{nombre_servicio?:mixed, descripcion?:mixed, duracion_estimada_min?:mixed} $datos
     * @return array{success:bool, message:string, errors:array<string,string>, id:?int}
     */
    public function registrarServicio(array $datos): array {
        [$nombre, $descripcion, $duracion, $errors] = $this->validarCampos($datos);

        if ($errors !== []) {
            return self::fallo('Por favor corrige los errores señalados.', $errors);
        }

        // Unicidad de nombre
        if ($this->repo->findByNombre($nombre) !== null) {
            return self::fallo(
                'Por favor corrige los errores señalados.',
                ['nombre_servicio' => 'Ya existe un servicio con ese nombre.']
            );
        }

        $id = $this->repo->create([
            'nombre_servicio'      => $nombre,
            'descripcion'          => $descripcion,
            'duracion_estimada_min' => $duracion,
        ]);

        return [
            'success' => true,
            'message' => sprintf('Servicio «%s» registrado exitosamente.', $nombre),
            'errors'  => [],
            'id'      => $id,
        ];
    }

    // ────────────────────────────────────────────────────────────── //
    //  Actualización
    // ────────────────────────────────────────────────────────────── //

    /**
     * Actualiza los datos de un servicio existente.
     *
     * @param array{nombre_servicio?:mixed, descripcion?:mixed, duracion_estimada_min?:mixed} $datos
     * @return array{success:bool, message:string, errors:array<string,string>}
     */
    public function actualizarServicio(mixed $id, array $datos): array {
        $idInt = self::aEntero($id);
        if ($idInt <= 0) {
            return self::falloSinId('El identificador de servicio no es válido.');
        }

        $servicio = $this->repo->findById($idInt);
        if (!$servicio) {
            return self::falloSinId('El servicio no existe.');
        }

        [$nombre, $descripcion, $duracion, $errors] = $this->validarCampos($datos);

        if ($errors !== []) {
            return self::fallo('Por favor corrige los errores señalados.', $errors);
        }

        // Unicidad excluyendo el propio ID
        if ($this->repo->findByNombre($nombre, $idInt) !== null) {
            return self::fallo(
                'Por favor corrige los errores señalados.',
                ['nombre_servicio' => 'Ya existe otro servicio con ese nombre.']
            );
        }

        $this->repo->update($idInt, [
            'nombre_servicio'      => $nombre,
            'descripcion'          => $descripcion,
            'duracion_estimada_min' => $duracion,
        ]);

        return [
            'success' => true,
            'message' => sprintf('Servicio «%s» actualizado exitosamente.', $nombre),
            'errors'  => [],
        ];
    }

    // ────────────────────────────────────────────────────────────── //
    //  Baja lógica / Reactivación (BAJA-01)
    // ────────────────────────────────────────────────────────────── //

    /**
     * Desactiva un servicio (baja lógica).
     * Los servicios con citas asociadas se desactivan igualmente;
     * los inactivos siguen disponibles al ver citas históricas.
     *
     * @return array{success:bool, message:string}
     */
    public function desactivarServicio(mixed $id): array {
        $idInt = self::aEntero($id);
        if ($idInt <= 0) {
            return ['success' => false, 'message' => 'El identificador de servicio no es válido.'];
        }

        $servicio = $this->repo->findById($idInt);
        if (!$servicio) {
            return ['success' => false, 'message' => 'El servicio no existe.'];
        }
        if ((int) $servicio['activo'] === 0) {
            return ['success' => false, 'message' => 'El servicio ya está inactivo.'];
        }

        $this->repo->desactivar($idInt);
        return [
            'success' => true,
            'message' => sprintf(
                'Servicio «%s» desactivado. No se ofrecerá en nuevos agendamientos, pero sus citas históricas se conservan.',
                (string) $servicio['nombre_servicio']
            ),
        ];
    }

    /**
     * Reactiva un servicio inactivo.
     *
     * @return array{success:bool, message:string}
     */
    public function activarServicio(mixed $id): array {
        $idInt = self::aEntero($id);
        if ($idInt <= 0) {
            return ['success' => false, 'message' => 'El identificador de servicio no es válido.'];
        }

        $servicio = $this->repo->findById($idInt);
        if (!$servicio) {
            return ['success' => false, 'message' => 'El servicio no existe.'];
        }
        if ((int) $servicio['activo'] === 1) {
            return ['success' => false, 'message' => 'El servicio ya está activo.'];
        }

        $this->repo->activar($idInt);
        return [
            'success' => true,
            'message' => sprintf('Servicio «%s» reactivado exitosamente.', (string) $servicio['nombre_servicio']),
        ];
    }

    // ────────────────────────────────────────────────────────────── //
    //  Validación interna
    // ────────────────────────────────────────────────────────────── //

    /**
     * Valida y normaliza los campos comunes a alta y edición.
     *
     * @return array{0:string, 1:?string, 2:int, 3:array<string,string>}
     */
    private function validarCampos(array $datos): array {
        $errors = [];

        // Nombre
        $nombre = trim((string) ($datos['nombre_servicio'] ?? ''));
        $nombre = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $nombre) ?? '';
        if ($nombre === '') {
            $errors['nombre_servicio'] = 'El nombre del servicio es obligatorio.';
        } elseif (mb_strlen($nombre, 'UTF-8') < self::NOMBRE_MIN) {
            $errors['nombre_servicio'] = sprintf('El nombre debe tener al menos %d caracteres.', self::NOMBRE_MIN);
        } elseif (mb_strlen($nombre, 'UTF-8') > self::NOMBRE_MAX) {
            $errors['nombre_servicio'] = sprintf('El nombre no puede superar los %d caracteres.', self::NOMBRE_MAX);
        }

        // Descripción (opcional)
        $rawDesc = $datos['descripcion'] ?? null;
        $descripcion = null;
        if ($rawDesc !== null && $rawDesc !== '') {
            $desc = trim((string) $rawDesc);
            $desc = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $desc) ?? '';
            if (mb_strlen($desc, 'UTF-8') > self::DESC_MAX) {
                $errors['descripcion'] = sprintf('La descripción no puede superar los %d caracteres.', self::DESC_MAX);
            } else {
                $descripcion = $desc !== '' ? $desc : null;
            }
        }

        // Duración (obligatorio, entero positivo)
        $rawDur  = $datos['duracion_estimada_min'] ?? '';
        $duracion = 0;
        $durStr  = trim((string) $rawDur);
        if ($durStr === '' || !ctype_digit($durStr)) {
            $errors['duracion_estimada_min'] = 'La duración estimada es obligatoria y debe ser un número entero positivo.';
        } else {
            $duracion = (int) $durStr;
            if ($duracion < self::DURACION_MIN) {
                $errors['duracion_estimada_min'] = sprintf('La duración debe ser mayor o igual a %d minuto(s).', self::DURACION_MIN);
            }
        }

        return [$nombre, $descripcion, $duracion, $errors];
    }

    // ────────────────────────────────────────────────────────────── //
    //  Helpers
    // ────────────────────────────────────────────────────────────── //

    private static function aEntero(mixed $valor): int {
        if (is_int($valor)) {
            return $valor;
        }
        return (is_string($valor) && ctype_digit($valor)) ? (int) $valor : 0;
    }

    /** @param array<string,string> $errors */
    private static function fallo(string $mensaje, array $errors): array {
        return ['success' => false, 'message' => $mensaje, 'errors' => $errors, 'id' => null];
    }

    private static function falloSinId(string $mensaje): array {
        return ['success' => false, 'message' => $mensaje, 'errors' => [], 'id' => null];
    }
}
