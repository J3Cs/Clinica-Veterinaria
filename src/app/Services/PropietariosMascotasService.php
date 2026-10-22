<?php
namespace App\Services;

use App\Repositories\PropietarioRepository;
use App\Repositories\MascotaRepository;

/**
 * PropietariosMascotasService — US-02, US-03, ARQ-01, UNI-01, DAT-01, SEC-01, BAJA-01
 * Contiene todas las reglas de negocio para la gestión de propietarios y sus mascotas.
 */
class PropietariosMascotasService {

    private PropietarioRepository $propietarioRepo;
    private MascotaRepository $mascotaRepo;

    public function __construct(
        ?PropietarioRepository $propietarioRepo = null,
        ?MascotaRepository $mascotaRepo = null
    ) {
        $this->propietarioRepo = $propietarioRepo ?? new PropietarioRepository();
        $this->mascotaRepo     = $mascotaRepo ?? new MascotaRepository();
    }

    // ────────────────────────────────────────────────────────────────── //
    //  SECCIÓN: Reglas de Propietarios (US-02, UNI-01)
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Normaliza un número de DUI salvadoreño (UNI-01).
     * Elimina espacios en blanco. Si se ingresan 9 dígitos consecutivos sin guion,
     * inserta automáticamente el guion en la posición correcta (########-#).
     *
     * @param string $dui
     * @return string
     */
    public function normalizarDui(string $dui): string {
        $limpio = trim($dui);
        // Quitar espacios interiores
        $limpio = str_replace(' ', '', $limpio);

        // Si son 9 dígitos continuos, insertar guion antes del último dígito
        if (preg_match('/^\d{9}$/', $limpio)) {
            return substr($limpio, 0, 8) . '-' . substr($limpio, 8, 1);
        }

        return $limpio;
    }

    /**
     * Valida si un DUI cumple estrictamente con el formato salvadoreño:
     * 8 dígitos numéricos, un guion y 1 dígito de control (10 caracteres).
     *
     * @param string $dui
     * @return bool
     */
    public function validarDui(string $dui): bool {
        return (bool) preg_match('/^\d{8}-\d{1}$/', $dui);
    }

    /**
     * Registra un nuevo propietario aplicando todas las reglas de negocio (T-018, UNI-01).
     *
     * @param array{nombre_completo?: string, dui?: string, direccion?: ?string, telefono?: ?string, correo?: ?string} $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int}
     */
    public function registrarPropietario(array $datos): array {
        $errors = [];

        // 1. Validación de nombre completo (obligatorio)
        $nombreCompleto = trim((string)($datos['nombre_completo'] ?? ''));
        if ($nombreCompleto === '') {
            $errors['nombre_completo'] = 'El nombre completo del propietario es obligatorio.';
        } elseif (mb_strlen($nombreCompleto) < 3) {
            $errors['nombre_completo'] = 'El nombre completo debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($nombreCompleto) > 150) {
            $errors['nombre_completo'] = 'El nombre completo no puede exceder los 150 caracteres.';
        }

        // 2. Validación de DUI (obligatorio, formato y unicidad - UNI-01)
        $rawDui = (string)($datos['dui'] ?? '');
        $dui = $this->normalizarDui($rawDui);

        if ($dui === '') {
            $errors['dui'] = 'El número de DUI es obligatorio.';
        } elseif (!$this->validarDui($dui)) {
            $errors['dui'] = 'El formato del DUI no es válido. Debe ser ########-# (ej. 12345678-9).';
        } elseif ($this->propietarioRepo->isDuiRegistered($dui)) {
            $errors['dui'] = 'El número de DUI ya se encuentra registrado en el sistema.';
        }

        // 3. Validación de correo (opcional - UNI-01)
        $correo = trim((string)($datos['correo'] ?? ''));
        if ($correo !== '') {
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errors['correo'] = 'El correo electrónico no tiene un formato válido.';
            } elseif (mb_strlen($correo) > 150) {
                $errors['correo'] = 'El correo electrónico no puede exceder los 150 caracteres.';
            }
        } else {
            $correo = null;
        }

        // 4. Validación de teléfono (opcional, no es identificador único - UNI-01)
        $telefono = trim((string)($datos['telefono'] ?? ''));
        if ($telefono !== '') {
            if (mb_strlen($telefono) > 20) {
                $errors['telefono'] = 'El número de teléfono no puede exceder los 20 caracteres.';
            }
        } else {
            $telefono = null;
        }

        // 5. Dirección (opcional)
        $direccion = trim((string)($datos['direccion'] ?? ''));
        $direccion = ($direccion !== '') ? $direccion : null;

        // Si existen errores, retornar sin persistir
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
                'id'      => null,
            ];
        }

        // Persistir en base de datos
        $idPropietario = $this->propietarioRepo->create([
            'dui'             => $dui,
            'nombre_completo' => $nombreCompleto,
            'direccion'       => $direccion,
            'telefono'        => $telefono,
            'correo'          => $correo,
            'activo'          => 1,
        ]);

        return [
            'success' => true,
            'message' => 'Propietario registrado exitosamente.',
            'errors'  => [],
            'id'      => $idPropietario,
        ];
    }

    /**
     * Consulta un propietario por su identificador primario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function obtenerPropietarioPorId(int $id): ?array {
        return $this->propietarioRepo->findById($id);
    }

    /**
     * Consulta un propietario por su DUI.
     *
     * @param string $dui
     * @return array<string,mixed>|null
     */
    public function obtenerPropietarioPorDui(string $dui): ?array {
        $duiNormalizado = $this->normalizarDui($dui);
        return $this->propietarioRepo->findByDui($duiNormalizado);
    }

    /**
     * Lista todos los propietarios activos.
     *
     * @param bool $soloActivos
     * @return array<array<string,mixed>>
     */
    public function listarPropietarios(bool $soloActivos = true): array {
        return $this->propietarioRepo->listAll($soloActivos);
    }

    // ────────────────────────────────────────────────────────────────── //
    //  SECCIÓN: Reglas de Mascotas (US-03, DAT-01, BAJA-01)
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Registra una nueva mascota vinculada a un propietario existente (US-03, DAT-01).
     *
     * @param array{
     *     id_propietario?: mixed,
     *     nombre?: string,
     *     especie?: string,
     *     raza?: string,
     *     sexo?: string,
     *     fecha_nacimiento?: ?string,
     *     caracteristicas_relevantes?: ?string
     * } $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int}
     */
    public function registrarMascota(array $datos): array {
        $errors = [];

        // 1. Validación de Integridad Referencial: Propietario (DAT-01)
        $rawIdPropietario = $datos['id_propietario'] ?? null;
        $idPropietario = is_numeric($rawIdPropietario) ? (int)$rawIdPropietario : 0;

        if ($idPropietario <= 0) {
            $errors['id_propietario'] = 'Debes seleccionar un propietario válido.';
        } else {
            $propietario = $this->propietarioRepo->findById($idPropietario);
            if (!$propietario) {
                $errors['id_propietario'] = 'El propietario seleccionado no existe en el sistema.';
            } elseif (isset($propietario['activo']) && (int)$propietario['activo'] !== 1) {
                $errors['id_propietario'] = 'No se pueden asociar mascotas a un propietario inactivo.';
            }
        }

        // 2. Validación de Nombre (obligatorio)
        $nombre = trim((string)($datos['nombre'] ?? ''));
        if ($nombre === '') {
            $errors['nombre'] = 'El nombre de la mascota es obligatorio.';
        } elseif (mb_strlen($nombre) < 2) {
            $errors['nombre'] = 'El nombre debe tener al menos 2 caracteres.';
        } elseif (mb_strlen($nombre) > 100) {
            $errors['nombre'] = 'El nombre no puede exceder los 100 caracteres.';
        }

        // 3. Validación de Especie (obligatorio)
        $especie = trim((string)($datos['especie'] ?? ''));
        if ($especie === '') {
            $errors['especie'] = 'La especie de la mascota es obligatoria.';
        } elseif (mb_strlen($especie) < 2) {
            $errors['especie'] = 'La especie debe tener al menos 2 caracteres.';
        } elseif (mb_strlen($especie) > 50) {
            $errors['especie'] = 'La especie no puede exceder los 50 caracteres.';
        }

        // 4. Validación de Raza (obligatorio según US-03)
        $raza = trim((string)($datos['raza'] ?? ''));
        if ($raza === '') {
            $errors['raza'] = 'La raza de la mascota es obligatoria (indica "Mestizo / Criollo" si se desconoce).';
        } elseif (mb_strlen($raza) > 50) {
            $errors['raza'] = 'La raza no puede exceder los 50 caracteres.';
        }

        // 5. Validación de Sexo (obligatorio: Macho / Hembra)
        $sexo = trim((string)($datos['sexo'] ?? ''));
        if ($sexo === '') {
            $errors['sexo'] = 'El sexo de la mascota es obligatorio.';
        } elseif (!in_array($sexo, ['Macho', 'Hembra'], true)) {
            $errors['sexo'] = 'El sexo debe ser "Macho" o "Hembra".';
        }

        // 6. Validación de Fecha de Nacimiento (opcional, no puede ser futura)
        $fechaNacimiento = trim((string)($datos['fecha_nacimiento'] ?? ''));
        if ($fechaNacimiento !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento)) {
                $errors['fecha_nacimiento'] = 'La fecha de nacimiento debe tener el formato AAAA-MM-DD.';
            } else {
                [$anio, $mes, $dia] = explode('-', $fechaNacimiento);
                if (!checkdate((int)$mes, (int)$dia, (int)$anio)) {
                    $errors['fecha_nacimiento'] = 'La fecha de nacimiento ingresada no es válida en el calendario.';
                } elseif ($fechaNacimiento > date('Y-m-d')) {
                    $errors['fecha_nacimiento'] = 'La fecha de nacimiento no puede ser posterior a la fecha actual.';
                }
            }
        } else {
            $fechaNacimiento = null;
        }

        // 7. Características Relevantes (opcional)
        $caracteristicas = trim((string)($datos['caracteristicas_relevantes'] ?? ''));
        $caracteristicas = ($caracteristicas !== '') ? $caracteristicas : null;

        // Si existen errores, abortar persistencia
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
                'id'      => null,
            ];
        }

        // Persistir en base de datos
        $idMascota = $this->mascotaRepo->create([
            'id_propietario'             => $idPropietario,
            'nombre'                     => $nombre,
            'especie'                    => $especie,
            'raza'                       => $raza,
            'sexo'                       => $sexo,
            'fecha_nacimiento'           => $fechaNacimiento,
            'caracteristicas_relevantes' => $caracteristicas,
            'activo'                     => 1,
        ]);

        return [
            'success' => true,
            'message' => 'Mascota registrada exitosamente y disponible para citas.',
            'errors'  => [],
            'id'      => $idMascota,
        ];
    }

    /**
     * Retorna todas las mascotas registradas con datos de su propietario.
     *
     * @param bool $soloActivos
     * @return array<array<string,mixed>>
     */
    public function listarMascotas(bool $soloActivos = true): array {
        return $this->mascotaRepo->listAll($soloActivos);
    }

    /**
     * Consulta una mascota por su identificador primario.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function obtenerMascotaPorId(int $id): ?array {
        return $this->mascotaRepo->findById($id);
    }

    /**
     * Retorna todas las mascotas de un propietario específico.
     *
     * @param int $idPropietario
     * @param bool $soloActivos
     * @return array<array<string,mixed>>
     */
    public function listarMascotasPorPropietario(int $idPropietario, bool $soloActivos = true): array {
        return $this->mascotaRepo->listByPropietario($idPropietario, $soloActivos);
    }
}
