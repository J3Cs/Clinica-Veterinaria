<?php
namespace App\Services;

use App\Repositories\VeterinarioRepository;
use App\Repositories\UsuarioRepository;

/**
 * VeterinariosService — US-09 (T-061), US-12 (T-077), ARQ-01, ACC-01, SEC-01, BAJA-01
 * Lógica de negocio para la gestión de personal médico veterinario y sus cuentas de usuario:
 * - Alta transaccional de cuenta de usuario (`rol = 'veterinario'`) y ficha profesional (`veterinarios`).
 * - Validación de datos obligatorios, formato de credenciales y unicidad (`usuario`, `correo`, `licencia_medica`).
 * - Edición de perfil profesional y cuenta con cambio opcional de contraseña (solo si se ingresa una nueva).
 * - Política de baja controlada (`BAJA-01`):
 *   * Si el veterinario posee citas o consultas asociadas: aplica baja lógica (`usuarios.activo = 0`),
 *     cancela sus citas programadas pendientes, impide iniciar sesión y conserva el historial clínico.
 *   * Si NO posee dependencias clínicas: elimina físicamente la ficha y la cuenta en transacción.
 */
class VeterinariosService {

    private VeterinarioRepository $vetRepo;
    private UsuarioRepository $usuarioRepo;

    public function __construct(
        ?VeterinarioRepository $vetRepo = null,
        ?UsuarioRepository $usuarioRepo = null
    ) {
        $this->vetRepo     = $vetRepo ?? new VeterinarioRepository();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepository();
    }

    /**
     * Lista los veterinarios registrados.
     *
     * @param bool $soloActivos
     * @return array<array<string,mixed>>
     */
    public function listarVeterinarios(bool $soloActivos = false): array {
        return $this->vetRepo->listAll($soloActivos);
    }

    /**
     * Busca veterinarios por término libre y filtra por estado ('activos', 'inactivos', 'todos') (T-061, T-063).
     *
     * @param string|null $termino
     * @param string      $estado  'activos' | 'inactivos' | 'todos'
     * @return array<array<string,mixed>>
     */
    public function buscarVeterinarios(?string $termino = null, string $estado = 'activos'): array {
        $soloActivos = ($estado === 'activos');
        $resultados  = $this->vetRepo->search($termino, $soloActivos);

        if ($estado === 'inactivos') {
            return array_values(array_filter(
                $resultados,
                static fn(array $v): bool => (int) ($v['activo'] ?? 1) === 0
            ));
        }

        return $resultados;
    }

    /**
     * Obtiene un veterinario por su ID junto con el resumen de sus relaciones clínicas.
     *
     * @param int $idVeterinario
     * @return array<string,mixed>|null
     */
    public function obtenerVeterinarioPorId(int $idVeterinario): ?array {
        if ($idVeterinario <= 0) {
            return null;
        }
        $vet = $this->vetRepo->findById($idVeterinario);
        if (!$vet) {
            return null;
        }
        $vet['relaciones'] = $this->vetRepo->obtenerRelaciones($idVeterinario);
        return $vet;
    }

    /**
     * Registra un nuevo médico veterinario y su cuenta de usuario inicial en transacción (T-060, T-061).
     *
     * @param array<string,mixed> $datos
     * @return array{
     *     success: bool,
     *     message: string,
     *     errors: array<string,string>,
     *     id_veterinario?: int,
     *     id_usuario?: int
     * }
     */
    public function registrarVeterinario(array $datos): array {
        $errors = [];

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) < 3) {
            $errors['nombre'] = 'El nombre completo del veterinario debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($nombre) > 100) {
            $errors['nombre'] = 'El nombre no puede exceder los 100 caracteres.';
        }

        $correo = trim((string) ($datos['correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errors['correo'] = 'El correo electrónico es obligatorio y debe tener formato válido.';
        } elseif (mb_strlen($correo) > 150) {
            $errors['correo'] = 'El correo electrónico no puede exceder los 150 caracteres.';
        } elseif ($this->usuarioRepo->isCorreoRegistered($correo)) {
            $errors['correo'] = 'El correo electrónico ya está registrado en otra cuenta.';
        }

        $usuario = trim((string) ($datos['usuario'] ?? ''));
        if ($usuario === '' || mb_strlen($usuario) < 3) {
            $errors['usuario'] = 'El nombre de usuario debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($usuario) > 50) {
            $errors['usuario'] = 'El nombre de usuario no puede exceder los 50 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $usuario)) {
            $errors['usuario'] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        } elseif ($this->usuarioRepo->isUsuarioRegistered($usuario)) {
            $errors['usuario'] = 'El nombre de usuario ya está en uso por otra cuenta.';
        }

        $contrasena = (string) ($datos['contrasena'] ?? '');
        if ($contrasena === '' || mb_strlen($contrasena) < 8) {
            $errors['contrasena'] = 'La contraseña inicial es obligatoria y debe tener al menos 8 caracteres.';
        } elseif (mb_strlen($contrasena) > 128) {
            $errors['contrasena'] = 'La contraseña no puede exceder los 128 caracteres.';
        }

        if (array_key_exists('confirmar_contrasena', $datos)) {
            $confirmar = (string) ($datos['confirmar_contrasena'] ?? '');
            if ($contrasena !== '' && $confirmar !== $contrasena) {
                $errors['confirmar_contrasena'] = 'La confirmación de contraseña no coincide.';
            }
        }

        $especialidad = trim((string) ($datos['especialidad'] ?? ''));
        if ($especialidad !== '' && mb_strlen($especialidad) > 100) {
            $errors['especialidad'] = 'La especialidad no puede exceder los 100 caracteres.';
        }
        $especialidad = ($especialidad !== '') ? $especialidad : null;

        $telefono = trim((string) ($datos['telefono'] ?? ''));
        if ($telefono !== '') {
            if (mb_strlen($telefono) > 20 || !preg_match('/^[0-9+\-\s()]{7,20}$/', $telefono)) {
                $errors['telefono'] = 'El teléfono debe tener entre 7 y 20 caracteres válidos (ej. 7890-1234).';
            }
        }
        $telefono = ($telefono !== '') ? $telefono : null;

        $licencia = trim((string) ($datos['licencia_medica'] ?? ''));
        if ($licencia !== '') {
            if (mb_strlen($licencia) > 50) {
                $errors['licencia_medica'] = 'La licencia médica no puede exceder los 50 caracteres.';
            } elseif ($this->vetRepo->isLicenciaRegistered($licencia)) {
                $errors['licencia_medica'] = 'La licencia médica indicada ya está asignada a otro veterinario.';
            }
        }
        $licencia = ($licencia !== '') ? $licencia : null;

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados en el formulario.',
                'errors'  => $errors,
            ];
        }

        $hashContrasena = password_hash($contrasena, PASSWORD_BCRYPT);

        try {
            $creado = $this->vetRepo->createConUsuario(
                [
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'usuario'    => $usuario,
                    'contrasena' => $hashContrasena,
                    'rol'        => 'veterinario',
                    'activo'     => 1,
                ],
                [
                    'especialidad'    => $especialidad,
                    'telefono'        => $telefono,
                    'licencia_medica' => $licencia,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[VeterinariosService::registrarVeterinario] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'No se pudo completar el registro del veterinario por un conflicto de datos. Verifica que el usuario, correo y licencia no estén duplicados.',
                'errors'  => [],
            ];
        }

        return [
            'success'        => true,
            'message'        => sprintf('Veterinario «%s» y su cuenta de acceso fueron registrados exitosamente.', $nombre),
            'errors'         => [],
            'id_veterinario' => $creado['id_veterinario'],
            'id_usuario'     => $creado['id_usuario'],
        ];
    }

    /**
     * Actualiza los datos profesionales y de contacto de un veterinario, cambiando la contraseña
     * únicamente cuando se solicita explícitamente (T-061, T-063, T-077, T-078).
     *
     * @param int   $idVeterinario
     * @param array $datos
     * @return array{success: bool, message: string, errors: array<string,string>}
     */
    public function actualizarVeterinario(int $idVeterinario, array $datos): array {
        if ($idVeterinario <= 0) {
            return [
                'success' => false,
                'message' => 'Identificador de veterinario no válido.',
                'errors'  => [],
            ];
        }

        $actual = $this->vetRepo->findById($idVeterinario);
        if (!$actual) {
            return [
                'success' => false,
                'message' => 'El veterinario indicado no existe.',
                'errors'  => [],
            ];
        }

        $idUsuario = (int) $actual['id_usuario'];
        $errors = [];

        $nombre = trim((string)($datos['nombre'] ?? $actual['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) < 3) {
            $errors['nombre'] = 'El nombre del veterinario debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($nombre) > 100) {
            $errors['nombre'] = 'El nombre no puede exceder los 100 caracteres.';
        }

        $correo = trim((string)($datos['correo'] ?? $actual['correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errors['correo'] = 'El correo electrónico es obligatorio y debe tener formato válido.';
        } elseif (mb_strlen($correo) > 150) {
            $errors['correo'] = 'El correo electrónico no puede exceder los 150 caracteres.';
        } elseif ($this->usuarioRepo->isCorreoRegistered($correo, $idUsuario)) {
            $errors['correo'] = 'El correo electrónico ya está en uso por otra cuenta.';
        }

        $usuario = trim((string)($datos['usuario'] ?? $actual['usuario'] ?? ''));
        if ($usuario === '' || mb_strlen($usuario) < 3) {
            $errors['usuario'] = 'El nombre de usuario debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($usuario) > 50) {
            $errors['usuario'] = 'El nombre de usuario no puede exceder los 50 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $usuario)) {
            $errors['usuario'] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        } elseif ($this->usuarioRepo->isUsuarioRegistered($usuario, $idUsuario)) {
            $errors['usuario'] = 'El nombre de usuario ya está en uso por otra cuenta.';
        }

        $especialidad = trim((string)($datos['especialidad'] ?? ''));
        if ($especialidad !== '' && mb_strlen($especialidad) > 100) {
            $errors['especialidad'] = 'La especialidad no puede exceder los 100 caracteres.';
        }
        $especialidad = ($especialidad !== '') ? $especialidad : null;

        $telefono = trim((string)($datos['telefono'] ?? ''));
        if ($telefono !== '') {
            if (mb_strlen($telefono) > 20 || !preg_match('/^[0-9+\-\s()]{7,20}$/', $telefono)) {
                $errors['telefono'] = 'El teléfono debe tener entre 7 y 20 caracteres válidos.';
            }
        }
        $telefono = ($telefono !== '') ? $telefono : null;

        $licencia = trim((string)($datos['licencia_medica'] ?? ''));
        if ($licencia !== '') {
            if (mb_strlen($licencia) > 50) {
                $errors['licencia_medica'] = 'La licencia médica no puede exceder los 50 caracteres.';
            } elseif ($this->vetRepo->isLicenciaRegistered($licencia, $idVeterinario)) {
                $errors['licencia_medica'] = 'La licencia médica indicada ya está asignada a otro veterinario.';
            }
        }
        $licencia = ($licencia !== '') ? $licencia : null;

        // La contraseña solo se valida y actualiza cuando el administrador ingresa una nueva (T-063)
        $nuevaContrasena = (string) ($datos['contrasena'] ?? '');
        $confirmar       = (string) ($datos['confirmar_contrasena'] ?? '');
        $hashContrasena  = null;

        if ($nuevaContrasena !== '' || $confirmar !== '') {
            if (mb_strlen($nuevaContrasena) < 8) {
                $errors['contrasena'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } elseif (mb_strlen($nuevaContrasena) > 128) {
                $errors['contrasena'] = 'La nueva contraseña no puede exceder los 128 caracteres.';
            } elseif (array_key_exists('confirmar_contrasena', $datos) && $nuevaContrasena !== $confirmar) {
                $errors['confirmar_contrasena'] = 'La confirmación de la nueva contraseña no coincide.';
            } else {
                $hashContrasena = password_hash($nuevaContrasena, PASSWORD_BCRYPT);
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados.',
                'errors'  => $errors,
            ];
        }

        try {
            $this->vetRepo->update($idVeterinario, $idUsuario, [
                'nombre'          => $nombre,
                'correo'          => $correo,
                'usuario'         => $usuario,
                'contrasena'      => $hashContrasena,
                'especialidad'    => $especialidad,
                'telefono'        => $telefono,
                'licencia_medica' => $licencia,
            ]);
        } catch (\Throwable $e) {
            error_log('[VeterinariosService::actualizarVeterinario] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'No se pudieron guardar los cambios por un conflicto de unicidad en la base de datos.',
                'errors'  => [],
            ];
        }

        return [
            'success' => true,
            'message' => $hashContrasena !== null
                ? 'Datos del veterinario y credenciales de acceso actualizados exitosamente.'
                : 'Datos del veterinario actualizados exitosamente.',
            'errors'  => [],
        ];
    }

    /**
     * Aplica la política de baja para un veterinario (T-077, BAJA-01):
     * - Si existen citas o consultas asociadas: desactiva su usuario (`usuarios.activo = 0`).
     * - Si NO existen dependencias clínicas: elimina el registro.
     *
     * @param int $idVeterinario
     * @return array{success: bool, tipo_baja: ?string, message: string, relaciones?: array<string,mixed>}
     */
    public function darDeBajaVeterinario(int $idVeterinario): array {
        if ($idVeterinario <= 0) {
            return [
                'success'   => false,
                'tipo_baja' => null,
                'message'   => 'Identificador de veterinario no válido.',
            ];
        }

        $vet = $this->vetRepo->findById($idVeterinario);
        if (!$vet) {
            return [
                'success'   => false,
                'tipo_baja' => null,
                'message'   => 'El veterinario indicado no existe en el sistema.',
            ];
        }

        $idUsuario  = (int) $vet['id_usuario'];
        $relaciones = $this->vetRepo->obtenerRelaciones($idVeterinario);
        $tieneDependencias = !empty($relaciones['tiene_dependencias'])
            || (int)($vet['citas_total'] ?? 0) > 0
            || (int)($vet['consultas_total'] ?? 0) > 0;

        if ($tieneDependencias) {
            $citasProgramadasPrevias = (int) ($relaciones['citas_programadas'] ?? 0);
            $this->vetRepo->cancelarCitasProgramadas($idVeterinario);
            $this->vetRepo->desactivar($idUsuario);
            $this->usuarioRepo->desactivar($idUsuario);
            $relacionesActualizadas = $this->vetRepo->obtenerRelaciones($idVeterinario);

            $mensaje = $citasProgramadasPrevias > 0
                ? sprintf(
                    'La cuenta del veterinario «%s» fue desactivada y se cancelaron automáticamente %d cita(s) programada(s). Su historial clínico se conserva en el sistema.',
                    (string) $vet['nombre'],
                    $citasProgramadasPrevias
                )
                : sprintf(
                    'La cuenta del veterinario «%s» fue desactivada y su historial de citas y consultas se conserva en el sistema.',
                    (string) $vet['nombre']
                );

            return [
                'success'          => true,
                'tipo_baja'        => 'logica',
                'citas_canceladas' => $citasProgramadasPrevias,
                'message'          => $mensaje,
                'relaciones'       => $relacionesActualizadas,
            ];
        }

        try {
            $this->vetRepo->deleteFisico($idVeterinario, $idUsuario);
        } catch (\Throwable $e) {
            error_log('[VeterinariosService::darDeBajaVeterinario] FK restrict fallback a baja logica: ' . $e->getMessage());
            $this->vetRepo->cancelarCitasProgramadas($idVeterinario);
            $this->vetRepo->desactivar($idUsuario);
            $this->usuarioRepo->desactivar($idUsuario);

            return [
                'success'    => true,
                'tipo_baja'  => 'logica',
                'message'    => sprintf(
                    'La cuenta del veterinario «%s» fue desactivada para preservar su historial clínico asociado.',
                    (string) $vet['nombre']
                ),
                'relaciones' => $this->vetRepo->obtenerRelaciones($idVeterinario),
            ];
        }

        return [
            'success'    => true,
            'tipo_baja'  => 'fisica',
            'message'    => sprintf(
                'El registro del veterinario «%s» fue eliminado correctamente.',
                (string) $vet['nombre']
            ),
            'relaciones' => $relaciones,
        ];
    }

    /**
     * Reactiva la cuenta de un veterinario previamente desactivado.
     *
     * @param int $idVeterinario
     * @return array{success: bool, message: string}
     */
    public function reactivarVeterinario(int $idVeterinario): array {
        if ($idVeterinario <= 0) {
            return ['success' => false, 'message' => 'Identificador de veterinario no válido.'];
        }

        $vet = $this->vetRepo->findById($idVeterinario);
        if (!$vet) {
            return ['success' => false, 'message' => 'El veterinario indicado no existe.'];
        }

        $idUsuario = (int) $vet['id_usuario'];
        $this->vetRepo->activar($idUsuario);
        $this->usuarioRepo->activar($idUsuario);

        return [
            'success' => true,
            'message' => sprintf('Veterinario «%s» reactivado exitosamente.', (string) $vet['nombre']),
        ];
    }
}
