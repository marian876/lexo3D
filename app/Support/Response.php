<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Utilidades de respuesta HTTP, JSON y escape seguro de HTML.
 */
final class Response
{
    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
