<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use App\Support\Env;
use App\Support\Session;
use RuntimeException;

final class AuthService
{
    private const AVATAR_COLORS = [
        '#FF5A36', '#4F46E5', '#10B981', '#FFB800', '#EC4899', '#0EA5E9', '#8B5CF6',
    ];

    public static function currentUser(): ?array
    {
        $userId = Session::userId();
        if ($userId === null) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND status = ? LIMIT 1');
        $stmt->execute([$userId, 'active']);
        $user = $stmt->fetch();
        if (!$user) {
            Session::clearUser();
            return null;
        }

        self::ensureStatsRow((int) $user['id']);
        return $user;
    }

    public static function loginAsGuest(string $displayName): array
    {
        $cleanName = self::sanitizeDisplayName($displayName);
        if ($cleanName === '') {
            throw new RuntimeException('Ingresá un apodo de al menos 2 caracteres.');
        }

        $pdo = Database::connection();
        $now = Database::nowUtc();
        $color = self::AVATAR_COLORS[random_int(0, count(self::AVATAR_COLORS) - 1)];

        $stmt = $pdo->prepare(
            'INSERT INTO users (display_name, avatar_color, is_guest, role, status, created_at, updated_at, last_login_at)
             VALUES (?, ?, 1, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$cleanName, $color, 'player', 'active', $now, $now, $now]);
        $userId = (int) $pdo->lastInsertId();

        self::ensureStatsRow($userId);
        Session::setUserId($userId);

        return self::currentUser() ?? [];
    }

    public static function updateDisplayName(int $userId, string $displayName): string
    {
        $clean = self::sanitizeDisplayName($displayName);
        if (mb_strlen($clean, 'UTF-8') < 2) {
            throw new RuntimeException('El apodo debe tener al menos 2 caracteres.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET display_name = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$clean, Database::nowUtc(), $userId]);
        return $clean;
    }

    public static function isGoogleConfigured(): bool
    {
        return Env::get('GOOGLE_CLIENT_ID') !== '' && Env::get('GOOGLE_CLIENT_SECRET') !== '';
    }

    public static function googleAuthUrl(): string
    {
        if (!self::isGoogleConfigured()) {
            throw new RuntimeException('Google OAuth todavía no está configurado en el archivo .env.');
        }

        $state = bin2hex(random_bytes(16));
        Session::set('google_oauth_state', $state);

        $redirectUri = Env::get('GOOGLE_REDIRECT_URI');
        if ($redirectUri === '') {
            $redirectUri = rtrim(Env::get('APP_URL', 'http://127.0.0.1:8000'), '/') . '/auth/google/callback';
        }

        $params = [
            'client_id' => Env::get('GOOGLE_CLIENT_ID'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public static function handleGoogleCallback(string $code, string $state): array
    {
        $expectedState = (string) Session::get('google_oauth_state', '');
        Session::forget('google_oauth_state');

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new RuntimeException('Estado de autenticación inválido. Intentá nuevamente.');
        }

        $redirectUri = Env::get('GOOGLE_REDIRECT_URI');
        if ($redirectUri === '') {
            $redirectUri = rtrim(Env::get('APP_URL', 'http://127.0.0.1:8000'), '/') . '/auth/google/callback';
        }

        $tokenResponse = self::httpPost('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => Env::get('GOOGLE_CLIENT_ID'),
            'client_secret' => Env::get('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (empty($tokenResponse['access_token'])) {
            throw new RuntimeException('Google no devolvió un token válido.');
        }

        $userInfo = self::httpGet('https://openidconnect.googleapis.com/v1/userinfo', (string) $tokenResponse['access_token']);
        $sub = (string) ($userInfo['sub'] ?? '');
        if ($sub === '') {
            throw new RuntimeException('No se pudo obtener la identidad de Google.');
        }

        $email = isset($userInfo['email']) ? (string) $userInfo['email'] : null;
        $firstName = isset($userInfo['given_name']) ? (string) $userInfo['given_name'] : null;
        $lastName = isset($userInfo['family_name']) ? (string) $userInfo['family_name'] : null;
        $picture = isset($userInfo['picture']) ? (string) $userInfo['picture'] : null;
        $rawName = (string) ($userInfo['name'] ?? $firstName ?? 'Jugador');
        $displayName = self::sanitizeDisplayName($rawName) ?: 'Jugador';

        $pdo = Database::connection();
        $now = Database::nowUtc();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE google_sub = ? LIMIT 1');
        $stmt->execute([$sub]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $pdo->prepare(
                'UPDATE users SET email = ?, first_name = ?, last_name = ?, avatar_url = ?, is_guest = 0, updated_at = ?, last_login_at = ? WHERE id = ?'
            );
            $upd->execute([$email, $firstName, $lastName, $picture, $now, $now, $existing['id']]);
            Session::setUserId((int) $existing['id']);
            self::ensureStatsRow((int) $existing['id']);
            return self::currentUser() ?? [];
        }

        // Si el usuario estaba jugando como invitado en esta sesión, promovemos su cuenta para conservar sus estrellas/XP
        $current = self::currentUser();
        if ($current && (int) $current['is_guest'] === 1 && empty($current['google_sub'])) {
            $upd = $pdo->prepare(
                'UPDATE users SET google_sub = ?, email = ?, first_name = ?, last_name = ?, avatar_url = ?, is_guest = 0, updated_at = ?, last_login_at = ? WHERE id = ?'
            );
            $upd->execute([$sub, $email, $firstName, $lastName, $picture, $now, $now, $current['id']]);
            return self::currentUser() ?? [];
        }

        $color = self::AVATAR_COLORS[random_int(0, count(self::AVATAR_COLORS) - 1)];
        $ins = $pdo->prepare(
            'INSERT INTO users (google_sub, email, first_name, last_name, display_name, avatar_url, avatar_color, is_guest, role, status, created_at, updated_at, last_login_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$sub, $email, $firstName, $lastName, $displayName, $picture, $color, 'player', 'active', $now, $now, $now]);
        $newId = (int) $pdo->lastInsertId();
        self::ensureStatsRow($newId);
        Session::setUserId($newId);

        return self::currentUser() ?? [];
    }

    public static function ensureStatsRow(int $userId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT user_id FROM player_stats WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            return;
        }
        $now = Database::nowUtc();
        $ins = $pdo->prepare('INSERT INTO player_stats (user_id, updated_at) VALUES (?, ?)');
        $ins->execute([$userId, $now]);
    }

    public static function sanitizeDisplayName(string $raw): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');
        $clean = mb_substr($clean, 0, 28, 'UTF-8');
        return $clean;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private static function httpPost(string $url, array $params): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Error de conexión con Google: ' . $err);
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function httpGet(string $url, string $bearerToken): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearerToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Error al consultar perfil de Google: ' . $err);
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
