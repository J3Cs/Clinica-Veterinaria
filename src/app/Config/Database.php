<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Si está en Docker usa 'db', si está en XAMPP usa '127.0.0.1' con puerto 3307 o 3306
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3307';
            $db   = getenv('DB_NAME') ?: 'clinica_veterinaria';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Si falla en 3307 y no se especificó DB_PORT, intentar puerto estándar 3306
                if (!getenv('DB_PORT') && $port === '3307') {
                    try {
                        $dsnFallback = "mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4";
                        self::$instance = new PDO($dsnFallback, $user, $pass, [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                        ]);
                        return self::$instance;
                    } catch (PDOException $eFallback) {
                        // Continuar para lanzar la excepción original
                    }
                }
                throw new \Exception("Error de conexión a la Base de Datos: " . $e->getMessage() . "<br><small>Verifica si en XAMPP creaste la base de datos 'clinica_veterinaria' o si en Docker los contenedores están corriendo.</small>");
            }
        }
        return self::$instance;
    }
}
