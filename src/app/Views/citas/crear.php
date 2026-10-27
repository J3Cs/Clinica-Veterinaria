<?php
/**
 * Formulario de Agendamiento de Citas — US-04 (T-034), UI-01, SEC-01
 * Fragmento incluido por listar.php (pestaña «Citas»); no es una página completa.
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
<section class="card" id="agendar" aria-labelledby="titulo-agendar">
    <div class="card-header-actions">
        <div>
            <h2 id="titulo-agendar">Agendar cita</h2>
            <p class="text-muted">Elige mascota, servicio, veterinario y fecha; luego selecciona uno de los horarios disponibles.</p>
        </div>
    </div>

    <?php if ($faltanDatos): ?>
        <div class="empty-state" role="status">
            <div class="empty-state-icon" aria-hidden="true">⚠️</div>
            <h3>Aún no se puede agendar</h3>
            <p>
                Para agendar se necesita al menos
                <?php
                $faltan = [];
                if ($sinMascotas)     { $faltan[] = 'una mascota activa'; }
                if ($sinServicios)    { $faltan[] = 'un servicio activo'; }
                if ($sinVeterinarios) { $faltan[] = 'un veterinario activo'; }
                echo $e(implode(', ', $faltan));
                ?>.
            </p>
            <?php if ($sinMascotas): ?>
                <a href="/mascotas/crear" class="btn btn-primary">+ Registrar Mascota</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Paso 1: selección. Se envía por GET para ver la disponibilidad sin JavaScript. -->
        <form id="form-consulta" method="GET" action="/citas" novalidate>
            <input type="hidden" name="estado" value="<?= $e($estadoFiltro) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="id_mascota">Mascota <span class="text-danger">*</span></label>
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
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="id_servicio">Servicio <span class="text-danger">*</span></label>
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
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="id_veterinario">Veterinario <span class="text-danger">*</span></label>
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
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha <span class="text-danger">*</span></label>
                    <input type="date" id="fecha" name="fecha" required min="<?= $e($hoy) ?>"
                           value="<?= $e($filtros['fecha']) ?>"
                           class="form-control <?= isset($errores['fecha']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errores['fecha'])): ?>
                        <div class="invalid-feedback" style="display:block"><?= $e($errores['fecha']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="consultar" value="1" class="btn btn-primary">Ver horarios disponibles</button>
            </div>
        </form>

        <?php if ($consultaValida): ?>
            <hr class="citas-separador">

            <!-- Paso 2: horarios y confirmación -->
            <div aria-labelledby="titulo-horarios">
                <h3 id="titulo-horarios">Horarios disponibles</h3>
                <p class="text-muted">
                    <?= $e($consulta['veterinario']['nombre']) ?> ·
                    <?= $e($consulta['servicio']['nombre_servicio']) ?> (<?= (int) $consulta['duracion'] ?> min) ·
                    <?= $e(date('d/m/Y', strtotime($consulta['fecha']))) ?>
                </p>

                <?php if (empty($consulta['franjas'])): ?>
                    <div class="alert alert-warning" role="status">
                        <span aria-hidden="true">📅</span>
                        <span>No hay horarios disponibles para esa combinación. Prueba con otra fecha u otro veterinario.</span>
                    </div>
                <?php else: ?>
                    <form id="form-confirmar" method="POST" action="/citas" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="id_mascota" value="<?= (int) $consulta['mascota']['id_mascota'] ?>">
                        <input type="hidden" name="id_servicio" value="<?= (int) $consulta['servicio']['id_servicio'] ?>">
                        <input type="hidden" name="id_veterinario" value="<?= (int) $consulta['veterinario']['id_veterinario'] ?>">
                        <input type="hidden" name="fecha" value="<?= $e($consulta['fecha']) ?>">

                        <fieldset class="slot-fieldset">
                            <legend class="sr-only">Selecciona un horario</legend>
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

                        <dl id="resumen-cita" class="resumen-cita"
                            data-mascota="<?= $e($consulta['mascota']['nombre']) ?>"
                            data-servicio="<?= $e($consulta['servicio']['nombre_servicio']) ?>"
                            data-veterinario="<?= $e($consulta['veterinario']['nombre']) ?>"
                            data-fecha="<?= $e(date('d/m/Y', strtotime($consulta['fecha']))) ?>">
                            <dt>Mascota</dt><dd><?= $e($consulta['mascota']['nombre']) ?></dd>
                            <dt>Servicio</dt><dd><?= $e($consulta['servicio']['nombre_servicio']) ?></dd>
                            <dt>Veterinario</dt><dd><?= $e($consulta['veterinario']['nombre']) ?></dd>
                            <dt>Fecha</dt><dd><?= $e(date('d/m/Y', strtotime($consulta['fecha']))) ?></dd>
                            <dt>Hora</dt><dd id="resumen-hora">Selecciona un horario</dd>
                        </dl>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Confirmar cita</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>
