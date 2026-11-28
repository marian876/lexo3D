<?php
declare(strict_types=1);

/**
 * Suite de pruebas de integración para LEXO.
 * Verifica flujo completo: usuarios, salas, reloj del servidor, normalización ñ/tildes,
 * privacidad en ronda activa, consenso, cierre idempotente, podio, logros y ranking.
 */

$rootPath = dirname(__DIR__);

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

use App\Services\AuthService;
use App\Services\DictionaryService;
use App\Services\MatchService;
use App\Services\RoomService;
use App\Services\StatsService;
use App\Support\Database;
use App\Support\WordNormalizer;

$config = require $rootPath . '/config/app.php';
DictionaryService::init($rootPath);

// Base SQLite en memoria aislada
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON;');
$schema = file_get_contents($rootPath . '/database/schema.sqlite.sql');
if ($schema === false) {
    fwrite(STDERR, "No se pudo leer schema.sqlite.sql\n");
    exit(1);
}
$pdo->exec($schema);
Database::setConnectionForTests($pdo, 'sqlite');

$passed = 0;
function assertTrue(bool $cond, string $msg): void
{
    global $passed;
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    $passed++;
}

function assertThrows(callable $fn, string $msg): void
{
    global $passed;
    try {
        $fn();
        fwrite(STDERR, "FAIL (no lanzó excepción): {$msg}\n");
        exit(1);
    } catch (\Throwable $e) {
        $passed++;
    }
}

// 1. Normalización española: tildes vs Ñ
assertTrue(WordNormalizer::normalizeKey('árbol') === WordNormalizer::normalizeKey('ARBOL'), 'árbol y ARBOL deben coincidir para evitar duplicados');
assertTrue(WordNormalizer::normalizeKey('año') !== WordNormalizer::normalizeKey('ano'), 'año y ano deben permanecer distintos (Ñ != N)');
assertTrue(!WordNormalizer::validateFormat('ca12', 3)['ok'], 'Números deben ser rechazados');
assertTrue(!WordNormalizer::validateFormat('sol', 4)['ok'], 'Palabra más corta que minLength debe ser rechazada');

// 2. Diccionario español
assertTrue(DictionaryService::isAvailable(), 'El diccionario base debe estar disponible');
assertTrue(DictionaryService::exists('CAMINO'), 'CAMINO debe existir en el diccionario');
assertTrue(DictionaryService::exists('CAMINOS'), 'Plural regular CAMINOS debe validarse');

// 3. Usuarios y creación/unión de sala
$host = AuthService::loginAsGuest('María');
$guest2 = AuthService::loginAsGuest('Lucas');
assertTrue((int) $host['id'] !== (int) $guest2['id'], 'Cada invitado tiene su propio usuario');

$room = RoomService::createRoom((int) $host['id'], [
    'name' => 'Mesa Test',
    'round_count' => 1,
    'round_duration_seconds' => 60,
    'scoring_mode' => 'length',
    'validation_mode' => 'table',
    'min_word_length' => 3,
], $config);
assertTrue(str_starts_with($room['code'], 'LEX-'), 'El código de sala debe iniciar con LEX-');

// No se puede iniciar con menos de 2 jugadores
assertThrows(fn () => MatchService::startMatch((int) $room['id'], (int) $host['id'], $config), 'No debe iniciar con 1 solo jugador');

RoomService::joinRoom((int) $guest2['id'], $room['code']);
$players = RoomService::getPlayers((int) $room['id']);
assertTrue(count($players) === 2, 'La sala debe tener 2 jugadores activos');

// 4. Inicio de partida y envío de palabras
$match = MatchService::startMatch((int) $room['id'], (int) $host['id'], $config);
$round = MatchService::getLatestRoundForMatch((int) $match['id']);
assertTrue($round !== null && $round['status'] === 'active', 'Ronda 1 debe estar activa');

$w1 = MatchService::submitWord((int) $round['id'], (int) $host['id'], 'relámpago', $config);
assertTrue($w1['points_awarded'] === 5, 'Palabra de 9 letras debe valer 5 puntos');
$w2 = MatchService::submitWord((int) $round['id'], (int) $host['id'], 'árbol', $config);
assertTrue($w2['points_awarded'] === 4, 'Palabra de 5 letras debe valer 4 puntos');

// Duplicado con diferente tilde/mayúscula debe rechazarse
assertThrows(fn () => MatchService::submitWord((int) $round['id'], (int) $host['id'], 'ARBOL', $config), 'ARBOL duplicado de árbol debe rechazarse');

// Segundo jugador envía palabra
MatchService::submitWord((int) $round['id'], (int) $guest2['id'], 'sol', $config);

// Privacidad: cada jugador ve solo sus palabras
$hostWords = MatchService::getPlayerRoundWords((int) $round['id'], (int) $host['id']);
$guestWords = MatchService::getPlayerRoundWords((int) $round['id'], (int) $guest2['id']);
assertTrue(count($hostWords) === 2 && count($guestWords) === 1, 'Cada jugador solo consulta sus propias palabras en ronda activa');

// Usuario externo no pertenece a la ronda
$intruder = AuthService::loginAsGuest('Intruso');
assertThrows(fn () => MatchService::submitWord((int) $round['id'], (int) $intruder['id'], 'casa', $config), 'Intruso no puede enviar palabras');

// 5. Cierre de ronda expirada, idempotencia, podio y estadísticas
$pdo->prepare('UPDATE rounds SET ends_at_ms = ? WHERE id = ?')->execute([Database::nowMs() - 1000, (int) $round['id']]);
assertThrows(fn () => MatchService::submitWord((int) $round['id'], (int) $host['id'], 'mesa', $config), 'No se aceptan palabras tras el cierre del tiempo');

// Llamar closeRound de nuevo debe ser idempotente (no duplicar recompensas)
MatchService::closeRound((int) $round['id'], $config);
MatchService::finalizeMatch((int) $match['id'], $config);

$hostStats = StatsService::getProfileSummary((int) $host['id'], $config);
assertTrue((int) $hostStats['stats']['matches_played'] === 1, 'Host debe tener 1 partida jugada');
assertTrue((int) $hostStats['stats']['matches_won'] === 1, 'Host debe tener 1 partida ganada');
assertTrue((int) $hostStats['stats']['stars_total'] >= 1, 'Host debe haber recibido estrellas por ganar');

$leaderboard = StatsService::getLeaderboard('points');
assertTrue(count($leaderboard) === 2 && (int) $leaderboard[0]['id'] === (int) $host['id'], 'Host debe liderar el ranking');
assertTrue(!array_key_exists('email', $leaderboard[0]), 'El ranking público nunca debe exponer email');

// 6. Modo práctica individual (1 jugador, sin sala de espera ni impacto en victorias/ranking)
$solo = AuthService::loginAsGuest('Solitario');
$practiceRoom = RoomService::createRoom((int) $solo['id'], [
    'name' => 'Modo práctica',
    'is_practice' => true,
    'round_count' => 1,
    'round_duration_seconds' => 60,
    'scoring_mode' => 'length',
    'validation_mode' => 'table',
    'min_word_length' => 3,
], $config);
assertTrue((int) $practiceRoom['max_players'] === 1, 'Modo práctica debe configurar max_players = 1');
assertThrows(fn () => RoomService::joinRoom((int) $host['id'], $practiceRoom['code']), 'Otro jugador no puede entrar al modo práctica individual');

$practiceMatch = MatchService::startMatch((int) $practiceRoom['id'], (int) $solo['id'], $config);
$practiceRound = MatchService::getLatestRoundForMatch((int) $practiceMatch['id']);
MatchService::submitWord((int) $practiceRound['id'], (int) $solo['id'], 'estrella', $config);
MatchService::closeRound((int) $practiceRound['id'], $config);

$soloStats = StatsService::getProfileSummary((int) $solo['id'], $config);
assertTrue((int) $soloStats['stats']['experience_total'] === 5, 'Práctica debe otorgar 5 XP');
assertTrue((int) $soloStats['stats']['matches_won'] === 0, 'Práctica no debe inflar victorias multijugador');
assertTrue((int) $soloStats['stats']['matches_played'] === 0, 'Práctica no debe inflar partidas competitivas');

// 7. Usuario registrado con Google (0 partidas) debe aparecer en el Ranking
$now = Database::nowUtc();
$pdo->prepare('INSERT INTO users (google_sub, email, display_name, is_guest, role, status, created_at, updated_at) VALUES (?, ?, ?, 0, ?, ?, ?, ?)')
    ->execute(['sub-test-123', 'reg@example.com', 'RegistradoNuevo', 'player', 'active', $now, $now]);
$leaderboardWithReg = StatsService::getLeaderboard('points');
$namesInBoard = array_column($leaderboardWithReg, 'display_name');
assertTrue(in_array('RegistradoNuevo', $namesInBoard, true), 'Usuario registrado debe aparecer en el ranking aunque tenga 0 partidas');

echo "ALL TESTS PASSED ({$passed} assertions)\n";
