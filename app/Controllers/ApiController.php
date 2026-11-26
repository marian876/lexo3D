<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\MatchService;
use App\Services\RoomService;
use App\Support\Response;
use App\Support\Session;

final class ApiController
{
    /**
     * @param array<string, mixed> $config
     */
    public static function roomState(array $config): never
    {
        $user = AuthService::currentUser();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'Sesión no iniciada.'], 401);
        }

        $code = (string) ($_GET['code'] ?? '');
        if ($code === '') {
            Response::json(['ok' => false, 'error' => 'Falta el código de sala.'], 400);
        }

        try {
            $state = RoomService::getFullState($code, (int) $user['id'], $config);
            Response::json(['ok' => true, 'data' => $state]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function startMatch(array $config): never
    {
        $user = self::requireAuthJson();
        self::requireCsrfJson();
        $body = self::jsonBody();

        try {
            $room = RoomService::findByCode((string) ($body['code'] ?? ''));
            if (!$room) {
                Response::json(['ok' => false, 'error' => 'Sala no encontrada.'], 404);
            }
            $match = MatchService::startMatch((int) $room['id'], (int) $user['id'], $config);
            Response::json(['ok' => true, 'match' => $match]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function submitWord(array $config): never
    {
        $user = self::requireAuthJson();
        self::requireCsrfJson();
        $body = self::jsonBody();

        $roundId = (int) ($body['round_id'] ?? 0);
        $word = (string) ($body['word'] ?? '');
        if ($roundId <= 0) {
            Response::json(['ok' => false, 'error' => 'Ronda inválida.'], 400);
        }

        try {
            $saved = MatchService::submitWord($roundId, (int) $user['id'], $word, $config);
            Response::json(['ok' => true, 'word' => $saved]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public static function nextRound(): never
    {
        $user = self::requireAuthJson();
        self::requireCsrfJson();
        $body = self::jsonBody();

        $matchId = (int) ($body['match_id'] ?? 0);
        try {
            $round = MatchService::startNextRound($matchId, (int) $user['id']);
            Response::json(['ok' => true, 'round' => $round]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function toggleWord(array $config): never
    {
        $user = self::requireAuthJson();
        self::requireCsrfJson();
        $body = self::jsonBody();

        try {
            MatchService::toggleWordRejection(
                (int) ($body['round_id'] ?? 0),
                (int) ($body['word_id'] ?? 0),
                (int) $user['id'],
                $config
            );
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public static function rematch(): never
    {
        $user = self::requireAuthJson();
        self::requireCsrfJson();
        $body = self::jsonBody();

        try {
            $room = RoomService::findByCode((string) ($body['code'] ?? ''));
            if (!$room) {
                Response::json(['ok' => false, 'error' => 'Sala no encontrada.'], 404);
            }
            MatchService::rematchToLobby((int) $room['id'], (int) $user['id']);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private static function requireAuthJson(): array
    {
        $user = AuthService::currentUser();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'Sesión expirada.'], 401);
        }
        return $user;
    }

    private static function requireCsrfJson(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        if (!Session::verifyCsrf(is_string($token) ? $token : null)) {
            Response::json(['ok' => false, 'error' => 'Token CSRF inválido. Recargá la página.'], 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }
}
