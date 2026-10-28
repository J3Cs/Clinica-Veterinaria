<?php
/**
 * Formulario de Edición y Detalle de Propietario — T-078, T-079, T-080, UI-01, UNI-01, BAJA-01, SEC-01
 *
 * @var array<string,mixed>   $propietario
 * @var string                $csrfToken
 * @var string|null           $flashError
 * @var string|null           $flashSuccess
 * @var array<string,string>  $fieldErrors
 * @var array<string,string>  $oldData
 * @var string                $nombreUsuario
 * @var string                $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$idPropietario   = (int) ($propietario['id_propietario'] ?? 0);
$activo          = (int) ($propietario['activo'] ?? 1) === 1;
$mascotas        = $propietario['mascotas'] ?? [];
$relaciones      = $propietario['relaciones'] ?? [];
$mascotasTotal   = (int) ($relaciones['mascotas_total'] ?? count($mascotas));
$mascotasActivas = (int) ($relaciones['mascotas_activas'] ?? 0);
$citasTotal      = (int) ($relaciones['citas_total'] ?? 0);
$consultasTotal  = (int) ($relaciones['consultas_total'] ?? 0);
$historialTotal  = (int) ($relaciones['historial_total'] ?? 0);
$tieneDep        = !empty($relaciones['tiene_dependencias']) || $mascotasTotal > 0;

$valNombre    = $oldData['nombre_completo'] ?? $propietario['nombre_completo'] ?? '';
$valDui       = $oldData['dui']             ?? $propietario['dui']             ?? '';
$valTelefono  = $oldData['telefono']        ?? $propietario['telefono']        ?? '';
$valCorreo    = $oldData['correo']          ?? $propietario['correo']          ?? '';
$valDireccion = $oldData['direccion']       ?? $propietario['direccion']       ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Propietario — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'propietarios'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>
                    Editar Propietario: <?= $e($propietario['nombre_completo']) ?>
                    <span class="badge <?= $activo ? 'badge-vet' : 'badge-inactivo' ?>">
                        <?= $activo ? 'Activo' : 'Inactivo' ?>
                    </span>
                </h2>
                <p class="text-muted">Actualiza la información de contacto o el número de DUI y consulta sus mascotas registradas.</p>
            </div>
            <div>
                <a href="/propietarios" class="btn btn-sm btn-outline-dark">&larr; Volver al directorio</a>
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

        <!-- ── Resumen de Registros del Propietario ── -->
        <div class="detail-grid">
            <div class="detail-stat-card">
                <div class="detail-stat-label">Mascotas Asociadas</div>
                <div class="detail-stat-value"><?= $mascotasTotal ?> <small class="text-muted">(<?= $mascotasActivas ?> activas)</small></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Citas Registradas</div>
                <div class="detail-stat-value"><?= $citasTotal ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Consultas Clínicas</div>
                <div class="detail-stat-value"><?= $consultasTotal ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Antecedentes en Historial</div>
                <div class="detail-stat-value"><?= $historialTotal ?></div>
            </div>
        </div>

        <!-- ── Formulario de Edición de Datos ── -->
        <form id="form-propietario" method="POST" action="/propietarios/editar" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_propietario" value="<?= $idPropietario ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="nombre_completo"
                        name="nombre_completo"
                        class="form-control <?= isset($fieldErrors['nombre_completo']) ? 'is-invalid' : '' ?>"
                        value="<?= $e($valNombre) ?>"
                        required
                        maxlength="150"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['nombre_completo'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['nombre_completo']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Nombre y apellidos del titular responsable.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="dui">DUI (Documento Único de Identidad) <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="dui"
                        name="dui"
                        class="form-control <?= isset($fieldErrors['dui']) ? 'is-invalid' : '' ?>"
                        placeholder="00000000-0"
                        maxlength="10"
                        value="<?= $e($valDui) ?>"
                        required
                        inputmode="numeric"
                        autocomplete="off"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['dui'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['dui']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Formato 00000000-0 (único por propietario).</small>
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
                        <small class="form-hint">Teléfono móvil o fijo de contacto.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="correo">Correo Electrónico <span class="text-muted">(opcional)</span></label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        class="form-control <?= isset($fieldErrors['correo']) ? 'is-invalid' : '' ?>"
                        maxlength="150"
                        value="<?= $e($valCorreo) ?>"
                    >
                    <?php if (isset($fieldErrors['correo'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['correo']) ?></span>
                    <?php else: ?>
                        <small class="form-hint">Correo para avisos y notificaciones.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="direccion">Dirección de Residencia <span class="text-muted">(opcional)</span></label>
                <textarea
                    id="direccion"
                    name="direccion"
                    class="form-control"
                    rows="3"
                ><?= $e($valDireccion) ?></textarea>
            </div>

            <div class="form-actions mt-2">
                <button type="submit" id="btn-submit" class="btn btn-primary">
                    Guardar Cambios
                </button>
                <a href="/propietarios" class="btn btn-outline-dark">
                    Cancelar
                </a>
            </div>
        </form>

        <!-- ── Mascotas Asociadas al Propietario ── -->
        <h3 class="clinical-section-title">Mascotas Asociadas a este Propietario</h3>
        <?php if (empty($mascotas)): ?>
            <p class="text-muted">Este propietario aún no tiene mascotas registradas.</p>
            <?php if ($activo): ?>
                <a href="/mascotas/crear?propietario_id=<?= $idPropietario ?>" class="btn btn-sm btn-primary">+ Registrar Mascota</a>
            <?php endif; ?>
        <?php else: ?>
            <p class="table-helper-text">
                Selecciona cualquiera de las mascotas asociadas para abrir su expediente o editar sus datos.
            </p>

            <div id="selection-action-bar-mascotas" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Mascota Seleccionada</span>
                    <span id="selection-summary-mascota">Mascota</span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-edit-associated-pet" class="btn-action-edit">
                        Ver / Editar Expediente de Mascota
                    </button>
                    <button type="button" id="btn-cancel-associated-pet" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <div class="table-container-center">
                <table class="data-table-horarios" id="associated-pets-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Mascota</th>
                            <th>Especie / Raza</th>
                            <th>Sexo</th>
                            <th>Historial Registrado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mascotas as $m): ?>
                            <?php
                                $mId     = (int) $m['id_mascota'];
                                $mActiva = (int) ($m['activo'] ?? 1) === 1;
                            ?>
                            <tr class="horario-row associated-pet-row <?= $mActiva ? '' : 'row-inactivo' ?>"
                                data-id="<?= $mId ?>"
                                data-resumen="<?= $e($m['nombre'] . ' (' . $m['especie'] . ' — ' . ($m['raza'] ?? '—') . ')') ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td>
                                    <span class="custom-radio-indicator"></span>
                                </td>
                                <td>
                                    <span class="vet-name"><?= $e($m['nombre']) ?></span>
                                </td>
                                <td>
                                    <div class="vet-info-centered">
                                        <span class="hora-text"><?= $e($m['especie']) ?></span>
                                        <span class="vet-spec"><?= $e($m['raza'] ?? '—') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= ($m['sexo'] ?? '') === 'Macho' ? 'badge-macho' : 'badge-hembra' ?>">
                                        <?= mb_strtoupper($e($m['sexo']), 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="relations-group">
                                        <span class="badge-relation badge-relation-clinical"><?= (int)($m['citas_total'] ?? 0) ?> citas</span>
                                        <span class="badge-relation badge-relation-history"><?= (int)($m['consultas_total'] ?? 0) ?> consultas</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $mActiva ? 'badge-vet' : 'badge-inactivo' ?>">
                                        <?= $mActiva ? 'ACTIVA' : 'INACTIVA' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ── Gestión de Estado del Propietario ── -->
        <div class="baja-policy-card <?= $tieneDep ? 'baja-logica-mode' : '' ?>">
            <div class="baja-policy-header">
                <div>
                    <?php if (!$activo): ?>
                        <h4>Propietario Inactivo</h4>
                        <p class="text-muted">Sus registros clínicos e historial se conservan. Puedes reactivarlo en cualquier momento.</p>
                    <?php elseif ($tieneDep): ?>
                        <h4>Desactivar Propietario</h4>
                        <p class="text-muted">
                            Este propietario tiene <strong><?= $mascotasTotal ?> mascota(s)</strong> y <strong><?= $citasTotal ?> cita(s)</strong> registradas.
                            Si lo desactivas, dejará de aparecer en los listados activos pero todo su historial médico se conservará.
                        </p>
                    <?php else: ?>
                        <h4>Eliminar Registro de Propietario</h4>
                        <p class="text-muted">
                            Este propietario no tiene mascotas ni citas registradas y puede eliminarse del sistema.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($activo): ?>
                        <form method="POST"
                              action="/propietarios/baja"
                              class="form-baja-propietario"
                              data-nombre="<?= $e($propietario['nombre_completo']) ?>"
                              data-dui="<?= $e($propietario['dui']) ?>"
                              data-dependencias="<?= $tieneDep ? '1' : '0' ?>"
                              data-mascotas="<?= $mascotasTotal ?>"
                              data-citas="<?= $citasTotal ?>"
                              data-accion="baja"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_propietario" value="<?= $idPropietario ?>">
                            <input type="hidden" name="accion" value="baja">
                            <?php if ($tieneDep): ?>
                                <button type="submit" class="btn btn-warning-outline">
                                    Desactivar Propietario
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-danger">
                                    Eliminar Propietario
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <form method="POST"
                              action="/propietarios/baja"
                              class="form-baja-propietario"
                              data-nombre="<?= $e($propietario['nombre_completo']) ?>"
                              data-accion="reactivar"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_propietario" value="<?= $idPropietario ?>">
                            <input type="hidden" name="accion" value="reactivar">
                            <button type="submit" class="btn btn-primary">
                                Reactivar Propietario
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="/assets/js/propietarios.js?v=<?= time() ?>"></script>
</body>
</html>
