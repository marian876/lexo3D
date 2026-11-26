<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Cargador minimalista de archivos .env sin dependencias externas (ideal para Hostinger).
 */
final class Env
{
    private static bool $loaded = false;
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $rootPath): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $envFile = rtrim($rootPath, '/') . '/.env';
        if (!is_file($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $eqPos = strpos($trimmed, '=');
            if ($eqPos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $eqPos));
            $val = trim(substr($trimmed, $eqPos + 1));

            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            self::$values[$key] = $val;
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }
        $env = getenv($key);
        if ($env !== false) {
            return (string) $env;
        }
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $val = strtolower(self::get($key, $default ? 'true' : 'false'));
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }
}
