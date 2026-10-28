<?php
/**
 * Panel / Agenda del Veterinario — ACC-01, ARQ-01, US-06
 * @var string                     $nombre
 * @var string                     $rol
 * @var string                     $correo
 * @var array<string,mixed>        $agenda        veterinario, citas, truncado
 * @var array<array<string,mixed>> $citas         Citas Programadas del veterinario
 * @var string|null                $flashError
 * @var string|null                $flashSuccess
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$horaRango = static function (string $hora, int $min): string {
    [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));
    $fin = $h * 60 + $m + $min;
    return sprintf('%02d:%02d – %02d:%02d', $h, $m, intdiv($fin, 60) % 24, $fin % 60);
};
$sinPerfil = $agenda['veterinario'] === null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Agenda y panel de atención veterinaria">
    <title>Agenda y Atención Veterinaria — Clínica Veterinaria</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/consultas.css">
</head>
<body class="dashboard-page">

<?php $navActivo = 'agenda'; include __DIR__ . '/../partials/navbar.php'; ?>

<main class="container mt-2">

    <div id="alert-container" aria-live="polite">
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error" role="alert">
                <span aria-hidden="true">⚠️</span>
                <span><?= $e($flashError) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success" role="status">
                <span aria-hidden="true">✅</span>
                <span><?= $e($flashSuccess) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Mi agenda</h2>
        <p>Bienvenido, Dr(a). <strong><?= $nombre ?></strong> (<?= $correo ?>).
           Estas son tus citas Programadas, de la más próxima a la más lejana.</p>

        <?php if ($sinPerfil): ?>
            <div class="alert alert-warning mt-1" role="status">
                <span aria-hidden="true">⚠️</span>
                <span>Tu cuenta no tiene un perfil de veterinario activo asociado, por eso no hay citas que mostrar. Consulta con el administrador.</span>
            </div>
        <?php elseif (empty($citas)): ?>
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">📅</div>
                <h3>No tienes citas programadas</h3>
                <p>Cuando el administrador agende una cita contigo, aparecerá aquí.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive mt-2">
                <table class="table" id="agenda-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Mascota</th>
                            <th>Servicio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $c): ?>
                            <tr>
                                <td><?= $e(date('d/m/Y', (int) strtotime((string) $c['fecha_cita']))) ?></td>
                                <td><?= $e($horaRango((string) $c['hora_cita'], (int) $c['duracion_estimada_min'])) ?></td>
                                <td>
                                    <strong>🐾 <?= $e($c['mascota_nombre']) ?></strong> (<?= $e($c['mascota_especie']) ?>)
                                    <br><small class="text-muted"><?= $e($c['propietario_nombre']) ?></small>
                                </td>
                                <td><?= $e($c['nombre_servicio']) ?></td>
                                <td>
                                    <a href="/consultas/crear?id_cita=<?= (int) $c['id_cita'] ?>" class="btn btn-sm btn-primary">Registrar consulta</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($agenda['truncado'])): ?>
                <p class="text-muted mt-2">Se muestran las primeras <?= (int) \App\Services\ConsultaService::LIMITE_AGENDA ?> citas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
