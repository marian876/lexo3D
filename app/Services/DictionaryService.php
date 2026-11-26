<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\WordNormalizer;

/**
 * Servicio léxico local en español.
 * Transparente: si se selecciona modo diccionario y el archivo falta, informa el estado
 * sin simular una validación exitosa (lexo.md §9.3).
 */
final class DictionaryService
{
    /** @var array<string, true>|null */
    private static ?array $words = null;
    private static string $rootPath = '';

    public static function init(string $rootPath): void
    {
        self::$rootPath = rtrim($rootPath, '/');
        self::$words = null;
    }

    private static function dictionaryPath(): string
    {
        $base = self::$rootPath !== '' ? self::$rootPath : dirname(__DIR__, 2);
        return $base . '/database/words_es.txt';
    }

    /**
     * @return array<string, true>
     */
    private static function load(): array
    {
        if (self::$words !== null) {
            return self::$words;
        }

        self::$words = [];
        $path = self::dictionaryPath();
        if (!is_file($path) || !is_readable($path)) {
            return self::$words;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return self::$words;
        }

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $key = WordNormalizer::normalizeKey($trimmed);
            if ($key !== '') {
                self::$words[$key] = true;
            }
        }
        fclose($handle);

        return self::$words;
    }

    public static function isAvailable(): bool
    {
        return count(self::load()) > 0;
    }

    public static function wordCount(): int
    {
        return count(self::load());
    }

    /**
     * Comprueba si la palabra normalizada existe en el diccionario español cargado.
     */
    public static function exists(string $normalizedWord): bool
    {
        $dict = self::load();
        if (isset($dict[$normalizedWord])) {
            return true;
        }

        // Tolerancia básica de plural regular en español (-s o -es) si la raíz existe
        $len = mb_strlen($normalizedWord, 'UTF-8');
        if ($len > 3 && str_ends_with($normalizedWord, 'S')) {
            $singularS = mb_substr($normalizedWord, 0, $len - 1, 'UTF-8');
            if (isset($dict[$singularS])) {
                return true;
            }
        }
        if ($len > 4 && str_ends_with($normalizedWord, 'ES')) {
            $singularEs = mb_substr($normalizedWord, 0, $len - 2, 'UTF-8');
            if (isset($dict[$singularEs])) {
                return true;
            }
        }

        return false;
    }
}
