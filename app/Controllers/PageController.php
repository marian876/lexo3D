<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DictionaryService;
use App\Services\MatchService;
use App\Services\RoomService;
use App\Services\StatsService;
use App\Support\Response;
use App\Support\Session;

final class PageController
{
    /**
     * @param array<string, mixed> $config
     */
    public static function home(array $config): void
    {
        $user = AuthService::currentUser();
        $profile = $user ? StatsService::getProfileSummary((int) $user['id'], $config) : null;
        $prefillCode = RoomService::normalizeCode((string) ($_GET['code'] ?? ''));

        self::render('home', [
            'title' => 'LEXO — El desafío de las palabras',
            'user' => $user,
            'profile' => $profile,
            'prefillCode' => $prefillCode,
            'googleReady' => AuthService::isGoogleConfigured(),
            'dictReady' => DictionaryService::isAvailable(),
            'dictCount' => DictionaryService::wordCount(),
            'config' => $config,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createRoom(array $config): void
    {
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'Token inválido. Intentá nuevamente.');
            Response::redirect('/');
        }

        $user = AuthService::currentUser();
        try {
            if (!$user) {
                $displayName = (string) ($_POST['display_name'] ?? '');
                $user = AuthService::loginAsGuest($displayName);
            }
            $room = RoomService::createRoom((int) $user['id'], $_POST, $config);
            Response::redirect('/sala?code=' . urlencode((string) $room['code']));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/');
        }
    }

    /**
     * Inicia una sesión individual de práctica (1 jugador, sin sala de espera).
     *
     * @param array<string, mixed> $config
     */
    public static function startPractice(array $config): void
    {
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'Token inválido. Intentá nuevamente.');
            Response::redirect('/');
        }

        $user = AuthService::currentUser();
        if (!$user) {
            Session::flash('error', 'Ingresá con tu apodo o con Google para iniciar el modo práctica.');
            Response::redirect('/');
        }

        try {
            $input = [
                'name' => 'Modo práctica',
                'is_practice' => true,
                'round_count' => 1,
                'round_duration_seconds' => $_POST['round_duration_seconds'] ?? $config['room']['default_round_duration'],
                'scoring_mode' => $_POST['scoring_mode'] ?? $config['room']['default_scoring_mode'],
                'validation_mode' => $_POST['validation_mode'] ?? $config['room']['default_validation_mode'],
                'min_word_length' => $_POST['min_word_length'] ?? $config['room']['default_min_word_length'],
            ];
            $room = RoomService::createRoom((int) $user['id'], $input, $config);
            MatchService::startMatch((int) $room['id'], (int) $user['id'], $config);
            Response::redirect('/sala?code=' . urlencode((string) $room['code']));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/');
        }
    }

    public static function joinRoom(): void
    {
        $rawCode = (string) ($_POST['code'] ?? $_GET['code'] ?? '');
        $normCode = RoomService::normalizeCode($rawCode);
        if ($normCode === '') {
            Session::flash('error', 'Ingresá un código de sala válido.');
            Response::redirect('/');
        }

        $user = AuthService::currentUser();
        if (!$user) {
            // Si llegó por QR sin sesión, prellenamos el código en inicio para entrada en 1 toque
            Response::redirect('/?code=' . urlencode($normCode));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'Token inválido. Intentá nuevamente.');
            Response::redirect('/');
        }

        try {
            $room = RoomService::joinRoom((int) $user['id'], $normCode);
            Response::redirect('/sala?code=' . urlencode((string) $room['code']));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/?code=' . urlencode($normCode));
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function room(array $config): void
    {
        $user = AuthService::currentUser();
        $code = RoomService::normalizeCode((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            Response::redirect('/');
        }
        if (!$user) {
            Response::redirect('/?code=' . urlencode($code));
        }

        try {
            $room = RoomService::findByCode($code);
            if (!$room) {
                throw new \RuntimeException('Sala no encontrada.');
            }
            if (!RoomService::isMember((int) $room['id'], (int) $user['id'])) {
                RoomService::joinRoom((int) $user['id'], $code);
            }
            $state = RoomService::getFullState($code, (int) $user['id'], $config);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/');
        }

        self::render('room', [
            'title' => 'Sala ' . $code . ' — LEXO',
            'user' => $user,
            'state' => $state,
            'config' => $config,
        ]);
    }

    public static function leaveRoom(): void
    {
        $user = AuthService::currentUser();
        if ($user && Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $room = RoomService::findByCode((string) ($_POST['code'] ?? ''));
            if ($room) {
                RoomService::leaveRoom((int) $room['id'], (int) $user['id']);
            }
        }
        Response::redirect('/');
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function profile(array $config): void
    {
        $user = AuthService::currentUser();
        if (!$user) {
            Session::flash('error', 'Ingresá con un apodo o con Google para ver tu perfil.');
            Response::redirect('/');
        }

        $summary = StatsService::getProfileSummary((int) $user['id'], $config);
        $achievements = StatsService::getUserAchievements((int) $user['id']);
        $history = StatsService::getMatchHistory((int) $user['id']);

        self::render('profile', [
            'title' => 'Mi perfil — LEXO',
            'user' => $user,
            'summary' => $summary,
            'achievements' => $achievements,
            'history' => $history,
            'googleReady' => AuthService::isGoogleConfigured(),
            'config' => $config,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function ranking(array $config): void
    {
        $user = AuthService::currentUser();
        $sort = (string) ($_GET['orden'] ?? 'points');
        if (!in_array($sort, ['points', 'wins', 'xp'], true)) {
            $sort = 'points';
        }
        $leaders = StatsService::getLeaderboard($sort);

        self::render('ranking', [
            'title' => 'Ranking — LEXO',
            'user' => $user,
            'sort' => $sort,
            'leaders' => $leaders,
            'config' => $config,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function rules(array $config): void
    {
        $user = AuthService::currentUser();
        self::render('rules', [
            'title' => 'Cómo se juega — LEXO',
            'user' => $user,
            'config' => $config,
            'dictCount' => DictionaryService::wordCount(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function render(string $view, array $data): void
    {
        extract($data, EXTR_SKIP);
        $viewPath = dirname(__DIR__, 2) . '/views/' . $view . '.php';
        $layoutPath = dirname(__DIR__, 2) . '/views/layout.php';
        if (!is_file($viewPath)) {
            http_response_code(404);
            echo 'Vista no encontrada.';
            return;
        }

        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();

        require $layoutPath;
    }
}
