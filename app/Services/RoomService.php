<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use RuntimeException;

final class RoomService
{
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $input
     */
    public static function createRoom(int $hostUserId, array $input, array $config): array
    {
        $roomCfg = $config['room'];
        $name = trim(strip_tags((string) ($input['name'] ?? '')));
        if ($name === '') {
            $name = 'Sala LEXO';
        }
        $name = mb_substr($name, 0, 50, 'UTF-8');

        $isPractice = !empty($input['is_practice']);
        $maxPlayers = $isPractice ? 1 : (int) ($input['max_players'] ?? $roomCfg['default_max_players']);
        if (!$isPractice && !in_array($maxPlayers, $roomCfg['max_players_options'], true)) {
            $maxPlayers = (int) $roomCfg['default_max_players'];
        }

        $roundCount = (int) ($input['round_count'] ?? $roomCfg['default_round_count']);
        if (!in_array($roundCount, $roomCfg['round_counts'], true)) {
            $roundCount = (int) $roomCfg['default_round_count'];
        }

        $roundDuration = (int) ($input['round_duration_seconds'] ?? $roomCfg['default_round_duration']);
        if (!in_array($roundDuration, $roomCfg['round_durations'], true)) {
            $roundDuration = (int) $roomCfg['default_round_duration'];
        }

        $scoringMode = (string) ($input['scoring_mode'] ?? $roomCfg['default_scoring_mode']);
        if (!array_key_exists($scoringMode, $roomCfg['scoring_modes'])) {
            $scoringMode = (string) $roomCfg['default_scoring_mode'];
        }

        $validationMode = (string) ($input['validation_mode'] ?? $roomCfg['default_validation_mode']);
        if (!array_key_exists($validationMode, $roomCfg['validation_modes'])) {
            $validationMode = (string) $roomCfg['default_validation_mode'];
        }

        $minLength = (int) ($input['min_word_length'] ?? $roomCfg['default_min_word_length']);
        if (!in_array($minLength, $roomCfg['min_word_lengths'], true)) {
            $minLength = (int) $roomCfg['default_min_word_length'];
        }

        $pdo = Database::connection();
        $code = self::generateUniqueCode($roomCfg['code_prefix']);
        $now = Database::nowUtc();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO rooms (code, name, host_user_id, status, max_players, round_count, round_duration_seconds, scoring_mode, validation_mode, min_word_length, language, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $code,
                $name,
                $hostUserId,
                'waiting',
                $maxPlayers,
                $roundCount,
                $roundDuration,
                $scoringMode,
                $validationMode,
                $minLength,
                'es',
                $now,
            ]);
            $roomId = (int) $pdo->lastInsertId();

            $rp = $pdo->prepare(
                'INSERT INTO room_players (room_id, user_id, status, is_host, is_ready, connection_state, last_seen_at, joined_at)
                 VALUES (?, ?, ?, 1, 1, ?, ?, ?)'
            );
            $rp->execute([$roomId, $hostUserId, 'active', 'online', $now, $now]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findByCode($code) ?? [];
    }

    public static function normalizeCode(string $raw): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw) ?? '');
        if ($clean === '') {
            return '';
        }
        if (str_starts_with($clean, 'LEX') && strlen($clean) > 3) {
            return 'LEX-' . substr($clean, 3);
        }
        if (strlen($clean) === 4) {
            return 'LEX-' . $clean;
        }
        return $clean;
    }

    public static function findByCode(string $rawCode): ?array
    {
        $code = self::normalizeCode($rawCode);
        if ($code === '') {
            return null;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.*, u.display_name AS host_name
             FROM rooms r
             JOIN users u ON u.id = r.host_user_id
             WHERE r.code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        $room = $stmt->fetch();
        return $room ?: null;
    }

    public static function joinRoom(int $userId, string $rawCode): array
    {
        $room = self::findByCode($rawCode);
        if (!$room) {
            throw new RuntimeException('No encontramos ninguna sala con ese código.');
        }

        if ($room['status'] === 'closed') {
            throw new RuntimeException('Esta sala fue cerrada por el anfitrión.');
        }

        if ((int) $room['max_players'] === 1 && (int) $room['host_user_id'] !== $userId) {
            throw new RuntimeException('Esta partida es un modo práctica individual.');
        }

        $pdo = Database::connection();
        $now = Database::nowUtc();

        $existingStmt = $pdo->prepare('SELECT * FROM room_players WHERE room_id = ? AND user_id = ? LIMIT 1');
        $existingStmt->execute([$room['id'], $userId]);
        $existing = $existingStmt->fetch();

        if ($existing) {
            if ($existing['status'] === 'active') {
                $upd = $pdo->prepare('UPDATE room_players SET connection_state = ?, last_seen_at = ? WHERE id = ?');
                $upd->execute(['online', $now, $existing['id']]);
                return $room;
            }
            if ($room['status'] !== 'waiting') {
                throw new RuntimeException('La partida ya comenzó y abandonaste la sala.');
            }
        }

        if ($room['status'] !== 'waiting') {
            throw new RuntimeException('Esta partida ya comenzó. Pedile al anfitrión una revancha al terminar.');
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ? AND status = ?');
        $countStmt->execute([$room['id'], 'active']);
        $activeCount = (int) $countStmt->fetchColumn();
        if ($activeCount >= (int) $room['max_players']) {
            throw new RuntimeException('La sala ya alcanzó el máximo de jugadores.');
        }

        if ($existing) {
            $upd = $pdo->prepare(
                'UPDATE room_players SET status = ?, is_ready = 1, connection_state = ?, last_seen_at = ?, joined_at = ?, left_at = NULL WHERE id = ?'
            );
            $upd->execute(['active', 'online', $now, $now, $existing['id']]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO room_players (room_id, user_id, status, is_host, is_ready, connection_state, last_seen_at, joined_at)
                 VALUES (?, ?, ?, 0, 1, ?, ?, ?)'
            );
            $ins->execute([$room['id'], $userId, 'active', 'online', $now, $now]);
        }

        return $room;
    }

    public static function touchPresence(int $roomId, int $userId): void
    {
        $pdo = Database::connection();
        $now = Database::nowUtc();
        $upd = $pdo->prepare(
            'UPDATE room_players SET connection_state = ?, last_seen_at = ? WHERE room_id = ? AND user_id = ? AND status = ?'
        );
        $upd->execute(['online', $now, $roomId, $userId, 'active']);

        // Marcar offline a quienes llevan > 20 segundos sin pulso (sin expulsarlos)
        $cutoff = gmdate('Y-m-d H:i:s', time() - 20);
        $off = $pdo->prepare(
            'UPDATE room_players SET connection_state = ? WHERE room_id = ? AND status = ? AND last_seen_at < ?'
        );
        $off->execute(['offline', $roomId, 'active', $cutoff]);
    }

    public static function isMember(int $roomId, int $userId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT 1 FROM room_players WHERE room_id = ? AND user_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$roomId, $userId, 'active']);
        return (bool) $stmt->fetchColumn();
    }

    public static function getPlayers(int $roomId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT rp.user_id, rp.is_host, rp.is_ready, rp.connection_state, rp.last_seen_at,
                    u.display_name, u.avatar_url, u.avatar_color, u.is_guest,
                    COALESCE(ps.experience_total, 0) AS experience_total,
                    COALESCE(ps.stars_total, 0) AS stars_total
             FROM room_players rp
             JOIN users u ON u.id = rp.user_id
             LEFT JOIN player_stats ps ON ps.user_id = rp.user_id
             WHERE rp.room_id = ? AND rp.status = ?
             ORDER BY rp.is_host DESC, rp.joined_at ASC'
        );
        $stmt->execute([$roomId, 'active']);
        return $stmt->fetchAll();
    }

    public static function leaveRoom(int $roomId, int $userId): void
    {
        $pdo = Database::connection();
        $now = Database::nowUtc();
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                'UPDATE room_players SET status = ?, connection_state = ?, left_at = ? WHERE room_id = ? AND user_id = ?'
            );
            $upd->execute(['left', 'offline', $now, $roomId, $userId]);

            $roomStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1');
            $roomStmt->execute([$roomId]);
            $room = $roomStmt->fetch();

            if ($room && (int) $room['host_user_id'] === $userId) {
                $nextStmt = $pdo->prepare(
                    'SELECT user_id FROM room_players WHERE room_id = ? AND status = ? ORDER BY joined_at ASC LIMIT 1'
                );
                $nextStmt->execute([$roomId, 'active']);
                $nextHost = $nextStmt->fetchColumn();

                if ($nextHost) {
                    $pdo->prepare('UPDATE rooms SET host_user_id = ? WHERE id = ?')->execute([(int) $nextHost, $roomId]);
                    $pdo->prepare('UPDATE room_players SET is_host = 1 WHERE room_id = ? AND user_id = ?')->execute([$roomId, (int) $nextHost]);
                } elseif ($room['status'] === 'waiting') {
                    $pdo->prepare('UPDATE rooms SET status = ?, finished_at = ? WHERE id = ?')->execute(['closed', $now, $roomId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Snapshot unificado de la sala + partida + ronda activa para polling y vistas.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function getFullState(string $code, int $viewerUserId, array $config): array
    {
        $room = self::findByCode($code);
        if (!$room) {
            throw new RuntimeException('Sala no encontrada.');
        }

        self::touchPresence((int) $room['id'], $viewerUserId);
        $players = self::getPlayers((int) $room['id']);

        $match = MatchService::getLatestMatchForRoom((int) $room['id']);
        $round = null;
        $myWords = [];
        $roundResults = [];
        $matchStandings = [];
        $newAchievements = [];

        if ($match) {
            $round = MatchService::getLatestRoundForMatch((int) $match['id']);
            if ($round && $round['status'] === 'active') {
                // Cierre automático autorizado por el reloj del servidor
                if (Database::nowMs() >= (int) $round['ends_at_ms']) {
                    MatchService::closeRound((int) $round['id'], $config);
                    $round = MatchService::getRoundById((int) $round['id']);
                    $match = MatchService::getMatchById((int) $match['id']);
                }
            }

            if ($round) {
                $myWords = MatchService::getPlayerRoundWords((int) $round['id'], $viewerUserId);
                if ($round['status'] === 'finished') {
                    $roundResults = MatchService::getRoundResultsWithWords((int) $round['id']);
                }
            }

            $matchStandings = MatchService::getMatchStandings((int) $match['id']);
            if ($match['status'] === 'finished') {
                $newAchievements = StatsService::getMatchUnlockedAchievements((int) $match['id'], $viewerUserId);
            }
        }

        // Refrescar estado de sala si la partida cerró
        $room = self::findByCode($code) ?? $room;

        return [
            'server_now_ms' => Database::nowMs(),
            'room' => $room,
            'players' => $players,
            'is_host' => (int) $room['host_user_id'] === $viewerUserId,
            'match' => $match,
            'round' => $round,
            'my_words' => $myWords,
            'round_results' => $roundResults,
            'match_standings' => $matchStandings,
            'unlocked_achievements' => $newAchievements,
        ];
    }

    private static function generateUniqueCode(string $prefix): string
    {
        $pdo = Database::connection();
        $alphabetLen = strlen(self::CODE_ALPHABET);

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, $alphabetLen - 1)];
            }
            $code = $prefix . '-' . $suffix;

            $stmt = $pdo->prepare('SELECT 1 FROM rooms WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);
            if (!$stmt->fetchColumn()) {
                return $code;
            }
        }

        throw new RuntimeException('No se pudo generar un código de sala único.');
    }
}
