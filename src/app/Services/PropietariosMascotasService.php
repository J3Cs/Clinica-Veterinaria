<?php
namespace App\Services;

use App\Repositories\PropietarioRepository;
use App\Repositories\MascotaRepository;

/**
 * PropietariosMascotasService — US-02, US-03, US-12 (T-076, T-077, T-090), ARQ-01, UNI-01, DAT-01, SEC-01, BAJA-01
 * Contiene todas las reglas de negocio para el alta, búsqueda, edición y política de baja
 * (baja lógica con dependencias vs eliminación física sin dependencias) de propietarios y mascotas.
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
    //  SECCIÓN: Reglas de Propietarios (US-02, US-12, UNI-01, BAJA-01)
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
        $limpio = str_replace(' ', '', $limpio);

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
     * Valida los campos de un propietario para registro o edición.
     *
     * @param array<string,mixed> $datos
     * @param int|null            $excludeId ID del propietario actual en caso de edición
     * @return array{errors: array<string,string>, clean: array{dui: string, nombre_completo: string, direccion: ?string, telefono: ?string, correo: ?string}}
     */
    private function validarCamposPropietario(array $datos, ?int $excludeId = null): array {
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
        } elseif ($this->propietarioRepo->isDuiRegistered($dui, $excludeId)) {
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

        return [
            'errors' => $errors,
            'clean'  => [
                'dui'             => $dui,
                'nombre_completo' => $nombreCompleto,
                'direccion'       => $direccion,
                'telefono'        => $telefono,
                'correo'          => $correo,
            ],
        ];
    }

    /**
     * Registra un nuevo propietario aplicando todas las reglas de negocio (T-018, UNI-01).
     *
     * @param array{nombre_completo?: string, dui?: string, direccion?: ?string, telefono?: ?string, correo?: ?string} $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int}
     */
    public function registrarPropietario(array $datos): array {
        $validacion = $this->validarCamposPropietario($datos, null);
        $errors = $validacion['errors'];
        $clean  = $validacion['clean'];

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
                'id'      => null,
            ];
        }

        $idPropietario = $this->propietarioRepo->create([
            'dui'             => $clean['dui'],
            'nombre_completo' => $clean['nombre_completo'],
            'direccion'       => $clean['direccion'],
            'telefono'        => $clean['telefono'],
            'correo'          => $clean['correo'],
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
     * Actualiza los datos de contacto o DUI de un propietario existente (T-076, T-077, UNI-01).
     *
     * @param int   $idPropietario
     * @param array $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int}
     */
    public function actualizarPropietario(int $idPropietario, array $datos): array {
        if ($idPropietario <= 0) {
            return [
                'success' => false,
                'message' => 'Identificador de propietario no válido.',
                'errors'  => [],
                'id'      => null,
            ];
        }

        $existente = $this->propietarioRepo->findById($idPropietario);
        if (!$existente) {
            return [
                'success' => false,
                'message' => 'El propietario indicado no existe en el sistema.',
                'errors'  => [],
                'id'      => null,
            ];
        }

        $validacion = $this->validarCamposPropietario($datos, $idPropietario);
        $errors = $validacion['errors'];
        $clean  = $validacion['clean'];

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
                'id'      => $idPropietario,
            ];
        }

        $this->propietarioRepo->update($idPropietario, $clean);

        return [
            'success' => true,
            'message' => 'Datos del propietario actualizados exitosamente.',
            'errors'  => [],
            'id'      => $idPropietario,
        ];
    }

    /**
     * Aplica la política definitiva de baja para un propietario (T-077, BAJA-01):
     * - Si existen mascotas asociadas o relaciones clínicas/citas: realiza BAJA LÓGICA (activo = 0)
     *   del propietario y desactiva sus mascotas asociadas, preservando todo el historial y citas.
     * - Si NO existen dependencias: realiza ELIMINACIÓN FÍSICA (DELETE).
     *
     * @param int $idPropietario
     * @return array{success: bool, tipo_baja: ?string, message: string, relaciones?: array<string,mixed>}
     */
    public function darDeBajaPropietario(int $idPropietario): array {
        if ($idPropietario <= 0) {
            return [
                'success'   => false,
                'tipo_baja' => null,
                'message'   => 'Identificador de propietario no válido.',
            ];
        }

        $propietario = $this->propietarioRepo->findById($idPropietario);
        if (!$propietario) {
            return [
                'success'   => false,
                'tipo_baja' => null,
                'message'   => 'El propietario indicado no existe en el sistema.',
            ];
        }

        $relaciones = $this->propietarioRepo->obtenerRelaciones($idPropietario);
        $mascotasAsociadas = $this->mascotaRepo->listByPropietario($idPropietario, false);
        $tieneMascotas = count($mascotasAsociadas) > 0 || (int)($relaciones['mascotas_total'] ?? 0) > 0;
        $tieneDependencias = $tieneMascotas || !empty($relaciones['tiene_dependencias']);

        if ($tieneDependencias) {
            $citasProgramadasPrevias = (int) ($relaciones['citas_programadas'] ?? 0);
            $this->propietarioRepo->desactivar($idPropietario);
            $this->mascotaRepo->desactivarPorPropietario($idPropietario);
            $relacionesActualizadas = $this->propietarioRepo->obtenerRelaciones($idPropietario);

            $mensaje = $citasProgramadasPrevias > 0
                ? sprintf(
                    'El propietario «%s» fue desactivado junto con sus mascotas asociadas y se cancelaron automáticamente %d cita(s) programada(s). Su historial clínico se conserva en el sistema.',
                    (string) $propietario['nombre_completo'],
                    $citasProgramadasPrevias
                )
                : sprintf(
                    'El propietario «%s» fue desactivado junto con sus mascotas asociadas. Su historial de citas y consultas se conserva en el sistema.',
                    (string) $propietario['nombre_completo']
                );

            return [
                'success'          => true,
                'tipo_baja'        => 'logica',
                'citas_canceladas' => $citasProgramadasPrevias,
                'message'          => $mensaje,
                'relaciones'       => $relacionesActualizadas,
            ];
        }

        $this->propietarioRepo->delete($idPropietario);

        return [
            'success'    => true,
            'tipo_baja'  => 'fisica',
            'message'    => sprintf(
                'El registro del propietario «%s» fue eliminado correctamente.',
                (string) $propietario['nombre_completo']
            ),
            'relaciones' => $relaciones,
        ];
    }

    /**
     * Alias de darDeBajaPropietario.
     */
    public function eliminarPropietario(int $idPropietario): array {
        return $this->darDeBajaPropietario($idPropietario);
    }

    /**
     * Reactiva a un propietario previamente desactivado.
     *
     * @param int $idPropietario
     * @return array{success: bool, message: string}
     */
    public function reactivarPropietario(int $idPropietario): array {
        if ($idPropietario <= 0) {
            return ['success' => false, 'message' => 'Identificador de propietario no válido.'];
        }

        $propietario = $this->propietarioRepo->findById($idPropietario);
        if (!$propietario) {
            return ['success' => false, 'message' => 'El propietario indicado no existe.'];
        }

        $this->propietarioRepo->activar($idPropietario);

        return [
            'success' => true,
            'message' => sprintf('Propietario «%s» reactivado exitosamente.', (string) $propietario['nombre_completo']),
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
     * Consulta un propietario junto con sus mascotas y el resumen de sus relaciones clínicas (T-076).
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function obtenerPropietarioConRelaciones(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        $propietario = $this->propietarioRepo->findById($id);
        if (!$propietario) {
            return null;
        }

        $propietario['mascotas']   = $this->mascotaRepo->listByPropietario($id, false);
        $propietario['relaciones'] = $this->propietarioRepo->obtenerRelaciones($id);
        return $propietario;
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
     * Lista todos los propietarios (por defecto excluye inactivos — T-090).
     *
     * @param bool $soloActivos
     * @return array<array<string,mixed>>
     */
    public function listarPropietarios(bool $soloActivos = true): array {
        return $this->propietarioRepo->listAll($soloActivos);
    }

    /**
     * Busca propietarios por nombre, DUI, teléfono o correo (T-076, T-090).
     *
     * @param string|null $termino
     * @param bool        $soloActivos
     * @return array<array<string,mixed>>
     */
    public function buscarPropietarios(?string $termino = null, bool $soloActivos = true): array {
        $limpio = trim((string) $termino);
        if ($limpio === '') {
            return $this->propietarioRepo->listAll($soloActivos);
        }
        if (preg_match('/^\d{9}$/', str_replace(' ', '', $limpio))) {
            $limpio = $this->normalizarDui($limpio);
        }
        return $this->propietarioRepo->search($limpio, $soloActivos);
    }

    // ────────────────────────────────────────────────────────────────── //
    //  SECCIÓN: Reglas de Mascotas (US-03, US-12, DAT-01, BAJA-01)
    // ────────────────────────────────────────────────────────────────── //

    /**
     * Valida los campos de una mascota para registro o edición.
     *
     * @param array<string,mixed> $datos
     * @return array{errors: array<string,string>, clean: array{id_propietario: int, nombre: string, especie: string, raza: string, sexo: string, fecha_nacimiento: ?string, caracteristicas_relevantes: ?string}}
     */
    private function validarCamposMascota(array $datos): array {
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

        return [
            'errors' => $errors,
            'clean'  => [
                'id_propietario'             => $idPropietario,
                'nombre'                     => $nombre,
                'especie'                    => $especie,
                'raza'                       => $raza,
                'sexo'                       => $sexo,
                'fecha_nacimiento'           => $fechaNacimiento,
                'caracteristicas_relevantes' => $caracteristicas,
            ],
        ];
    }

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
        $validacion = $this->validarCamposMascota($datos);
        $errors = $validacion['errors'];
        $clean  = $validacion['clean'];

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
                'id'      => null,
            ];
        }

        $idMascota = $this->mascotaRepo->create([
            'id_propietario'             => $clean['id_propietario'],
            'nombre'                     => $clean['nombre'],
            'especie'                    => $clean['especie'],
            'raza'                       => $clean['raza'],
            'sexo'                       => $clean['sexo'],
            'fecha_nacimiento'           => $clean['fecha_nacimiento'],
            'caracteristicas_relevantes' => $clean['caracteristicas_relevantes'],
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
     * Actualiza los datos de una mascota existente y su propietario responsable (T-076, T-077, DAT-01).
     *
     * @param int   $idMascota
     * @param array $datos
     * @return array{success: bool, message: string, errors: array<string,string>, id: ?int}
     */
    public function actualizarMascota(int $idMascota, array $datos): array {
        if ($idMascota <= 0) {
            return [
                'success' => false,
                'message' => 'Identificador de mascota no válido.',
                'errors'  => [],
                'id'      => null,
            ];
        }

        $existente = $this->mascotaRepo->findById($idMascota);
        if (!$existente) {
            return [
                'success' => false,
                'message' => 'La mascota indicada no existe en el sistema.',
                'errors'  => [],
                'id'      => null,
            ];
        }

        $validacion = $this->validarCamposMascota($datos);
        $errors = $validacion['errors'];
        $clean  = $validacion['clean'];

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
                'id'      => $idMascota,
            ];
        }

        $this->mascotaRepo->update($idMascota, $clean);

        return [
            'success' => true,
            'message' => 'Expediente de la mascota actualizado exitosamente.',
            'errors'  => [],
            'id'      => $idMascota,
        ];
    }

    /**
     * Aplica la política definitiva de baja para una mascota (T-077, BAJA-01):
     * - Si posee citas, consultas veterinarias o antecedentes en historial_mascota:
     *   realiza BAJA LÓGICA (activo = 0), preservando intactos los datos clínicos.
     * - Si NO posee ninguna dependencia clínica: realiza ELIMINACIÓN FÍSICA (DELETE).
     *
     * @param int $idMascota
     * @return array{success: bool, tipo_baja: ?string, message: string, relaciones?: array<string,mixed>}
     */
    public function darDeBajaMascota(int $idMascota): array {
        if ($idMascota <= 0) {
            return [
                'success'   => false,
                'tipo_baja' => null,
                'message'   => 'Identificador de mascota no válido.',
            ];
        }

        $mascota = $this->mascotaRepo->findById($idMascota);
        if (!$mascota) {
            return [
                'success'   => false,
                'tipo_baja' => null,
                'message'   => 'La mascota indicada no existe en el sistema.',
            ];
        }

        $relaciones = $this->mascotaRepo->obtenerRelaciones($idMascota);
        $tieneDependencias = !empty($relaciones['tiene_dependencias'])
            || (int)($mascota['citas_total'] ?? 0) > 0
            || (int)($mascota['consultas_total'] ?? 0) > 0
            || (int)($mascota['historial_total'] ?? 0) > 0;

        if ($tieneDependencias) {
            $citasProgramadasPrevias = (int) ($relaciones['citas_programadas'] ?? ($mascota['citas_programadas'] ?? 0));
            $this->mascotaRepo->desactivar($idMascota);
            $relacionesActualizadas = $this->mascotaRepo->obtenerRelaciones($idMascota);

            $mensaje = $citasProgramadasPrevias > 0
                ? sprintf(
                    'La mascota «%s» fue desactivada y se cancelaron automáticamente %d cita(s) programada(s). Su historial clínico se conserva en el sistema.',
                    (string) $mascota['nombre'],
                    $citasProgramadasPrevias
                )
                : sprintf(
                    'La mascota «%s» fue desactivada. Su historial de citas y consultas clínicas se conserva en el sistema.',
                    (string) $mascota['nombre']
                );

            return [
                'success'          => true,
                'tipo_baja'        => 'logica',
                'citas_canceladas' => $citasProgramadasPrevias,
                'message'          => $mensaje,
                'relaciones'       => $relacionesActualizadas,
            ];
        }

        $this->mascotaRepo->delete($idMascota);

        return [
            'success'    => true,
            'tipo_baja'  => 'fisica',
            'message'    => sprintf(
                'El registro de la mascota «%s» fue eliminado correctamente.',
                (string) $mascota['nombre']
            ),
            'relaciones' => $relaciones,
        ];
    }

    /**
     * Alias de darDeBajaMascota.
     */
    public function eliminarMascota(int $idMascota): array {
        return $this->darDeBajaMascota($idMascota);
    }

    /**
     * Reactiva una mascota previamente desactivada (siempre que su propietario esté activo — DAT-01).
     *
     * @param int $idMascota
     * @return array{success: bool, message: string}
     */
    public function reactivarMascota(int $idMascota): array {
        if ($idMascota <= 0) {
            return ['success' => false, 'message' => 'Identificador de mascota no válido.'];
        }

        $mascota = $this->mascotaRepo->findById($idMascota);
        if (!$mascota) {
            return ['success' => false, 'message' => 'La mascota indicada no existe.'];
        }

        $propietario = $this->propietarioRepo->findById((int) $mascota['id_propietario']);
        if (!$propietario || (int)($propietario['activo'] ?? 1) !== 1) {
            return [
                'success' => false,
                'message' => 'No se puede reactivar la mascota porque su propietario se encuentra inactivo. Reactiva primero al propietario.',
            ];
        }

        $this->mascotaRepo->activar($idMascota);

        return [
            'success' => true,
            'message' => sprintf('Mascota «%s» reactivada exitosamente.', (string) $mascota['nombre']),
        ];
    }

    /**
     * Retorna todas las mascotas registradas con datos de su propietario (T-090).
     *
     * @param bool $soloActivos
     * @return array<array<string,mixed>>
     */
    public function listarMascotas(bool $soloActivos = true): array {
        return $this->mascotaRepo->listAll($soloActivos);
    }

    /**
     * Busca mascotas por nombre, especie, raza, propietario o DUI (T-076, T-090).
     *
     * @param string|null $termino
     * @param bool        $soloActivos
     * @param int|null    $idPropietario
     * @return array<array<string,mixed>>
     */
    public function buscarMascotas(?string $termino = null, bool $soloActivos = true, ?int $idPropietario = null): array {
        $limpio = trim((string) $termino);
        if ($limpio === '' && $idPropietario === null) {
            return $this->mascotaRepo->listAll($soloActivos);
        }
        if ($limpio !== '' && preg_match('/^\d{9}$/', str_replace(' ', '', $limpio))) {
            $limpio = $this->normalizarDui($limpio);
        }
        return $this->mascotaRepo->search($limpio, $soloActivos, $idPropietario);
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
     * Consulta una mascota con los datos de su propietario y todas sus relaciones clínicas (T-076).
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function obtenerMascotaConRelaciones(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        return $this->mascotaRepo->findConRelaciones($id);
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
