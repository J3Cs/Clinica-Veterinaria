<?php
/**
 * Formulario de Agendamiento de Citas — US-04 (T-034), UI-01, SEC-01
 * Fragmento incluido por listar.php (pestaña «Citas»).
 * Homologado con el diseño de tarjetas y módulos de Horarios, Propietarios y Mascotas.
 *
 * @var \Closure                    $e            Escapa HTML (definido en listar.php)
 * @var string                      $csrfToken
 * @var array<string,string>        $filtros      Selección actual (id_mascota, id_servicio, id_veterinario, fecha)
 * @var array<string,array>         $opciones     mascotas, servicios, veterinarios (solo activos)
 * @var array<string,mixed>|null    $consulta     Resultado de consultarDisponibilidad() o null
 * @var array<string,string>        $fieldErrors  Errores de un intento de guardado anterior
 * @var string                      $estadoFiltro Filtro actual del listado (se conserva al consultar)
 */
$errores = array_merge($fieldErrors ?? [], $consulta['errors'] ?? []);
$hoy     = date('Y-m-d');

$sinMascotas     = empty($opciones['mascotas']);
$sinServicios    = empty($opciones['servicios']);
$sinVeterinarios = empty($opciones['veterinarios']);
$faltanDatos     = $sinMascotas || $sinServicios || $sinVeterinarios;

$hhmm = static function (string $inicio, int $min): string {
    [$h, $m] = array_map('intval', explode(':', $inicio));
    $fin = $h * 60 + $m + $min;
    return sprintf('%s – %02d:%02d', $inicio, intdiv($fin, 60) % 24, $fin % 60);
};

$consultaValida = $consulta !== null && empty($consulta['errors']);
?>
<section class="card card-modulo" id="agendar" aria-labelledby="titulo-agendar">
    <div class="card-header-actions">
        <div>
            <h2 id="titulo-agendar">Agendar Cita Médica</h2>
            <p class="text-muted">Selecciona paciente, servicio clínico, médico veterinario y fecha para consultar y reservar un horario disponible.</p>
        </div>
        <div>
            <a href="#listado" class="btn btn-sm btn-outline-dark">Ir al Listado de Citas &darr;</a>
        </div>
    </div>

    <?php if ($faltanDatos): ?>
        <div class="empty-state" role="status">
            <h3>Aún no se puede agendar</h3>
            <p>
                Para agendar una cita se requiere contar con al menos
                <?php
                $faltan = [];
                if ($sinMascotas)     { $faltan[] = 'una mascota activa'; }
                if ($sinServicios)    { $faltan[] = 'un servicio activo'; }
                if ($sinVeterinarios) { $faltan[] = 'un veterinario activo'; }
                echo $e(implode(', ', $faltan));
                ?>.
            </p>
            <div class="form-actions" style="justify-content: center; margin-top: 1rem;">
                <?php if ($sinMascotas): ?>
                    <a href="/mascotas/crear" class="btn btn-primary">+ Registrar Mascota</a>
                <?php endif; ?>
                <?php if ($sinServicios): ?>
                    <a href="/servicios/crear" class="btn btn-outline-dark">+ Nuevo Servicio</a>
                <?php endif; ?>
                <?php if ($sinVeterinarios): ?>
                    <a href="/veterinarios/crear" class="btn btn-outline-dark">+ Registrar Veterinario</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

        <!-- Paso 1: selección. Se envía por GET para ver la disponibilidad sin JavaScript. -->
        <form id="form-consulta" method="GET" action="/citas" novalidate>
            <input type="hidden" name="estado" value="<?= $e($estadoFiltro) ?>">
            <div class="form-grid citas-form-grid">
                <div class="form-group">
                    <label for="id_mascota">Mascota / Paciente <span class="text-danger">*</span></label>
                    <select id="id_mascota" name="id_mascota" required
                            class="form-control <?= isset($errores['id_mascota']) ? 'is-invalid' : '' ?>">
                        <option value="">-- Selecciona la mascota --</option>
                        <?php foreach ($opciones['mascotas'] as $m): ?>
                            <option value="<?= (int) $m['id_mascota'] ?>" <?= $filtros['id_mascota'] === (string) $m['id_mascota'] ? 'selected' : '' ?>>
                                <?= $e($m['nombre']) ?> (<?= $e($m['especie']) ?>) — <?= $e($m['propietario_nombre'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['id_mascota'])): ?>
                        <div class="invalid-feedback" style="display:block"><?= $e($errores['id_mascota']) ?></div>
                    <?php else: ?>
                        <small class="form-hint">Solo se muestran mascotas y propietarios activos.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="id_servicio">Servicio Clínico <span class="text-danger">*</span></label>
                    <select id="id_servicio" name="id_servicio" required
                            class="form-control <?= isset($errores['id_servicio']) ? 'is-invalid' : '' ?>">
                        <option value="">-- Selecciona el servicio --</option>
                        <?php foreach ($opciones['servicios'] as $s): ?>
                            <option value="<?= (int) $s['id_servicio'] ?>" <?= $filtros['id_servicio'] === (string) $s['id_servicio'] ? 'selected' : '' ?>>
                                <?= $e($s['nombre_servicio']) ?> (<?= (int) $s['duracion_estimada_min'] ?> min)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['id_servicio'])): ?>
                        <div class="invalid-feedback" style="display:block"><?= $e($errores['id_servicio']) ?></div>
                    <?php else: ?>
                        <small class="form-hint">Define la duración estimada del bloque en agenda.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="id_veterinario">Médico Veterinario <span class="text-danger">*</span></label>
                    <select id="id_veterinario" name="id_veterinario" required
                            class="form-control <?= isset($errores['id_veterinario']) ? 'is-invalid' : '' ?>">
                        <option value="">-- Selecciona el veterinario --</option>
                        <?php foreach ($opciones['veterinarios'] as $v): ?>
                            <option value="<?= (int) $v['id_veterinario'] ?>" <?= $filtros['id_veterinario'] === (string) $v['id_veterinario'] ? 'selected' : '' ?>>
                                <?= $e($v['nombre']) ?><?= !empty($v['especialidad']) ? ' — ' . $e($v['especialidad']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['id_veterinario'])): ?>
                        <div class="invalid-feedback" style="display:block"><?= $e($errores['id_veterinario']) ?></div>
                    <?php else: ?>
                        <small class="form-hint">Profesional encargado de atender la cita.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha de la Cita <span class="text-danger">*</span></label>
                    <input type="date" id="fecha" name="fecha" required min="<?= $e($hoy) ?>"
                           value="<?= $e($filtros['fecha']) ?>"
                           class="form-control <?= isset($errores['fecha']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errores['fecha'])): ?>
                        <div class="invalid-feedback" style="display:block"><?= $e($errores['fecha']) ?></div>
                    <?php else: ?>
                        <small class="form-hint">A partir del día de hoy (<?= $e(date('d/m/Y')) ?>).</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions mt-1">
                <button type="submit" name="consultar" value="1" class="btn btn-primary">Ver Horarios Disponibles</button>
                <?php if ($filtros['id_mascota'] !== '' || $filtros['id_servicio'] !== '' || $filtros['id_veterinario'] !== '' || $filtros['fecha'] !== ''): ?>
                    <a href="/citas<?= $estadoFiltro !== 'Programada' ? '?estado=' . $e($estadoFiltro) : '' ?>" class="btn btn-outline-dark">Limpiar Selección</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($consultaValida): ?>
            <hr class="citas-separador">

            <!-- Paso 2: horarios y confirmación -->
            <div aria-labelledby="titulo-horarios">
                <div class="owner-summary-box" style="margin-bottom: 1.25rem;">
                    <div>
                        <strong id="titulo-horarios" style="font-size: 1.02rem; color: #0f172a;">Franjas Horarias Disponibles</strong>
                        <div class="text-muted" style="font-size: 0.9rem; margin-top: 0.15rem;">
                            <?= $e($consulta['veterinario']['nombre']) ?> &middot;
                            <?= $e($consulta['servicio']['nombre_servicio']) ?> (<?= (int) $consulta['duracion'] ?> min) &middot;
                            <?= $e(date('d/m/Y', (int) strtotime((string) $consulta['fecha']))) ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge badge-vet"><?= count($consulta['franjas']) ?> franja(s) libre(s)</span>
                    </div>
                </div>

                <?php if (empty($consulta['franjas'])): ?>
                    <div class="alert alert-warning" role="status">
                        <span>No hay horarios disponibles para esa combinación. Prueba con otra fecha, otro servicio u otro médico veterinario, o verifica los <a href="/horarios" style="font-weight: 600; text-decoration: underline;">Horarios de Atención</a>.</span>
                    </div>
                <?php else: ?>
                    <form id="form-confirmar" method="POST" action="/citas" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="id_mascota" value="<?= (int) $consulta['mascota']['id_mascota'] ?>">
                        <input type="hidden" name="id_servicio" value="<?= (int) $consulta['servicio']['id_servicio'] ?>">
                        <input type="hidden" name="id_veterinario" value="<?= (int) $consulta['veterinario']['id_veterinario'] ?>">
                        <input type="hidden" name="fecha" value="<?= $e($consulta['fecha']) ?>">

                        <fieldset class="slot-fieldset">
                            <legend class="sr-only">Selecciona un horario disponible</legend>
                            <div class="slot-grid">
                                <?php foreach ($consulta['franjas'] as $hora): ?>
                                    <?php $idSlot = 'slot-' . str_replace(':', '', $hora); ?>
                                    <div class="slot-option">
                                        <input type="radio" id="<?= $e($idSlot) ?>" name="hora_cita" value="<?= $e($hora) ?>" required>
                                        <label for="<?= $e($idSlot) ?>"><?= $e($hhmm($hora, (int) $consulta['duracion'])) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php if (isset($errores['hora_cita'])): ?>
                            <div class="invalid-feedback" style="display:block"><?= $e($errores['hora_cita']) ?></div>
                        <?php endif; ?>

                        <div id="resumen-cita"
                             class="detail-grid mt-1"
                             data-mascota="<?= $e($consulta['mascota']['nombre']) ?>"
                             data-servicio="<?= $e($consulta['servicio']['nombre_servicio']) ?>"
                             data-veterinario="<?= $e($consulta['veterinario']['nombre']) ?>"
                             data-fecha="<?= $e(date('d/m/Y', (int) strtotime((string) $consulta['fecha']))) ?>">
                            <div class="detail-stat-card">
                                <div class="detail-stat-label">Mascota</div>
                                <div class="detail-stat-value" style="font-size: 1.05rem;"><?= $e($consulta['mascota']['nombre']) ?></div>
                            </div>
                            <div class="detail-stat-card">
                                <div class="detail-stat-label">Servicio</div>
                                <div class="detail-stat-value" style="font-size: 1.05rem;"><?= $e($consulta['servicio']['nombre_servicio']) ?></div>
                            </div>
                            <div class="detail-stat-card">
                                <div class="detail-stat-label">Veterinario</div>
                                <div class="detail-stat-value" style="font-size: 1.05rem;"><?= $e($consulta['veterinario']['nombre']) ?></div>
                            </div>
                            <div class="detail-stat-card">
                                <div class="detail-stat-label">Fecha y Hora</div>
                                <div class="detail-stat-value" style="font-size: 1.05rem;">
                                    <?= $e(date('d/m/Y', (int) strtotime((string) $consulta['fecha']))) ?> &middot;
                                    <span id="resumen-hora" style="color: #2c6b4f;">Selecciona un horario</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Confirmar y Agendar Cita</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>
