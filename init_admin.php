<?php
/**
 * init_admin.php — ADM-01
 * Script de inicialización del primer administrador.
 *
 * EJECUCIÓN (solo desde terminal/CLI):
 *   php init_admin.php
 *
 * NUNCA debe estar dentro de public/.
 * Se niega a ejecutarse si ya existe un administrador.
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

// Verificar si ya existe un administrador (ADM-01)
$stmt = $db->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'administrador'");
$total = (int) $stmt->fetch()['total'];

if ($total > 0) {
    echo "⚠️  Ya existe al menos un administrador en el sistema.\n";
    echo "    Script bloqueado para evitar duplicados (ADM-01).\n";
    exit(0);
}

echo "=== Inicialización del Administrador — Clínica Veterinaria ===\n\n";

// Recoger datos
echo "Nombre completo: ";
$nombre = trim(fgets(STDIN));

echo "Correo electrónico: ";
$correo = trim(fgets(STDIN));

echo "Nombre de usuario: ";
$usuario = trim(fgets(STDIN));

// Leer contraseña sin mostrarla (solo funciona en sistemas Unix dentro del contenedor)
if (PHP_OS_FAMILY !== 'Windows') {
    echo "Contraseña: ";
    system('stty -echo');
    $contrasena = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
} else {
    // En Windows la contraseña se muestra (limitación del shell)
    echo "Contraseña (visible en Windows): ";
    $contrasena = trim(fgets(STDIN));
}

// Validaciones básicas
if (empty($nombre) || empty($correo) || empty($usuario) || empty($contrasena)) {
    echo "ERROR: Todos los campos son obligatorios.\n";
    exit(1);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo "ERROR: El correo electrónico no es válido.\n";
    exit(1);
}
if (strlen($contrasena) < 8) {
    echo "ERROR: La contraseña debe tener al menos 8 caracteres.\n";
    exit(1);
}

// Hash seguro
$hash = password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]);

// Insertar con prepared statement (SEC-01)
$insert = $db->prepare(
    'INSERT INTO usuarios (nombre, correo, usuario, contrasena, rol, activo)
     VALUES (:nombre, :correo, :usuario, :contrasena, :rol, 1)'
);

try {
    $insert->execute([
        ':nombre'     => $nombre,
        ':correo'     => $correo,
        ':usuario'    => $usuario,
        ':contrasena' => $hash,
        ':rol'        => 'administrador',
    ]);
} catch (PDOException $e) {
    echo "ERROR al insertar: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Administrador creado exitosamente.\n";
echo "   Usuario: $usuario\n";
echo "   Correo : $correo\n";
echo "\nPuedes iniciar sesión en la aplicación con estas credenciales.\n";
echo "Este script ya no podrá crear otro administrador.\n";
