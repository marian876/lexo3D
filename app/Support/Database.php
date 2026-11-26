<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión PDO unificada compatible con MySQL (Hostinger) y SQLite (local/pruebas).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';
    private static string $rootPath = '';

    public static function init(string $rootPath): void
    {
        self::$rootPath = rtrim($rootPath, '/');
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if (self::$rootPath === '') {
            self::$rootPath = dirname(__DIR__, 2);
        }

        $driver = strtolower(Env::get('DB_DRIVER', 'sqlite'));
        self::$driver = $driver === 'mysql' ? 'mysql' : 'sqlite';

        try {
            if (self::$driver === 'mysql') {
                $host = Env::get('DB_HOST', 'localhost');
                $port = Env::get('DB_PORT', '3306');
                $db   = Env::get('DB_DATABASE', 'lexo');
                $user = Env::get('DB_USERNAME', 'root');
                $pass = Env::get('DB_PASSWORD', '');

                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                $relPath = Env::get('DB_SQLITE_PATH', 'storage/lexo.sqlite');
                $dbPath = str_starts_with($relPath, '/')
                    ? $relPath
                    : self::$rootPath . '/' . $relPath;

                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $isNew = !is_file($dbPath) || filesize($dbPath) === 0;
                self::$pdo = new PDO('sqlite:' . $dbPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
                self::$pdo->exec('PRAGMA journal_mode = WAL;');

                if ($isNew) {
                    self::bootstrapSqliteSchema(self::$pdo);
                }
            }
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    public static function setConnectionForTests(PDO $pdo, string $driver = 'sqlite'): void
    {
        self::$pdo = $pdo;
        self::$driver = $driver;
    }

    private static function bootstrapSqliteSchema(PDO $pdo): void
    {
        $schemaFile = self::$rootPath . '/database/schema.sqlite.sql';
        if (!is_file($schemaFile)) {
            return;
        }
        $sql = file_get_contents($schemaFile);
        if ($sql !== false) {
            $pdo->exec($sql);
        }
    }

    public static function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
