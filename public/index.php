<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $staticPath = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($staticPath !== __DIR__ . '/' && is_file($staticPath)) {
        return false;
    }
}

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Services\DictionaryService;
use App\Support\Database;
use App\Support\Env;
use App\Support\Session;

$rootPath = dirname(__DIR__);

// Autoloader PSR-4 simple sin necesidad de Composer en Hostinger
spl_autoload_register(static function (string $class) use ($rootPath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $rootPath . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

Env::load($rootPath);
date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC'));
Database::init($rootPath);
DictionaryService::init($rootPath);
Session::start();

$config = require $rootPath . '/config/app.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Soporte por si la app vive en un subdirectorio de Hostinger
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uriPath, $scriptDir)) {
    $uriPath = substr($uriPath, strlen($scriptDir)) ?: '/';
}
$uriPath = '/' . ltrim($uriPath, '/');
if ($uriPath !== '/' && str_ends_with($uriPath, '/')) {
    $uriPath = rtrim($uriPath, '/');
}

// Enrutamiento principal
match (true) {
    $method === 'GET' && $uriPath === '/' => PageController::home($config),
    $method === 'GET' && $uriPath === '/reglas' => PageController::rules($config),
    $method === 'GET' && $uriPath === '/ranking' => PageController::ranking($config),
    $method === 'GET' && $uriPath === '/perfil' => PageController::profile($config),
    $method === 'GET' && $uriPath === '/sala' => PageController::room($config),
    $uriPath === '/join' => PageController::joinRoom(),

    $method === 'POST' && $uriPath === '/rooms/create' => PageController::createRoom($config),
    $method === 'POST' && $uriPath === '/rooms/leave' => PageController::leaveRoom(),
    $method === 'POST' && $uriPath === '/practice/start' => PageController::startPractice($config),

    $method === 'POST' && $uriPath === '/auth/guest' => AuthController::guest(),
    $method === 'GET' && $uriPath === '/auth/google' => AuthController::googleRedirect(),
    $method === 'GET' && $uriPath === '/auth/google/callback' => AuthController::googleCallback(),
    $method === 'POST' && $uriPath === '/auth/profile' => AuthController::updateProfile(),
    $method === 'POST' && $uriPath === '/auth/logout' => AuthController::logout(),

    $method === 'GET' && $uriPath === '/api/room/state' => ApiController::roomState($config),
    $method === 'POST' && $uriPath === '/api/room/start' => ApiController::startMatch($config),
    $method === 'POST' && $uriPath === '/api/round/word' => ApiController::submitWord($config),
    $method === 'POST' && $uriPath === '/api/round/next' => ApiController::nextRound(),
    $method === 'POST' && $uriPath === '/api/round/toggle-word' => ApiController::toggleWord($config),
    $method === 'POST' && $uriPath === '/api/room/rematch' => ApiController::rematch(),

    default => (static function (): void {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>404 — LEXO</title></head><body style="font-family:sans-serif;text-align:center;padding:3rem;"><h1>404</h1><p>Página no encontrada.</p><a href="/">Volver a LEXO</a></body></html>';
    })(),
};
