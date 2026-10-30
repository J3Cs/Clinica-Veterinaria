<?php
/**
 * init_veterinario.php — US-06 (apoyo de desarrollo)
 * Crea un veterinario con acceso al sistema, o cambia la contraseña de uno existente
 * (por ejemplo, las contraseñas iniciales de los veterinarios de
 * `database/seeds/dev_citas_seed.sql`, que son conocidas y solo sirven para desarrollo).
 *
 * Existe porque aún no hay pantallas para gestionar veterinarios (US-09/US-11).
 *
 * EJECUCIÓN (solo desde terminal/CLI, en tu equipo con acceso a la base de datos):
 *   php init_veterinario.php
 *
 * NUNCA debe estar dentro de public/.
 */

// Bloquear acceso web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado. Este script solo puede ejecutarse desde la terminal.\n";
    exit(1);
}

// Autoloader
spl_autoload_register(function (string $class) {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/src/app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Config\Database;

// Cargar variables de entorno desde .env si existe (soporte XAMPP sin Docker)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    echo "ERROR: No se pudo conectar a la base de datos.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

/** Lee una línea de la entrada estándar. */
function preguntar(string $texto): string {
    echo $texto;
    return trim((string) fgets(STDIN));
}

/** Lee una contraseña (oculta en sistemas Unix; visible en Windows). */
function preguntarContrasena(string $texto): string {
    if (PHP_OS_FAMILY !== 'Windows') {
        echo $texto;
        system('stty -echo 2>/dev/null');
        $valor = trim((string) fgets(STDIN));
        system('stty echo 2>/dev/null');
        echo "\n";
        return $valor;
    }
    return preguntar($texto . '(visible en Windows): ');
}

echo "=== Veterinario con acceso — Clínica Veterinaria ===\n\n";

$usuario = preguntar('Nombre de usuario: ');
if ($usuario === '') {
    echo "ERROR: El nombre de usuario es obligatorio.\n";
    exit(1);
}

$stmt = $db->prepare('SELECT id_usuario, nombre, rol, activo FROM usuarios WHERE usuario = :u LIMIT 1');
$stmt->execute([':u' => $usuario]);
$existente = $stmt->fetch();

$nombre = $correo = null;

if ($existente) {
    if ($existente['rol'] !== 'veterinario') {
        echo "ERROR: El usuario '$usuario' existe pero no es veterinario (rol: {$existente['rol']}).\n";
        exit(1);
    }
    if ((int) $existente['activo'] !== 1) {
        echo "ERROR: El usuario '$usuario' está inactivo. Actívalo antes de asignarle contraseña.\n";
        exit(1);
    }
    echo "Se encontró al veterinario: {$existente['nombre']}. Se le asignará una nueva contraseña.\n";
} else {
    echo "El usuario no existe: se creará un veterinario nuevo.\n";
    $nombre = preguntar('Nombre completo: ');
    $correo = preguntar('Correo electrónico: ');
    if ($nombre === '' || $correo === '') {
        echo "ERROR: El nombre y el correo son obligatorios.\n";
        exit(1);
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo "ERROR: El correo electrónico no es válido.\n";
        exit(1);
    }
}

$contrasena = preguntarContrasena('Contraseña (mínimo 8 caracteres): ');
if (strlen($contrasena) < 8) {
    echo "ERROR: La contraseña debe tener al menos 8 caracteres.\n";
    exit(1);
}

$hash = password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $db->beginTransaction();

    if ($existente) {
        $idUsuario = (int) $existente['id_usuario'];
        $db->prepare('UPDATE usuarios SET contrasena = :h WHERE id_usuario = :id')
           ->execute([':h' => $hash, ':id' => $idUsuario]);
    } else {
        $db->prepare(
            'INSERT INTO usuarios (nombre, correo, usuario, contrasena, rol, activo)
             VALUES (:nombre, :correo, :usuario, :contrasena, :rol, 1)'
        )->execute([
            ':nombre'     => $nombre,
            ':correo'     => $correo,
            ':usuario'    => $usuario,
            ':contrasena' => $hash,
            ':rol'        => 'veterinario',
        ]);
        $idUsuario = (int) $db->lastInsertId();
    }

    // Perfil en `veterinarios` (si falta)
    $db->prepare('INSERT IGNORE INTO veterinarios (id_usuario) VALUES (:id)')->execute([':id' => $idUsuario]);

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR al guardar: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Listo. El veterinario '$usuario' ya puede iniciar sesión.\n";
echo "   Su agenda estará en /veterinario/agenda.\n";
