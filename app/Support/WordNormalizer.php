<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Normalización léxica en español.
 * Regla crítica: conserva la Ñ/ñ como letra distinta de la N/n y normaliza tildes/diéresis
 * para evitar duplicados como "árbol" / "ARBOL".
 */
final class WordNormalizer
{
    public static function cleanDisplay(string $raw): string
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        return mb_strtoupper($trimmed, 'UTF-8');
    }

    public static function normalizeKey(string $raw): string
    {
        $upper = self::cleanDisplay($raw);
        $map = [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        ];
        // IMPORTANTE: Ñ permanece Ñ, nunca se convierte en N.
        return strtr($upper, $map);
    }

    /**
     * Valida que la cadena contenga solo letras españolas válidas y cumpla longitud mínima.
     *
     * @return array{ok: bool, display: string, normalized: string, length: int, error: ?string}
     */
    public static function validateFormat(string $raw, int $minLength = 3, int $maxLength = 32): array
    {
        $display = self::cleanDisplay($raw);
        if ($display === '') {
            return [
                'ok' => false,
                'display' => '',
                'normalized' => '',
                'length' => 0,
                'error' => 'Escribí una palabra antes de enviar.',
            ];
        }

        if (preg_match('/\s/u', $display) === 1) {
            return [
                'ok' => false,
                'display' => $display,
                'normalized' => '',
                'length' => 0,
                'error' => 'Ingresá una sola palabra sin espacios.',
            ];
        }

        if (preg_match('/^[A-ZÁÉÍÓÚÜÑ]+$/u', $display) !== 1) {
            return [
                'ok' => false,
                'display' => $display,
                'normalized' => '',
                'length' => 0,
                'error' => 'Usá únicamente letras españolas (sin números ni símbolos).',
            ];
        }

        $length = mb_strlen($display, 'UTF-8');
        if ($length < $minLength) {
            return [
                'ok' => false,
                'display' => $display,
                'normalized' => '',
                'length' => $length,
                'error' => "La palabra debe tener al menos {$minLength} letras.",
            ];
        }

        if ($length > $maxLength) {
            return [
                'ok' => false,
                'display' => $display,
                'normalized' => '',
                'length' => $length,
                'error' => "La palabra no puede superar las {$maxLength} letras.",
            ];
        }

        return [
            'ok' => true,
            'display' => $display,
            'normalized' => self::normalizeKey($display),
            'length' => $length,
            'error' => null,
        ];
    }
}
