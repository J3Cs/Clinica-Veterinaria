<?php
namespace App\Services;

use App\Repositories\VeterinarioRepository;
use App\Repositories\UsuarioRepository;

/**
 * VeterinariosService — US-12 (T-077), ARQ-01, ACC-01, SEC-01, BAJA-01
 * Lógica de negocio para el mantenimiento y política de baja de veterinarios:
 * - Si el veterinario posee citas o consultas asociadas: desactiva su cuenta de usuario
 *   (`usuarios.activo = 0`), impidiendo que pueda iniciar sesión y conservando los datos clínicos.
 * - Si NO posee dependencias clínicas: elimina el registro.
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
     * Actualiza los datos profesionales y de contacto de un veterinario (T-077, T-078).
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
        } elseif ($this->usuarioRepo->isCorreoRegistered($correo, $idUsuario)) {
            $errors['correo'] = 'El correo electrónico ya está en uso por otra cuenta.';
        }

        $usuario = trim((string)($datos['usuario'] ?? $actual['usuario'] ?? ''));
        if ($usuario === '' || mb_strlen($usuario) < 3) {
            $errors['usuario'] = 'El nombre de usuario debe tener al menos 3 caracteres.';
        } elseif ($this->usuarioRepo->isUsuarioRegistered($usuario, $idUsuario)) {
            $errors['usuario'] = 'El nombre de usuario ya está en uso por otra cuenta.';
        }

        $especialidad = trim((string)($datos['especialidad'] ?? ''));
        $especialidad = ($especialidad !== '') ? $especialidad : null;

        $telefono = trim((string)($datos['telefono'] ?? ''));
        if ($telefono !== '' && mb_strlen($telefono) > 20) {
            $errors['telefono'] = 'El teléfono no puede exceder los 20 caracteres.';
        }
        $telefono = ($telefono !== '') ? $telefono : null;

        $licencia = trim((string)($datos['licencia_medica'] ?? ''));
        if ($licencia !== '' && mb_strlen($licencia) > 50) {
            $errors['licencia_medica'] = 'La licencia médica no puede exceder los 50 caracteres.';
        }
        $licencia = ($licencia !== '') ? $licencia : null;

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Por favor corrige los errores señalados.',
                'errors'  => $errors,
            ];
        }

        $this->vetRepo->update($idVeterinario, $idUsuario, [
            'nombre'          => $nombre,
            'correo'          => $correo,
            'usuario'         => $usuario,
            'especialidad'    => $especialidad,
            'telefono'        => $telefono,
            'licencia_medica' => $licencia,
        ]);

        return [
            'success' => true,
            'message' => 'Datos del veterinario actualizados exitosamente.',
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

        $this->vetRepo->deleteFisico($idVeterinario, $idUsuario);

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
