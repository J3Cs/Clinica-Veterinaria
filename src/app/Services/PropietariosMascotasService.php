<?php
namespace App\Services;

use App\Repositories\PropietarioRepository;

/**
 * PropietariosMascotasService — T-018, ARQ-01, UNI-01, SEC-01
 * Contiene todas las reglas de negocio para la gestión de propietarios y sus mascotas.
 */
class PropietariosMascotasService {

    private PropietarioRepository $propietarioRepo;

    public function __construct(?PropietarioRepository $propietarioRepo = null) {
        $this->propietarioRepo = $propietarioRepo ?? new PropietarioRepository();
    }

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
}

