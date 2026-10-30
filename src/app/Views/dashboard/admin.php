<?php
/**
 * Panel del Administrador — ACC-01, ARQ-01, UI-01
 * @var string $nombre
 * @var string $rol
 * @var string $correo
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panel de administración de la Clínica Veterinaria">
    <title>Panel de Administración — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/modulos.css?v=<?= time() ?>">
</head>
<body class="dashboard-page">

<?php $navActivo = 'dashboard'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">
    <div class="card card-modulo">
        <div class="card-header-actions">
            <div>
                <h2>Panel de Administración Clínica</h2>
                <p class="text-muted">
                    Bienvenido, <strong><?= $nombre ?></strong> (<?= $correo ?>) &middot; Rol activo: <strong>Administrador</strong>.
                </p>
            </div>
            <div>
                <a href="/citas" class="btn btn-primary">+ Agendar Nueva Cita</a>
            </div>
        </div>

        <div class="alert alert-info">
            <span>Selecciona cualquiera de las tarjetas de módulos clínicos y administrativos para consultar registros o crear nuevas fichas.</span>
        </div>

        <div class="dashboard-modules-grid">
            <!-- Módulo Propietarios -->
            <div class="module-quick-card">
                <div class="module-quick-header">
                    <span class="module-quick-icon" aria-hidden="true">👥</span>
                    <div>
                        <div class="module-quick-title">Directorio de Propietarios</div>
                        <p class="module-quick-desc">Gestión de titulares responsables, DUI único, datos de contacto y mascotas vinculadas.</p>
                    </div>
                </div>
                <div class="module-quick-actions">
                    <a href="/propietarios" class="btn btn-sm btn-primary">Ver Propietarios</a>
                    <a href="/propietarios/crear" class="btn btn-sm btn-outline-dark">+ Nuevo Propietario</a>
                </div>
            </div>

            <!-- Módulo Mascotas -->
            <div class="module-quick-card">
                <div class="module-quick-header">
                    <span class="module-quick-icon" aria-hidden="true">🐾</span>
                    <div>
                        <div class="module-quick-title">Expedientes de Mascotas</div>
                        <p class="module-quick-desc">Fichas de pacientes, especie, raza, propietario asignado e historial clínico consolidado.</p>
                    </div>
                </div>
                <div class="module-quick-actions">
                    <a href="/mascotas" class="btn btn-sm btn-primary">Ver Mascotas</a>
                    <a href="/mascotas/crear" class="btn btn-sm btn-outline-dark">+ Nueva Mascota</a>
                </div>
            </div>

            <!-- Módulo Citas -->
            <div class="module-quick-card">
                <div class="module-quick-header">
                    <span class="module-quick-icon" aria-hidden="true">📅</span>
                    <div>
                        <div class="module-quick-title">Agenda y Control de Citas</div>
                        <p class="module-quick-desc">Consulta de disponibilidad horaria en tiempo real, reserva de citas y cancelaciones.</p>
                    </div>
                </div>
                <div class="module-quick-actions">
                    <a href="/citas" class="btn btn-sm btn-primary">Agendar Cita</a>
                    <a href="/citas#listado" class="btn btn-sm btn-outline-dark">Listado de Citas</a>
                </div>
            </div>

            <!-- Módulo Veterinarios -->
            <div class="module-quick-card">
                <div class="module-quick-header">
                    <span class="module-quick-icon" aria-hidden="true">🩺</span>
                    <div>
                        <div class="module-quick-title">Personal Veterinario</div>
                        <p class="module-quick-desc">Registro de médicos veterinarios, especialidades, licencias y cuentas de acceso.</p>
                    </div>
                </div>
                <div class="module-quick-actions">
                    <a href="/veterinarios" class="btn btn-sm btn-primary">Ver Veterinarios</a>
                    <a href="/veterinarios/crear" class="btn btn-sm btn-outline-dark">+ Registrar Veterinario</a>
                </div>
            </div>

            <!-- Módulo Servicios -->
            <div class="module-quick-card">
                <div class="module-quick-header">
                    <span class="module-quick-icon" aria-hidden="true">💉</span>
                    <div>
                        <div class="module-quick-title">Catálogo de Servicios</div>
                        <p class="module-quick-desc">Configuración de servicios clínicos, duración estimada en minutos y estado en agenda.</p>
                    </div>
                </div>
                <div class="module-quick-actions">
                    <a href="/servicios" class="btn btn-sm btn-primary">Ver Catálogo</a>
                    <a href="/servicios/crear" class="btn btn-sm btn-outline-dark">+ Nuevo Servicio</a>
                </div>
            </div>

            <!-- Módulo Horarios -->
            <div class="module-quick-card">
                <div class="module-quick-header">
                    <span class="module-quick-icon" aria-hidden="true">🕒</span>
                    <div>
                        <div class="module-quick-title">Horarios de Atención</div>
                        <p class="module-quick-desc">Definición de días y bloques horarios de atención por cada médico veterinario.</p>
                    </div>
                </div>
                <div class="module-quick-actions">
                    <a href="/horarios" class="btn btn-sm btn-primary">Ver Horarios</a>
                    <a href="/horarios/crear" class="btn btn-sm btn-outline-dark">+ Asignar Horario</a>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>
