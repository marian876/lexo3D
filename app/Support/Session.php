<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Manejo de sesión segura, CSRF y usuario autenticado / invitado.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name('lexo_sid');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30, // 30 días para conservar sesión móvil
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (PHP_SAPI !== 'cli') {
            session_start();
        } elseif (!isset($_SESSION)) {
            $_SESSION = [];
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfToken(): string
    {
        self::start();
        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        if ($token === null || $token === '') {
            return false;
        }
        return hash_equals((string) $_SESSION['csrf_token'], $token);
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function setUserId(int $userId): void
    {
        self::start();
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
    }

    public static function clearUser(): void
    {
        self::start();
        unset($_SESSION['user_id']);
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        self::start();
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }
}
