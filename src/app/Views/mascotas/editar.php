<?php
/**
 * Formulario de Edición y Expediente Clínico de Mascota — T-076, T-078, T-079, T-080, UI-01, DAT-01, BAJA-01, SEC-01
 * Al cargar una mascota se conoce su propietario y las relaciones clínicas asociadas.
 *
 * @var array<string,mixed>        $mascota
 * @var array<array<string,mixed>> $propietarios
 * @var string                     $csrfToken
 * @var string|null                $flashError
 * @var string|null                $flashSuccess
 * @var array<string,string>       $fieldErrors
 * @var array<string,string>       $oldData
 * @var string                     $nombreUsuario
 * @var string                     $rolUsuario
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$idMascota      = (int) ($mascota['id_mascota'] ?? 0);
$activa         = (int) ($mascota['activo'] ?? 1) === 1;
$relaciones     = $mascota['relaciones'] ?? [];
$citasTotal     = (int) ($relaciones['citas_total'] ?? $mascota['citas_total'] ?? 0);
$consultasTotal = (int) ($relaciones['consultas_total'] ?? $mascota['consultas_total'] ?? 0);
$historialTotal = (int) ($relaciones['historial_total'] ?? $mascota['historial_total'] ?? 0);
$tieneDep       = !empty($relaciones['tiene_dependencias']) || ($citasTotal > 0 || $consultasTotal > 0 || $historialTotal > 0);

$citas        = $relaciones['citas']        ?? [];
$consultas    = $relaciones['consultas']    ?? [];
$antecedentes = $relaciones['antecedentes'] ?? [];

$valIdPropietario   = (int) ($oldData['id_propietario'] ?? $mascota['id_propietario'] ?? 0);
$valNombre          = $oldData['nombre']                     ?? $mascota['nombre']                     ?? '';
$valEspecie         = $oldData['especie']                    ?? $mascota['especie']                    ?? '';
$valRaza            = $oldData['raza']                       ?? $mascota['raza']                       ?? '';
$valSexo            = $oldData['sexo']                       ?? $mascota['sexo']                       ?? '';
$valFechaNacimiento = $oldData['fecha_nacimiento']           ?? $mascota['fecha_nacimiento']           ?? '';
$valCaracteristicas = $oldData['caracteristicas_relevantes'] ?? $mascota['caracteristicas_relevantes'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Mascota y Expediente — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'mascotas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>
                    Expediente de Mascota: <?= $e($mascota['nombre']) ?>
                    <span class="badge <?= $activa ? 'badge-vet' : 'badge-inactivo' ?>">
                        <?= $activa ? 'Activa' : 'Inactiva' ?>
                    </span>
                </h2>
                <p class="text-muted">Actualiza los datos del paciente, consulta su propietario responsable y revisa su historial clínico.</p>
            </div>
            <div>
                <a href="/mascotas" class="btn btn-sm btn-outline-dark">&larr; Volver al directorio</a>
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

        <!-- ── Información del Propietario Actual ── -->
        <div class="owner-summary-box">
            <div>
                <strong>Propietario Responsable:</strong>
                <?= $e($mascota['propietario_nombre'] ?? '—') ?>
                &middot; DUI: <code><?= $e($mascota['propietario_dui'] ?? '—') ?></code>
                <?php if (!empty($mascota['propietario_telefono'])): ?>
                    &middot; Tel.: <?= $e($mascota['propietario_telefono']) ?>
                <?php endif; ?>
                <?php if (!empty($mascota['propietario_correo'])): ?>
                    &middot; <?= $e($mascota['propietario_correo']) ?>
                <?php endif; ?>
                <span class="badge <?= (int)($mascota['propietario_activo'] ?? 1) === 1 ? 'badge-vet' : 'badge-inactivo' ?>">
                    <?= (int)($mascota['propietario_activo'] ?? 1) === 1 ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
            <div>
                <a href="/propietarios/editar?id=<?= (int)($mascota['id_propietario'] ?? 0) ?>" class="btn btn-sm btn-outline-dark">
                    Ver Ficha del Propietario
                </a>
            </div>
        </div>

        <!-- ── Resumen de Historial Registrado ── -->
        <div class="detail-grid">
            <div class="detail-stat-card">
                <div class="detail-stat-label">Citas Registradas</div>
                <div class="detail-stat-value"><?= $citasTotal ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Consultas Veterinarias</div>
                <div class="detail-stat-value"><?= $consultasTotal ?></div>
            </div>
            <div class="detail-stat-card">
                <div class="detail-stat-label">Antecedentes Médicos</div>
                <div class="detail-stat-value"><?= $historialTotal ?></div>
            </div>
        </div>

        <!-- ── Formulario de Edición de Mascota ── -->
        <form id="form-mascota" method="POST" action="/mascotas/editar" novalidate autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="id_mascota" value="<?= $idMascota ?>">

            <div class="form-group">
                <label for="id_propietario">Propietario Responsable <span class="text-danger">*</span></label>
                <select
                    id="id_propietario"
                    name="id_propietario"
                    class="form-control <?= isset($fieldErrors['id_propietario']) ? 'is-invalid' : '' ?>"
                    required
                    aria-required="true"
                >
                    <option value="">-- Selecciona el propietario responsable --</option>
                    <?php foreach ($propietarios as $prop): ?>
                        <?php $selected = ($valIdPropietario === (int)$prop['id_propietario']) ? 'selected' : ''; ?>
                        <option value="<?= (int)$prop['id_propietario'] ?>" <?= $selected ?>>
                            <?= $e($prop['nombre_completo']) ?> (DUI: <?= $e($prop['dui']) ?>)<?= (int)($prop['activo'] ?? 1) !== 1 ? ' [Inactivo]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($fieldErrors['id_propietario'])): ?>
                    <span class="invalid-feedback"><?= $e($fieldErrors['id_propietario']) ?></span>
                <?php else: ?>
                    <small class="form-hint">Toda mascota debe estar asignada a un propietario activo.</small>
                <?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre">Nombre de la Mascota <span class="text-danger">*</span></label>
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
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="especie">Especie <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="especie"
                        name="especie"
                        list="especies-sugeridas"
                        class="form-control <?= isset($fieldErrors['especie']) ? 'is-invalid' : '' ?>"
                        value="<?= $e($valEspecie) ?>"
                        required
                        maxlength="50"
                        aria-required="true"
                    >
                    <datalist id="especies-sugeridas">
                        <option value="Canino (Perro)">
                        <option value="Felino (Gato)">
                        <option value="Ave">
                        <option value="Conejo">
                        <option value="Roedor">
                        <option value="Reptil">
                        <option value="Equino">
                    </datalist>
                    <?php if (isset($fieldErrors['especie'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['especie']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="raza">Raza <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        id="raza"
                        name="raza"
                        class="form-control <?= isset($fieldErrors['raza']) ? 'is-invalid' : '' ?>"
                        value="<?= $e($valRaza) ?>"
                        required
                        maxlength="50"
                        aria-required="true"
                    >
                    <?php if (isset($fieldErrors['raza'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['raza']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="sexo">Sexo <span class="text-danger">*</span></label>
                    <select
                        id="sexo"
                        name="sexo"
                        class="form-control <?= isset($fieldErrors['sexo']) ? 'is-invalid' : '' ?>"
                        required
                        aria-required="true"
                    >
                        <option value="">-- Selecciona el sexo --</option>
                        <option value="Macho" <?= $valSexo === 'Macho' ? 'selected' : '' ?>>Macho</option>
                        <option value="Hembra" <?= $valSexo === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
                    </select>
                    <?php if (isset($fieldErrors['sexo'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['sexo']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="fecha_nacimiento">Fecha de Nacimiento <span class="text-muted">(Opcional)</span></label>
                    <input
                        type="date"
                        id="fecha_nacimiento"
                        name="fecha_nacimiento"
                        class="form-control <?= isset($fieldErrors['fecha_nacimiento']) ? 'is-invalid' : '' ?>"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= $e($valFechaNacimiento) ?>"
                    >
                    <?php if (isset($fieldErrors['fecha_nacimiento'])): ?>
                        <span class="invalid-feedback"><?= $e($fieldErrors['fecha_nacimiento']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="caracteristicas_relevantes">Características Relevantes / Señas Particulares <span class="text-muted">(Opcional)</span></label>
                <textarea
                    id="caracteristicas_relevantes"
                    name="caracteristicas_relevantes"
                    class="form-control"
                    rows="3"
                ><?= $e($valCaracteristicas) ?></textarea>
            </div>

            <div class="form-actions mt-2">
                <button type="submit" id="btn-submit-mascota" class="btn btn-primary">
                    Guardar Cambios
                </button>
                <a href="/mascotas" class="btn btn-outline-dark">
                    Cancelar
                </a>
            </div>
        </form>

        <!-- ── Detalle de Historial (Citas, Consultas e Historial) ── -->
        <h3 class="clinical-section-title">Citas Médicas Asociadas (<?= count($citas) ?>)</h3>
        <?php if (empty($citas)): ?>
            <p class="text-muted">Esta mascota no tiene citas registradas.</p>
        <?php else: ?>
            <p class="table-helper-text">
                Selecciona una cita médica para ver las acciones disponibles.
            </p>

            <div id="selection-action-bar-citas" class="selection-action-bar" style="display: none;">
                <div class="selection-info">
                    <span class="selection-badge">Cita Seleccionada</span>
                    <span id="selection-summary-cita">Cita médica</span>
                </div>
                <div class="selection-buttons">
                    <button type="button" id="btn-ir-modulo-citas" class="btn-action-edit">
                        Ir al Módulo de Citas
                    </button>
                    <button type="button" id="btn-cancelar-cita-selected" class="btn-action-delete" style="display: none;">
                        Cancelar Cita Programada
                    </button>
                    <button type="button" id="btn-deselect-cita" class="btn-action-cancel">
                        Deseleccionar
                    </button>
                </div>
            </div>

            <div class="table-container-center">
                <table class="data-table-horarios" id="pet-citas-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Servicio</th>
                            <th>Veterinario</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $cita): ?>
                            <?php
                                $idCita     = (int) ($cita['id_cita'] ?? 0);
                                $horaCita   = substr((string)($cita['hora_cita'] ?? ''), 0, 5);
                                $estadoCita = (string) ($cita['estado'] ?? '');
                            ?>
                            <tr class="horario-row pet-cita-row"
                                data-id="<?= $idCita ?>"
                                data-estado="<?= $e($estadoCita) ?>"
                                data-resumen="<?= $e($cita['fecha_cita'] . ' (' . $horaCita . ') — ' . ($cita['nombre_servicio'] ?? 'Servicio') . ' [' . $estadoCita . ']') ?>"
                                tabindex="0"
                                role="button"
                                aria-pressed="false"
                            >
                                <td><span class="custom-radio-indicator"></span></td>
                                <td><span class="vet-name"><?= $e($cita['fecha_cita']) ?></span></td>
                                <td><span class="hora-text"><?= $e($horaCita) ?></span></td>
                                <td><span class="vet-name"><?= $e($cita['nombre_servicio'] ?? '—') ?></span></td>
                                <td><span class="vet-spec"><?= $e($cita['veterinario_nombre'] ?? '—') ?></span></td>
                                <td>
                                    <span class="badge <?= $estadoCita === 'Programada' ? 'badge-vet' : ($estadoCita === 'Cancelada' ? 'badge-inactivo' : 'badge-macho') ?>">
                                        <?= mb_strtoupper($e($estadoCita), 'UTF-8') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="clinical-section-title">Consultas Veterinarias Atendidas (<?= count($consultas) ?>)</h3>
        <?php if (empty($consultas)): ?>
            <p class="text-muted">Esta mascota aún no registra consultas veterinarias atendidas.</p>
        <?php else: ?>
            <div class="table-container-center">
                <table class="data-table-horarios">
                    <thead>
                        <tr>
                            <th>Fecha Atención</th>
                            <th>Veterinario</th>
                            <th>Diagnóstico</th>
                            <th>Tratamiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultas as $con): ?>
                            <tr class="horario-row" style="cursor: default;">
                                <td><span class="hora-text"><?= $e($con['fecha_atencion']) ?></span></td>
                                <td><span class="vet-name"><?= $e($con['veterinario_nombre'] ?? '—') ?></span></td>
                                <td><?= $e($con['diagnostico']) ?></td>
                                <td><?= $e($con['tratamiento'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($antecedentes)): ?>
            <h3 class="clinical-section-title">Antecedentes Médicos Previos (<?= count($antecedentes) ?>)</h3>
            <div class="table-container-center">
                <table class="data-table-horarios">
                    <thead>
                        <tr>
                            <th>Condición Médica</th>
                            <th>Descripción</th>
                            <th>Alergias</th>
                            <th>Fecha Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($antecedentes as $ant): ?>
                            <tr class="horario-row" style="cursor: default;">
                                <td><span class="vet-name"><?= $e($ant['condicion_medica']) ?></span></td>
                                <td><?= $e($ant['descripcion_problema'] ?? '—') ?></td>
                                <td><?= $e($ant['alergias'] ?? '—') ?></td>
                                <td><span class="hora-text"><?= $e($ant['fecha_registro_antecedente'] ?? '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ── Gestión de Estado de la Mascota ── -->
        <div class="baja-policy-card <?= $tieneDep ? 'baja-logica-mode' : '' ?>">
            <div class="baja-policy-header">
                <div>
                    <?php if (!$activa): ?>
                        <h4>Mascota Inactiva</h4>
                        <p class="text-muted">Sus citas y consultas históricas se conservan. Puedes reactivarla si su propietario está activo.</p>
                    <?php elseif ($tieneDep): ?>
                        <h4>Desactivar Mascota</h4>
                        <p class="text-muted">
                            Esta mascota tiene <strong><?= $citasTotal ?> cita(s)</strong>, <strong><?= $consultasTotal ?> consulta(s)</strong> y <strong><?= $historialTotal ?> antecedente(s)</strong> registrados.
                            Si la desactivas, dejará de aparecer como activa pero conservará todo su expediente médico.
                        </p>
                    <?php else: ?>
                        <h4>Eliminar Registro de Mascota</h4>
                        <p class="text-muted">
                            Esta mascota no tiene citas ni consultas registradas y puede eliminarse del sistema.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($activa): ?>
                        <form method="POST"
                              action="/mascotas/baja"
                              class="form-baja-mascota"
                              data-nombre="<?= $e($mascota['nombre']) ?>"
                              data-dependencias="<?= $tieneDep ? '1' : '0' ?>"
                              data-citas="<?= $citasTotal ?>"
                              data-consultas="<?= $consultasTotal ?>"
                              data-historial="<?= $historialTotal ?>"
                              data-accion="baja"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_mascota" value="<?= $idMascota ?>">
                            <input type="hidden" name="accion" value="baja">
                            <?php if ($tieneDep): ?>
                                <button type="submit" class="btn btn-warning-outline">
                                    Desactivar Mascota
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-danger">
                                    Eliminar Mascota
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <form method="POST"
                              action="/mascotas/baja"
                              class="form-baja-mascota"
                              data-nombre="<?= $e($mascota['nombre']) ?>"
                              data-accion="reactivar"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="id_mascota" value="<?= $idMascota ?>">
                            <input type="hidden" name="accion" value="reactivar">
                            <button type="submit" class="btn btn-primary">
                                Reactivar Mascota
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="/assets/js/mascotas.js?v=<?= time() ?>"></script>
</body>
</html>
