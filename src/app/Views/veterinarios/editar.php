<?php
/**
 * Formulario de Edición y Detalle de Médico Veterinario
 * US-09 (T-063, T-064), US-12 (T-077, T-078) — Cumple UI-01, BAJA-01, ACC-01, SEC-01
 *
 * @var array<string,mixed>   $veterinario
 * @var string                $csrfToken
 * @var string|null           $flashError
 * @var string|null           $flashSuccess
 * @var array<string,string>  $fieldErrors
 * @var array<string,string>  $oldData
 * @var string                $nombreUsuario
 * @var string                $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$idVeterinario    = (int) ($veterinario['id_veterinario'] ?? 0);
$idUsuario        = (int) ($veterinario['id_usuario'] ?? 0);
$activo           = (int) ($veterinario['activo'] ?? 1) === 1;
$relaciones       = $veterinario['relaciones'] ?? [];
$citasTotal       = (int) ($relaciones['citas_total'] ?? $veterinario['citas_total'] ?? 0);
$citasProgramadas = (int) ($relaciones['citas_programadas'] ?? $veterinario['citas_programadas'] ?? 0);
$consultasTotal   = (int) ($relaciones['consultas_total'] ?? $veterinario['consultas_total'] ?? 0);
$horariosTotal    = (int) ($relaciones['horarios_total'] ?? $veterinario['horarios_total'] ?? 0);
$tieneDep         = !empty($relaciones['tiene_dependencias']) || $citasTotal > 0 || $consultasTotal > 0;

$valNombre       = $oldData['nombre']          ?? $veterinario['nombre']          ?? '';
$valCorreo       = $oldData['correo']          ?? $veterinario['correo']          ?? '';
$valUsuario      = $oldData['usuario']         ?? $veterinario['usuario']         ?? '';
$valEspecialidad = $oldData['especialidad']    ?? $veterinario['especialidad']    ?? '';
$valTelefono     = $oldData['telefono']        ?? $veterinario['telefono']        ?? '';
$valLicencia     = $oldData['licencia_medica'] ?? $veterinario['licencia_medica'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Veterinario — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'veterinarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>
                    Ficha del Veterinario: <?= $e($veterinario['nombre']) ?>
                    <span class="badge <?= $activo ? 'badge-vet' : 'badge-inactivo' ?>">
                        <?= $activo ? 'Activo' : 'Inactivo' ?>
                    </span>
                </h2>
                <p class="text-muted">Actualiza sus datos profesionales, gestiona sus credenciales o administra el estado de su cuenta.</p>
            </div>
            <div class="form-actions" style="margin: 0;">
                <a href="/horarios?id_veterinario=<?= $idVeterinario ?>" class="btn btn-sm btn-outline-dark">Ver Horarios (<?= $horariosTotal ?>)</a>
                <a href="/veterinarios" class="btn btn-sm btn-outline-dark">&larr; Volver al directorio</a>
            </div>
        </div>

        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error" role="alert" id="server-error-msg">
                    <span><?= $e($flashError) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success" role="status" id="server-success-msg">
                    <span><?= $e($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
                <span id="js-error-text"></span>
            </div>
        </div>

        <!-- ── Resumen de Relaciones Clínicas y de Agenda (T-060, T-063) ── -->
        <div class="detail-grid">
            <div class="detail-stat-card">
                <div class="detail-stat-label">Citas Registradas</div>
                <div class="detail-stat-value"><?= $citasTotal ?> <small class="text-muted">(<?= $citasProgramadas ?> programadas)</small></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Consultas Atendidas</div>
                <div class="detail-stat-value"><?= $consultasTotal ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Franjas Horarias</div>
                <div class="detail-stat-value"><?= $horariosTotal ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Cuenta Vinculada</div>
                <div class="detail-stat-value" style="font-size: 1.05rem;">@<?= $e($veterinario['usuario']) ?> <small class="text-muted">(ID #<?= $idUsuario ?>)</small></div>
            </div>
        </div>

        <!-- ── Formulario de Edición de Ficha y Cuenta (T-063) ── -->
        <form id="form-veterinario" method="POST" action="/veterinarios/editar" data-modo="editar" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_veterinario" value="<?= $idVeterinario ?>">

            <h3 class="clinical-section-title" style="margin-top: 0.5rem;">1. Datos Profesionales y de Cuenta</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre">Nombre Completo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        class="form-control <?= isset($fieldErrors['nombre']) ? 'is-invalid' : '' ?>"
                        value="<?= $e($valNombre) ?>"
                        required
                        maxlength="100"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['nombre'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['nombre']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Nombre completo del médico veterinario.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="especialidad">Especialidad Médica <span class="text-muted">(opcional)</span></label>
                    <input
                        type="text"
                        id="especialidad"
                        name="especialidad"
                        class="form-control <?= isset($fieldErrors['especialidad']) ? 'is-invalid' : '' ?>"
                        maxlength="100"
                        value="<?= $e($valEspecialidad) ?>"
                    >
                    <?php if (isset($fieldErrors['especialidad'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['especialidad']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Área de especialidad médica.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="licencia_medica">Licencia Médica <span class="text-muted">(opcional)</span></label>
                    <input
                        type="text"
                        id="licencia_medica"
                        name="licencia_medica"
                        class="form-control <?= isset($fieldErrors['licencia_medica']) ? 'is-invalid' : '' ?>"
                        maxlength="50"
                        value="<?= $e($valLicencia) ?>"
                    >
                    <?php if (isset($fieldErrors['licencia_medica'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['licencia_medica']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Número de licencia profesional única.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="telefono">Teléfono de Contacto <span class="text-muted">(opcional)</span></label>
                    <input
                        type="tel"
                        id="telefono"
                        name="telefono"
                        class="form-control <?= isset($fieldErrors['telefono']) ? 'is-invalid' : '' ?>"
                        maxlength="20"
                        value="<?= $e($valTelefono) ?>"
                    >
                    <?php if (isset($fieldErrors['telefono'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['telefono']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Teléfono móvil o fijo.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="usuario">Nombre de Usuario <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        class="form-control <?= isset($fieldErrors['usuario']) ? 'is-invalid' : '' ?>"
                        maxlength="50"
                        value="<?= $e($valUsuario) ?>"
                        required
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['usuario'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['usuario']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Nombre de usuario único para iniciar sesión.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="correo">Correo Electrónico <span class="text-danger">*</span></label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        class="form-control <?= isset($fieldErrors['correo']) ? 'is-invalid' : '' ?>"
                        maxlength="150"
                        value="<?= $e($valCorreo) ?>"
                        required
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['correo'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['correo']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Correo único vinculado a la cuenta.</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Cambio Opcional de Credenciales (T-063: La contraseña solo se solicita al crear o cambiar credenciales) ── -->
            <h3 class="clinical-section-title">2. Cambiar Credenciales de Acceso <span class="text-muted" style="font-weight: 400; font-size: 0.9rem;">(opcional)</span></h3>
            <p class="text-muted" style="margin-bottom: 1rem;">
                Deja estos campos en blanco si deseas conservar la contraseña actual del veterinario. Solo complétalos cuando necesites asignar una nueva contraseña.
            </p>

            <div class="form-grid">
                <div class="form-group">
                    <label for="contrasena">Nueva Contraseña <span class="text-muted">(solo para cambiar credenciales)</span></label>
                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        class="form-control <?= isset($fieldErrors['contrasena']) ? 'is-invalid' : '' ?>"
                        placeholder="Dejar en blanco para no modificar"
                        minlength="8"
                        maxlength="128"
                        autocomplete="new-password"
                    >
                    <?php if (isset($fieldErrors['contrasena'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['contrasena']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Si ingresas una nueva contraseña, debe tener al menos 8 caracteres.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirmar_contrasena">Confirmar Nueva Contraseña <span class="text-muted">(opcional)</span></label>
                    <input
                        type="password"
                        id="confirmar_contrasena"
                        name="confirmar_contrasena"
                        class="form-control <?= isset($fieldErrors['confirmar_contrasena']) ? 'is-invalid' : '' ?>"
                        placeholder="Repite la nueva contraseña"
                        minlength="8"
                        maxlength="128"
                        autocomplete="new-password"
                    >
                    <?php if (isset($fieldErrors['confirmar_contrasena'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['confirmar_contrasena']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Requerido únicamente si escribiste una nueva contraseña.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions mt-2">
                <button type="submit" id="btn-submit" class="btn btn-primary">
                    Guardar Cambios
                </button>
                <a href="/veterinarios" class="btn btn-outline-dark">
                    Cancelar
                </a>
            </div>
        </form>

        <!-- ── Panel de Política de Baja Controlada / Estado de Cuenta (T-061, T-064, BAJA-01) ── -->
        <div class="baja-policy-card <?= $tieneDep ? 'baja-logica-mode' : '' ?>">
            <div class="baja-policy-header">
                <div>
                    <?php if (!$activo): ?>
                        <h4>Cuenta de Veterinario Inactiva</h4>
                        <p class="text-muted">
                            Esta cuenta se encuentra desactivada (`activo = 0`) y no puede iniciar sesión ni recibir nuevas citas.
                            Su historial de citas y consultas médicas se conserva íntegro. Puedes reactivarla cuando el veterinario se reincorpore.
                        </p>
                    <?php elseif ($tieneDep): ?>
                        <h4>Desactivar Cuenta del Veterinario</h4>
                        <p class="text-muted">
                            Este profesional registra <strong><?= $citasTotal ?> cita(s)</strong> (<?= $citasProgramadas ?> programada(s)) y <strong><?= $consultasTotal ?> consulta(s)</strong> clínicas.
                            Por integridad histórica (`BAJA-01`), no se elimina físicamente: al desactivarlo su cuenta pasará a estado inactivo, no podrá iniciar sesión, sus citas programadas pendientes se cancelarán automáticamente y se conservará todo su historial médico.
                        </p>
                    <?php else: ?>
                        <h4>Eliminar Registro de Veterinario</h4>
                        <p class="text-muted">
                            Este veterinario no posee citas ni consultas vinculadas en el historial clínico y puede eliminarse físicamente junto con su cuenta de usuario.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($activo): ?>
                        <form method="POST"
                              action="/veterinarios/baja"
                              class="form-baja-veterinario"
                              data-nombre="<?= $e($veterinario['nombre']) ?>"
                              data-usuario="<?= $e($veterinario['usuario']) ?>"
                              data-dependencias="<?= $tieneDep ? '1' : '0' ?>"
                              data-citas="<?= $citasTotal ?>"
                              data-citas-programadas="<?= $citasProgramadas ?>"
                              data-consultas="<?= $consultasTotal ?>"
                              data-accion="baja"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_veterinario" value="<?= $idVeterinario ?>">
                            <input type="hidden" name="accion" value="baja">
                            <?php if ($tieneDep): ?>
                                <button type="submit" class="btn btn-warning-outline">
                                    Desactivar Cuenta
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-danger">
                                    Eliminar Veterinario
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <form method="POST"
                              action="/veterinarios/baja"
                              class="form-baja-veterinario"
                              data-nombre="<?= $e($veterinario['nombre']) ?>"
                              data-usuario="<?= $e($veterinario['usuario']) ?>"
                              data-accion="reactivar"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_veterinario" value="<?= $idVeterinario ?>">
                            <input type="hidden" name="accion" value="reactivar">
                            <button type="submit" class="btn btn-primary">
                                Reactivar Cuenta
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="/assets/js/veterinarios.js?v=<?= time() ?>"></script>
</body>
</html>
