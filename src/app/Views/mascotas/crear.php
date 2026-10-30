<?php
/**
 * Formulario de Alta de Mascota — US-03, UI-01, DAT-01, SEC-01
 *
 * @var string $csrfToken
 * @var string|null $flashError
 * @var string|null $flashSuccess
 * @var array<string,string> $fieldErrors
 * @var array<string,string> $oldData
 * @var array<array<string,mixed>> $propietarios
 * @var string $nombreUsuario
 * @var string $rolUsuario
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro de mascotas de pacientes veterinarios">
    <title>Registrar Mascota — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<!-- Barra de navegación superior -->
<?php $navActivo = 'mascotas'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card">
        <div class="card-header-actions">
            <div>
                <h2>Registrar Nueva Mascota</h2>
                <p class="text-muted">Ingresa los datos de la mascota vinculándola a su propietario para abrir su expediente clínico.</p>
            </div>
            <div>
                <a href="/mascotas" class="btn btn-sm btn-outline-dark">&larr; Volver a la lista</a>
            </div>
        </div>

        <!-- Contenedor accesible para notificaciones (concordante con login) -->
        <div id="alert-container" aria-live="polite">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-error" role="alert" id="server-error-msg">
                    <span aria-hidden="true">⚠️</span>
                    <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success" role="status" id="server-success-msg">
                    <span aria-hidden="true">✅</span>
                    <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- Alerta dinámica gestionada por JavaScript -->
            <div id="js-error-container" class="alert alert-warning" role="alert" style="display: none;">
                <span aria-hidden="true">ℹ️</span>
                <span id="js-error-text"></span>
            </div>
        </div>

        <?php if (empty($propietarios)): ?>
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">⚠️</div>
                <h3>No hay propietarios activos disponibles</h3>
                <p>Para registrar una mascota, primero debes registrar al menos un propietario en el sistema (DAT-01).</p>
                <a href="/propietarios/crear" class="btn btn-primary">+ Registrar Propietario Ahora</a>
            </div>
        <?php else: ?>

            <form id="form-mascota" method="POST" action="/mascotas" novalidate autocomplete="off">
                <!-- Token CSRF obligatorio (SEC-01) -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Propietario Responsable (DAT-01) -->
                <div class="form-group">
                    <label for="id_propietario">Propietario Responsable <span class="text-danger">*</span></label>
                    <select
                        id="id_propietario"
                        name="id_propietario"
                        class="form-control <?= isset($fieldErrors['id_propietario']) ? 'is-invalid' : '' ?>"
                        required
                        aria-required="true"
                        aria-describedby="propietario-hint <?= isset($fieldErrors['id_propietario']) ? 'propietario-error' : '' ?>"
                    >
                        <option value="">-- Selecciona el propietario responsable --</option>
                        <?php foreach ($propietarios as $prop): ?>
                            <?php
                                $selected = (isset($oldData['id_propietario']) && (int)$oldData['id_propietario'] === (int)$prop['id_propietario']) ? 'selected' : '';
                            ?>
                            <option value="<?= (int)$prop['id_propietario'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($prop['nombre_completo'], ENT_QUOTES, 'UTF-8') ?> (DUI: <?= htmlspecialchars($prop['dui'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="propietario-hint" class="form-hint">Selecciona el dueño responsable previamente registrado en el sistema.</small>
                    <?php if (isset($fieldErrors['id_propietario'])): ?>
                        <span id="propietario-error" class="invalid-feedback"><?= htmlspecialchars($fieldErrors['id_propietario'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-grid">
                    <!-- Nombre de la Mascota -->
                    <div class="form-group">
                        <label for="nombre">Nombre de la Mascota <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            class="form-control <?= isset($fieldErrors['nombre']) ? 'is-invalid' : '' ?>"
                            placeholder="Ej. Rocky, Luna, Michi..."
                            value="<?= htmlspecialchars($oldData['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="100"
                            aria-required="true"
                            aria-describedby="nombre-hint <?= isset($fieldErrors['nombre']) ? 'nombre-error' : '' ?>"
                        >
                        <small id="nombre-hint" class="form-hint">Nombre con el que se identifica al paciente.</small>
                        <?php if (isset($fieldErrors['nombre'])): ?>
                            <span id="nombre-error" class="invalid-feedback"><?= htmlspecialchars($fieldErrors['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Especie -->
                    <div class="form-group">
                        <label for="especie">Especie <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="especie"
                            name="especie"
                            list="especies-sugeridas"
                            class="form-control <?= isset($fieldErrors['especie']) ? 'is-invalid' : '' ?>"
                            placeholder="Ej. Canino, Felino, Ave..."
                            value="<?= htmlspecialchars($oldData['especie'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="50"
                            aria-required="true"
                            aria-describedby="especie-hint <?= isset($fieldErrors['especie']) ? 'especie-error' : '' ?>"
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
                        <small id="especie-hint" class="form-hint">Especie biológica del animal.</small>
                        <?php if (isset($fieldErrors['especie'])): ?>
                            <span id="especie-error" class="invalid-feedback"><?= htmlspecialchars($fieldErrors['especie'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Raza -->
                    <div class="form-group">
                        <label for="raza">Raza <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="raza"
                            name="raza"
                            class="form-control <?= isset($fieldErrors['raza']) ? 'is-invalid' : '' ?>"
                            placeholder="Ej. Labrador, Mestizo / Criollo, Siamés..."
                            value="<?= htmlspecialchars($oldData['raza'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="50"
                            aria-required="true"
                            aria-describedby="raza-hint <?= isset($fieldErrors['raza']) ? 'raza-error' : '' ?>"
                        >
                        <small id="raza-hint" class="form-hint">Indica "Mestizo / Criollo" si se desconoce la raza pura.</small>
                        <?php if (isset($fieldErrors['raza'])): ?>
                            <span id="raza-error" class="invalid-feedback"><?= htmlspecialchars($fieldErrors['raza'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Sexo -->
                    <div class="form-group">
                        <label for="sexo">Sexo <span class="text-danger">*</span></label>
                        <select
                            id="sexo"
                            name="sexo"
                            class="form-control <?= isset($fieldErrors['sexo']) ? 'is-invalid' : '' ?>"
                            required
                            aria-required="true"
                            aria-describedby="sexo-hint <?= isset($fieldErrors['sexo']) ? 'sexo-error' : '' ?>"
                        >
                            <option value="">-- Selecciona el sexo --</option>
                            <option value="Macho" <?= (isset($oldData['sexo']) && $oldData['sexo'] === 'Macho') ? 'selected' : '' ?>>Macho</option>
                            <option value="Hembra" <?= (isset($oldData['sexo']) && $oldData['sexo'] === 'Hembra') ? 'selected' : '' ?>>Hembra</option>
                        </select>
                        <small id="sexo-hint" class="form-hint">Sexo biológico de la mascota.</small>
                        <?php if (isset($fieldErrors['sexo'])): ?>
                            <span id="sexo-error" class="invalid-feedback"><?= htmlspecialchars($fieldErrors['sexo'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Fecha de Nacimiento (Opcional) -->
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento <span class="text-muted">(Opcional)</span></label>
                        <input
                            type="date"
                            id="fecha_nacimiento"
                            name="fecha_nacimiento"
                            class="form-control <?= isset($fieldErrors['fecha_nacimiento']) ? 'is-invalid' : '' ?>"
                            max="<?= date('Y-m-d') ?>"
                            value="<?= htmlspecialchars($oldData['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            aria-describedby="fecha-nac-hint <?= isset($fieldErrors['fecha_nacimiento']) ? 'fecha-nac-error' : '' ?>"
                        >
                        <small id="fecha-nac-hint" class="form-hint">No puede ser una fecha futura.</small>
                        <?php if (isset($fieldErrors['fecha_nacimiento'])): ?>
                            <span id="fecha-nac-error" class="invalid-feedback"><?= htmlspecialchars($fieldErrors['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Características Relevantes (Opcional) -->
                <div class="form-group">
                    <label for="caracteristicas_relevantes">Características Relevantes / Señas Particulares <span class="text-muted">(Opcional)</span></label>
                    <textarea
                        id="caracteristicas_relevantes"
                        name="caracteristicas_relevantes"
                        class="form-control"
                        rows="3"
                        placeholder="Color de pelaje, marcas especiales, manchas, cicatrices, esterilizado/a, temperamento habitual..."
                        aria-describedby="caract-hint"
                    ><?= htmlspecialchars($oldData['caracteristicas_relevantes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <small id="caract-hint" class="form-hint">Detalles físicos o de temperamento para facilitar su identificación.</small>
                </div>

                <div class="form-actions mt-2">
                    <button type="submit" id="btn-submit-mascota" class="btn btn-primary">
                        🐾 Guardar Mascota
                    </button>
                    <a href="/mascotas" class="btn btn-outline-dark">
                        Cancelar
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script src="/assets/js/mascotas.js?v=<?= time() ?>"></script>
</body>
</html>
