<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use App\Support\WordNormalizer;
use PDOException;
use RuntimeException;

final class MatchService
{
    /**
     * @param array<string, mixed> $config
     */
    public static function startMatch(int $roomId, int $hostUserId, array $config): array
    {
        $pdo = Database::connection();
        $roomStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1');
        $roomStmt->execute([$roomId]);
        $room = $roomStmt->fetch();

        if (!$room) {
            throw new RuntimeException('La sala no existe.');
        }
        if ((int) $room['host_user_id'] !== $hostUserId) {
            throw new RuntimeException('Solo el anfitrión puede iniciar la partida.');
        }
        if (!in_array($room['status'], ['waiting', 'finished'], true)) {
            throw new RuntimeException('Ya hay una partida en curso en esta sala.');
        }

        if ($room['validation_mode'] === 'dictionary' && !DictionaryService::isAvailable()) {
            throw new RuntimeException('El diccionario español local no está disponible. Cambiá a modo Mesa libre o cargá words_es.txt.');
        }

        $players = RoomService::getPlayers($roomId);
        $isPractice = (int) $room['max_players'] === 1;
        $minPlayers = $isPractice ? 1 : (int) $config['room']['min_players'];
        if (count($players) < $minPlayers) {
            throw new RuntimeException("Se necesitan al menos {$minPlayers} jugadores en la sala para comenzar.");
        }

        $now = Database::nowUtc();
        $nowMs = Database::nowMs();
        $durationSec = (int) $room['round_duration_seconds'];
        $endsAtMs = $nowMs + ($durationSec * 1000);
        $endsAt = gmdate('Y-m-d H:i:s', (int) ceil($endsAtMs / 1000));

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE rooms SET status = ?, started_at = ?, finished_at = NULL WHERE id = ?')
                ->execute(['playing', $now, $roomId]);

            $mStmt = $pdo->prepare(
                'INSERT INTO matches (room_id, status, scoring_mode_snapshot, validation_mode_snapshot, min_word_length_snapshot, round_count_snapshot, round_duration_snapshot, started_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $mStmt->execute([
                $roomId,
                'active',
                $room['scoring_mode'],
                $room['validation_mode'],
                (int) $room['min_word_length'],
                (int) $room['round_count'],
                $durationSec,
                $now,
                $now,
            ]);
            $matchId = (int) $pdo->lastInsertId();

            $mpStmt = $pdo->prepare(
                'INSERT INTO match_players (match_id, user_id, status) VALUES (?, ?, ?)'
            );
            foreach ($players as $p) {
                $mpStmt->execute([$matchId, (int) $p['user_id'], 'playing']);
            }

            $rStmt = $pdo->prepare(
                'INSERT INTO rounds (match_id, round_number, status, started_at, ends_at, started_at_ms, ends_at_ms, created_at)
                 VALUES (?, 1, ?, ?, ?, ?, ?, ?)'
            );
            $rStmt->execute([$matchId, 'active', $now, $endsAt, $nowMs, $endsAtMs, $now]);

            $pdo->commit();
            return self::getMatchById($matchId) ?? [];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function submitWord(int $roundId, int $userId, string $rawWord, array $config): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.*, m.id AS match_id, m.scoring_mode_snapshot, m.validation_mode_snapshot, m.min_word_length_snapshot
             FROM rounds r
             JOIN matches m ON m.id = r.match_id
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            throw new RuntimeException('La ronda no existe.');
        }

        // Verificar pertenencia del usuario a la partida
        $mpStmt = $pdo->prepare('SELECT 1 FROM match_players WHERE match_id = ? AND user_id = ? LIMIT 1');
        $mpStmt->execute([(int) $round['match_id'], $userId]);
        if (!$mpStmt->fetchColumn()) {
            throw new RuntimeException('No pertenecés a esta partida.');
        }

        $nowMs = Database::nowMs();
        if ($round['status'] !== 'active' || $nowMs > (int) $round['ends_at_ms']) {
            if ($round['status'] === 'active') {
                self::closeRound($roundId, $config);
            }
            throw new RuntimeException('⏱️ El tiempo de la ronda terminó.');
        }

        $minLen = (int) $round['min_word_length_snapshot'];
        $check = WordNormalizer::validateFormat($rawWord, $minLen);
        if (!$check['ok']) {
            throw new RuntimeException((string) $check['error']);
        }

        $validationSource = (string) $round['validation_mode_snapshot'];
        if ($validationSource === 'dictionary') {
            if (!DictionaryService::isAvailable()) {
                throw new RuntimeException('El diccionario español no está disponible.');
            }
            if (!DictionaryService::exists($check['normalized'])) {
                throw new RuntimeException("\"{$check['display']}\" no figura en el diccionario español.");
            }
        }

        $points = self::calculateWordPoints(
            $check['length'],
            (string) $round['scoring_mode_snapshot'],
            $config
        );

        $now = Database::nowUtc();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO words (round_id, user_id, original_text, normalized_text, letter_count, points_awarded, validation_status, validation_source, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $roundId,
                $userId,
                $check['display'],
                $check['normalized'],
                $check['length'],
                $points,
                'valid',
                $validationSource,
                $now,
            ]);
        } catch (PDOException $e) {
            // Restricción única uq_round_user_word -> palabra duplicada
            if (str_contains(strtolower($e->getMessage()), 'unique') || (string) $e->getCode() === '23000') {
                throw new RuntimeException("Ya registraste \"{$check['display']}\" en esta ronda.");
            }
            throw $e;
        }

        return [
            'id' => (int) $pdo->lastInsertId(),
            'original_text' => $check['display'],
            'normalized_text' => $check['normalized'],
            'letter_count' => $check['length'],
            'points_awarded' => $points,
            'validation_status' => 'valid',
        ];
    }

    /**
     * Cierra una ronda de forma transaccional e idempotente y calcula puntajes.
     *
     * @param array<string, mixed> $config
     */
    public static function closeRound(int $roundId, array $config): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $rStmt = $pdo->prepare(
                'SELECT r.*, m.room_id, m.scoring_mode_snapshot, m.round_count_snapshot
                 FROM rounds r
                 JOIN matches m ON m.id = r.match_id
                 WHERE r.id = ? LIMIT 1'
            );
            $rStmt->execute([$roundId]);
            $round = $rStmt->fetch();

            if (!$round || $round['status'] !== 'active') {
                $pdo->commit();
                return;
            }

            $matchId = (int) $round['match_id'];
            $mpStmt = $pdo->prepare('SELECT user_id FROM match_players WHERE match_id = ?');
            $mpStmt->execute([$matchId]);
            $playerIds = array_map('intval', $mpStmt->fetchAll(\PDO::FETCH_COLUMN));

            $wStmt = $pdo->prepare(
                "SELECT user_id, original_text, letter_count, points_awarded
                 FROM words
                 WHERE round_id = ? AND validation_status = 'valid'
                 ORDER BY letter_count DESC, id ASC"
            );
            $wStmt->execute([$roundId]);
            $words = $wStmt->fetchAll();

            $byUser = [];
            foreach ($playerIds as $uid) {
                $byUser[$uid] = [
                    'user_id' => $uid,
                    'valid_word_count' => 0,
                    'competitive_points' => 0,
                    'longest_word' => null,
                    'longest_len' => 0,
                ];
            }

            foreach ($words as $w) {
                $uid = (int) $w['user_id'];
                if (!isset($byUser[$uid])) {
                    continue;
                }
                $byUser[$uid]['valid_word_count']++;
                $byUser[$uid]['competitive_points'] += (int) $w['points_awarded'];
                if ((int) $w['letter_count'] > $byUser[$uid]['longest_len']) {
                    $byUser[$uid]['longest_len'] = (int) $w['letter_count'];
                    $byUser[$uid]['longest_word'] = (string) $w['original_text'];
                }
            }

            $ranked = array_values($byUser);
            usort($ranked, static function (array $a, array $b): int {
                if ($a['competitive_points'] !== $b['competitive_points']) {
                    return $b['competitive_points'] <=> $a['competitive_points'];
                }
                if ($a['valid_word_count'] !== $b['valid_word_count']) {
                    return $b['valid_word_count'] <=> $a['valid_word_count'];
                }
                return $b['longest_len'] <=> $a['longest_len'];
            });

            $now = Database::nowUtc();
            $topScore = $ranked[0]['competitive_points'] ?? 0;
            $topCount = $ranked[0]['valid_word_count'] ?? 0;
            $winnersCount = 0;
            foreach ($ranked as $row) {
                if ($topScore > 0 && $row['competitive_points'] === $topScore && $row['valid_word_count'] === $topCount) {
                    $winnersCount++;
                }
            }
            $winnerUserId = ($winnersCount === 1) ? (int) $ranked[0]['user_id'] : null;

            $rrStmt = $pdo->prepare(
                'INSERT INTO round_results (round_id, user_id, valid_word_count, competitive_points, longest_word, position, is_winner, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $updMp = $pdo->prepare(
                'UPDATE match_players
                 SET rounds_won = rounds_won + ?, valid_word_count = valid_word_count + ?, competitive_points = competitive_points + ?
                 WHERE match_id = ? AND user_id = ?'
            );

            $position = 1;
            foreach ($ranked as $idx => $row) {
                if ($idx > 0) {
                    $prev = $ranked[$idx - 1];
                    if ($row['competitive_points'] !== $prev['competitive_points'] || $row['valid_word_count'] !== $prev['valid_word_count']) {
                        $position = $idx + 1;
                    }
                }
                $isWinner = ($topScore > 0 && $row['competitive_points'] === $topScore && $row['valid_word_count'] === $topCount) ? 1 : 0;
                $rrStmt->execute([
                    $roundId,
                    $row['user_id'],
                    $row['valid_word_count'],
                    $row['competitive_points'],
                    $row['longest_word'],
                    $position,
                    $isWinner,
                    $now,
                ]);

                $updMp->execute([
                    $isWinner,
                    $row['valid_word_count'],
                    $row['competitive_points'],
                    $matchId,
                    $row['user_id'],
                ]);
            }

            $pdo->prepare('UPDATE rounds SET status = ?, finished_at = ?, winner_user_id = ? WHERE id = ?')
                ->execute(['finished', $now, $winnerUserId, $roundId]);

            $isFinalRound = (int) $round['round_number'] >= (int) $round['round_count_snapshot'];
            $pdo->commit();

            if ($isFinalRound) {
                self::finalizeMatch($matchId, $config);
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inicia la siguiente ronda cuando el anfitrión avanza desde los resultados de ronda.
     */
    public static function startNextRound(int $matchId, int $hostUserId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT m.*, r.host_user_id
             FROM matches m
             JOIN rooms r ON r.id = m.room_id
             WHERE m.id = ? LIMIT 1'
        );
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();

        if (!$match) {
            throw new RuntimeException('Partida no encontrada.');
        }
        if ((int) $match['host_user_id'] !== $hostUserId) {
            throw new RuntimeException('Solo el anfitrión puede iniciar la siguiente ronda.');
        }
        if ($match['status'] !== 'active') {
            throw new RuntimeException('La partida ya finalizó.');
        }

        $latest = self::getLatestRoundForMatch($matchId);
        if (!$latest || $latest['status'] === 'active') {
            throw new RuntimeException('La ronda actual todavía está en curso.');
        }

        $nextNum = (int) $latest['round_number'] + 1;
        if ($nextNum > (int) $match['round_count_snapshot']) {
            throw new RuntimeException('Ya se jugaron todas las rondas.');
        }

        $now = Database::nowUtc();
        $nowMs = Database::nowMs();
        $endsAtMs = $nowMs + ((int) $match['round_duration_snapshot'] * 1000);
        $endsAt = gmdate('Y-m-d H:i:s', (int) ceil($endsAtMs / 1000));

        $ins = $pdo->prepare(
            'INSERT INTO rounds (match_id, round_number, status, started_at, ends_at, started_at_ms, ends_at_ms, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$matchId, $nextNum, 'active', $now, $endsAt, $nowMs, $endsAtMs, $now]);

        return self::getRoundById((int) $pdo->lastInsertId()) ?? [];
    }

    /**
     * Permite a los contrarios marcar o desmarcar una palabra (por ejemplo si usa letras que no estaban en los dados).
     *
     * @param array<string, mixed> $config
     */
    public static function toggleWordRejection(int $roundId, int $wordId, int $actorUserId, array $config): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.id, r.match_id, r.status, m.status AS match_status, m.room_id, m.scoring_mode_snapshot
             FROM rounds r
             JOIN matches m ON m.id = r.match_id
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$roundId]);
        $info = $stmt->fetch();

        if (!$info) {
            throw new RuntimeException('Ronda no encontrada.');
        }
        if ($info['status'] !== 'finished') {
            throw new RuntimeException('Las palabras solo se pueden rechazar al terminar la ronda.');
        }

        // Verificar que quien rechaza pertenezca a la partida
        $mpCheck = $pdo->prepare('SELECT 1 FROM match_players WHERE match_id = ? AND user_id = ? LIMIT 1');
        $mpCheck->execute([(int) $info['match_id'], $actorUserId]);
        if (!$mpCheck->fetchColumn()) {
            throw new RuntimeException('No pertenecés a esta sala.');
        }

        $wStmt = $pdo->prepare('SELECT * FROM words WHERE id = ? AND round_id = ? LIMIT 1');
        $wStmt->execute([$wordId, $roundId]);
        $word = $wStmt->fetch();
        if (!$word) {
            throw new RuntimeException('Palabra no encontrada.');
        }

        if ((int) $word['user_id'] === $actorUserId) {
            throw new RuntimeException('Solo los contrarios pueden rechazar o restaurar tus palabras.');
        }

        $newStatus = $word['validation_status'] === 'valid' ? 'rejected' : 'valid';
        $reason = $newStatus === 'rejected' ? 'Rechazada por los contrarios' : null;
        $newPoints = $newStatus === 'valid'
            ? self::calculateWordPoints((int) $word['letter_count'], (string) $info['scoring_mode_snapshot'], $config)
            : 0;

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE words SET validation_status = ?, rejection_reason = ?, points_awarded = ? WHERE id = ?')
                ->execute([$newStatus, $reason, $newPoints, $wordId]);

            self::recalculateRoundAndMatchTotals($pdo, (int) $info['match_id'], $roundId);
            if ($info['match_status'] === 'finished') {
                self::recalculateFinishedMatchPositions($pdo, (int) $info['match_id']);
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
     * Cierra la partida, asigna podio final y entrega recompensas idempotentes (XP, estrellas, logros).
     *
     * @param array<string, mixed> $config
     */
    public static function finalizeMatch(int $matchId, array $config): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $mStmt = $pdo->prepare('SELECT * FROM matches WHERE id = ? LIMIT 1');
            $mStmt->execute([$matchId]);
            $match = $mStmt->fetch();
            if (!$match || $match['status'] !== 'active') {
                $pdo->commit();
                return;
            }

            $rInfoStmt = $pdo->prepare('SELECT max_players FROM rooms WHERE id = ? LIMIT 1');
            $rInfoStmt->execute([(int) $match['room_id']]);
            $isPractice = ((int) $rInfoStmt->fetchColumn()) === 1;

            $mpStmt = $pdo->prepare('SELECT * FROM match_players WHERE match_id = ?');
            $mpStmt->execute([$matchId]);
            $standings = $mpStmt->fetchAll();

            usort($standings, static function (array $a, array $b): int {
                if ((int) $a['rounds_won'] !== (int) $b['rounds_won']) {
                    return (int) $b['rounds_won'] <=> (int) $a['rounds_won'];
                }
                if ((int) $a['competitive_points'] !== (int) $b['competitive_points']) {
                    return (int) $b['competitive_points'] <=> (int) $a['competitive_points'];
                }
                return (int) $b['valid_word_count'] <=> (int) $a['valid_word_count'];
            });

            $now = Database::nowUtc();
            $topRounds = (int) ($standings[0]['rounds_won'] ?? 0);
            $topPoints = (int) ($standings[0]['competitive_points'] ?? 0);
            $topWinners = 0;
            foreach ($standings as $s) {
                if ($topPoints > 0 && (int) $s['rounds_won'] === $topRounds && (int) $s['competitive_points'] === $topPoints) {
                    $topWinners++;
                }
            }
            $winnerUserId = ($topWinners === 1) ? (int) $standings[0]['user_id'] : null;

            $prog = $config['progression'];
            $scoring = $config['scoring'];

            $pos = 1;
            foreach ($standings as $idx => $s) {
                if ($idx > 0) {
                    $prev = $standings[$idx - 1];
                    if ((int) $s['rounds_won'] !== (int) $prev['rounds_won'] || (int) $s['competitive_points'] !== (int) $prev['competitive_points']) {
                        $pos = $idx + 1;
                    }
                }
                $uid = (int) $s['user_id'];
                if ($isPractice) {
                    // Modo práctica individual: otorga XP de entrenamiento y suma palabras sin inflar ranking/rachas multijugador
                    $xp = 5;
                    $stars = 0;
                    self::grantRewardIdempotent($pdo, $uid, 'match', $matchId, 'xp', $xp, "match:{$matchId}:u:{$uid}:xp", $now);

                    AuthService::ensureStatsRow($uid);
                    $pdo->prepare(
                        'UPDATE player_stats
                         SET valid_words_total = valid_words_total + ?,
                             experience_total = experience_total + ?,
                             updated_at = ?
                         WHERE user_id = ?'
                    )->execute([(int) $s['valid_word_count'], $xp, $now, $uid]);

                    $pdo->prepare(
                        'UPDATE match_players
                         SET final_position = 1, experience_earned = ?, stars_earned = 0, status = ?
                         WHERE id = ?'
                    )->execute([$xp, 'finished', (int) $s['id']]);
                    continue;
                }

                $isWinner = ($winnerUserId === $uid);
                $xp = (int) $prog['xp_per_match_played']
                    + ((int) $s['rounds_won'] * (int) $prog['xp_per_round_won'])
                    + ($isWinner ? (int) $prog['xp_per_match_won'] : 0);
                $stars = $isWinner ? 1 : 0;
                $bonusPts = $isWinner ? (int) $scoring['match_win_bonus_points'] : 0;

                // Transacciones idempotentes de recompensas
                self::grantRewardIdempotent($pdo, $uid, 'match', $matchId, 'xp', $xp, "match:{$matchId}:u:{$uid}:xp", $now);
                $totalUserPts = (int) $s['competitive_points'] + $bonusPts;
                if ($totalUserPts > 0) {
                    self::grantRewardIdempotent($pdo, $uid, 'match', $matchId, 'points', $totalUserPts, "match:{$matchId}:u:{$uid}:pts", $now);
                }
                if ($stars > 0) {
                    self::grantRewardIdempotent($pdo, $uid, 'match', $matchId, 'stars', $stars, "match:{$matchId}:u:{$uid}:stars", $now);
                }

                // Actualizar estadísticas acumuladas
                AuthService::ensureStatsRow($uid);
                $psStmt = $pdo->prepare('SELECT * FROM player_stats WHERE user_id = ? LIMIT 1');
                $psStmt->execute([$uid]);
                $ps = $psStmt->fetch();

                $newStreak = $isWinner ? ((int) $ps['current_win_streak'] + 1) : 0;
                $bestStreak = max((int) $ps['best_win_streak'], $newStreak);

                $pdo->prepare(
                    'UPDATE player_stats
                     SET matches_played = matches_played + 1,
                         matches_won = matches_won + ?,
                         rounds_played = rounds_played + ?,
                         rounds_won = rounds_won + ?,
                         valid_words_total = valid_words_total + ?,
                         competitive_points_total = competitive_points_total + ?,
                         experience_total = experience_total + ?,
                         stars_total = stars_total + ?,
                         current_win_streak = ?,
                         best_win_streak = ?,
                         updated_at = ?
                     WHERE user_id = ?'
                )->execute([
                    $isWinner ? 1 : 0,
                    (int) $match['round_count_snapshot'],
                    (int) $s['rounds_won'],
                    (int) $s['valid_word_count'],
                    $totalUserPts,
                    $xp,
                    $stars,
                    $newStreak,
                    $bestStreak,
                    $now,
                    $uid,
                ]);

                // Evaluar logros desbloqueados
                $achReward = StatsService::evaluateAndUnlockAchievements($pdo, $uid, $matchId, $now);
                $xp += $achReward['xp'];
                $stars += $achReward['stars'];

                $pdo->prepare(
                    'UPDATE match_players
                     SET final_position = ?, experience_earned = ?, stars_earned = ?, status = ?
                     WHERE id = ?'
                )->execute([$pos, $xp, $stars, 'finished', (int) $s['id']]);
            }

            $pdo->prepare('UPDATE matches SET status = ?, winner_user_id = ?, finished_at = ? WHERE id = ?')
                ->execute(['finished', $winnerUserId, $now, $matchId]);
            $pdo->prepare('UPDATE rooms SET status = ?, finished_at = ? WHERE id = ?')
                ->execute(['finished', $now, (int) $match['room_id']]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function rematchToLobby(int $roomId, int $hostUserId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        if (!$room || (int) $room['host_user_id'] !== $hostUserId) {
            throw new RuntimeException('Solo el anfitrión puede preparar la revancha.');
        }
        $pdo->prepare('UPDATE rooms SET status = ?, finished_at = NULL WHERE id = ?')->execute(['waiting', $roomId]);
    }

    public static function getLatestMatchForRoom(int $roomId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM matches WHERE room_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$roomId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getMatchById(int $matchId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ? LIMIT 1');
        $stmt->execute([$matchId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getLatestRoundForMatch(int $matchId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM rounds WHERE match_id = ? ORDER BY round_number DESC LIMIT 1');
        $stmt->execute([$matchId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getRoundById(int $roundId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM rounds WHERE id = ? LIMIT 1');
        $stmt->execute([$roundId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getPlayerRoundWords(int $roundId, int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, original_text, letter_count, points_awarded, validation_status, rejection_reason, submitted_at
             FROM words
             WHERE round_id = ? AND user_id = ?
             ORDER BY id DESC'
        );
        $stmt->execute([$roundId, $userId]);
        return $stmt->fetchAll();
    }

    public static function getRoundResultsWithWords(int $roundId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT rr.*, u.display_name, u.avatar_url, u.avatar_color
             FROM round_results rr
             JOIN users u ON u.id = rr.user_id
             WHERE rr.round_id = ?
             ORDER BY rr.position ASC, rr.competitive_points DESC'
        );
        $stmt->execute([$roundId]);
        $rows = $stmt->fetchAll();

        $wStmt = $pdo->prepare(
            'SELECT id, user_id, original_text, letter_count, points_awarded, validation_status, rejection_reason
             FROM words WHERE round_id = ? ORDER BY points_awarded DESC, id ASC'
        );
        $wStmt->execute([$roundId]);
        $allWords = $wStmt->fetchAll();

        $wordsByUser = [];
        foreach ($allWords as $w) {
            $wordsByUser[(int) $w['user_id']][] = $w;
        }

        foreach ($rows as &$r) {
            $r['words'] = $wordsByUser[(int) $r['user_id']] ?? [];
        }

        return $rows;
    }

    public static function getMatchStandings(int $matchId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT mp.*, u.display_name, u.avatar_url, u.avatar_color, u.is_guest
             FROM match_players mp
             JOIN users u ON u.id = mp.user_id
             WHERE mp.match_id = ?
             ORDER BY COALESCE(mp.final_position, 99) ASC, mp.rounds_won DESC, mp.competitive_points DESC, mp.valid_word_count DESC'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve todas las rondas de una partida con el listado completo de palabras por participante.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getMatchAllRoundWords(int $matchId): array
    {
        $pdo = Database::connection();
        $rStmt = $pdo->prepare('SELECT id, round_number FROM rounds WHERE match_id = ? ORDER BY round_number ASC');
        $rStmt->execute([$matchId]);
        $rounds = $rStmt->fetchAll();

        $pStmt = $pdo->prepare(
            'SELECT mp.user_id, u.display_name, u.avatar_url, u.avatar_color
             FROM match_players mp
             JOIN users u ON u.id = mp.user_id
             WHERE mp.match_id = ?
             ORDER BY COALESCE(mp.final_position, 99) ASC, mp.competitive_points DESC'
        );
        $pStmt->execute([$matchId]);
        $players = $pStmt->fetchAll();

        $wStmt = $pdo->prepare(
            'SELECT w.id, w.round_id, w.user_id, w.original_text, w.letter_count, w.points_awarded, w.validation_status, w.rejection_reason
             FROM words w
             JOIN rounds r ON r.id = w.round_id
             WHERE r.match_id = ?
             ORDER BY w.points_awarded DESC, w.id ASC'
        );
        $wStmt->execute([$matchId]);
        $allWords = $wStmt->fetchAll();

        $byRoundUser = [];
        foreach ($allWords as $w) {
            $byRoundUser[(int) $w['round_id']][(int) $w['user_id']][] = $w;
        }

        $out = [];
        foreach ($rounds as $rd) {
            $rid = (int) $rd['id'];
            $pList = [];
            foreach ($players as $p) {
                $uid = (int) $p['user_id'];
                $pList[] = [
                    'user_id' => $uid,
                    'display_name' => $p['display_name'],
                    'avatar_url' => $p['avatar_url'],
                    'avatar_color' => $p['avatar_color'],
                    'words' => $byRoundUser[$rid][$uid] ?? [],
                ];
            }
            $out[] = [
                'round_id' => $rid,
                'round_number' => (int) $rd['round_number'],
                'players' => $pList,
            ];
        }

        return $out;
    }

    private static function recalculateFinishedMatchPositions(\PDO $pdo, int $matchId): void
    {
        $mpStmt = $pdo->prepare('SELECT * FROM match_players WHERE match_id = ?');
        $mpStmt->execute([$matchId]);
        $standings = $mpStmt->fetchAll();

        usort($standings, static function (array $a, array $b): int {
            if ((int) $a['rounds_won'] !== (int) $b['rounds_won']) {
                return (int) $b['rounds_won'] <=> (int) $a['rounds_won'];
            }
            if ((int) $a['competitive_points'] !== (int) $b['competitive_points']) {
                return (int) $b['competitive_points'] <=> (int) $a['competitive_points'];
            }
            return (int) $b['valid_word_count'] <=> (int) $a['valid_word_count'];
        });

        $topRounds = (int) ($standings[0]['rounds_won'] ?? 0);
        $topPoints = (int) ($standings[0]['competitive_points'] ?? 0);
        $topWinners = 0;
        foreach ($standings as $s) {
            if ($topPoints > 0 && (int) $s['rounds_won'] === $topRounds && (int) $s['competitive_points'] === $topPoints) {
                $topWinners++;
            }
        }
        $winnerUserId = ($topWinners === 1) ? (int) $standings[0]['user_id'] : null;

        $pos = 1;
        $upd = $pdo->prepare('UPDATE match_players SET final_position = ? WHERE id = ?');
        foreach ($standings as $idx => $s) {
            if ($idx > 0) {
                $prev = $standings[$idx - 1];
                if ((int) $s['rounds_won'] !== (int) $prev['rounds_won'] || (int) $s['competitive_points'] !== (int) $prev['competitive_points']) {
                    $pos = $idx + 1;
                }
            }
            $upd->execute([$pos, (int) $s['id']]);
        }

        $pdo->prepare('UPDATE matches SET winner_user_id = ? WHERE id = ?')->execute([$winnerUserId, $matchId]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function calculateWordPoints(int $length, string $scoringMode, array $config): int
    {
        if ($scoringMode === 'count') {
            return 1;
        }
        $table = $config['scoring']['length_points'];
        if ($length >= 6) {
            return (int) $table[6];
        }
        return (int) ($table[$length] ?? 1);
    }

    private static function recalculateRoundAndMatchTotals(\PDO $pdo, int $matchId, int $roundId): void
    {
        $mpStmt = $pdo->prepare('SELECT user_id FROM match_players WHERE match_id = ?');
        $mpStmt->execute([$matchId]);
        $playerIds = array_map('intval', $mpStmt->fetchAll(\PDO::FETCH_COLUMN));

        $wStmt = $pdo->prepare(
            "SELECT user_id, original_text, letter_count, points_awarded
             FROM words
             WHERE round_id = ? AND validation_status = 'valid'
             ORDER BY letter_count DESC, id ASC"
        );
        $wStmt->execute([$roundId]);
        $words = $wStmt->fetchAll();

        $byUser = [];
        foreach ($playerIds as $uid) {
            $byUser[$uid] = [
                'user_id' => $uid,
                'valid_word_count' => 0,
                'competitive_points' => 0,
                'longest_word' => null,
                'longest_len' => 0,
            ];
        }
        foreach ($words as $w) {
            $uid = (int) $w['user_id'];
            if (!isset($byUser[$uid])) {
                continue;
            }
            $byUser[$uid]['valid_word_count']++;
            $byUser[$uid]['competitive_points'] += (int) $w['points_awarded'];
            if ((int) $w['letter_count'] > $byUser[$uid]['longest_len']) {
                $byUser[$uid]['longest_len'] = (int) $w['letter_count'];
                $byUser[$uid]['longest_word'] = (string) $w['original_text'];
            }
        }

        $ranked = array_values($byUser);
        usort($ranked, static function (array $a, array $b): int {
            if ($a['competitive_points'] !== $b['competitive_points']) {
                return $b['competitive_points'] <=> $a['competitive_points'];
            }
            return $b['valid_word_count'] <=> $a['valid_word_count'];
        });

        $topScore = $ranked[0]['competitive_points'] ?? 0;
        $topCount = $ranked[0]['valid_word_count'] ?? 0;
        $winners = 0;
        foreach ($ranked as $r) {
            if ($topScore > 0 && $r['competitive_points'] === $topScore && $r['valid_word_count'] === $topCount) {
                $winners++;
            }
        }
        $winnerUserId = ($winners === 1) ? (int) $ranked[0]['user_id'] : null;

        $pos = 1;
        $updRr = $pdo->prepare(
            'UPDATE round_results SET valid_word_count = ?, competitive_points = ?, longest_word = ?, position = ?, is_winner = ? WHERE round_id = ? AND user_id = ?'
        );
        foreach ($ranked as $idx => $r) {
            if ($idx > 0) {
                $prev = $ranked[$idx - 1];
                if ($r['competitive_points'] !== $prev['competitive_points'] || $r['valid_word_count'] !== $prev['valid_word_count']) {
                    $pos = $idx + 1;
                }
            }
            $isW = ($topScore > 0 && $r['competitive_points'] === $topScore && $r['valid_word_count'] === $topCount) ? 1 : 0;
            $updRr->execute([$r['valid_word_count'], $r['competitive_points'], $r['longest_word'], $pos, $isW, $roundId, $r['user_id']]);
        }

        $pdo->prepare('UPDATE rounds SET winner_user_id = ? WHERE id = ?')->execute([$winnerUserId, $roundId]);

        // Recalcular acumulados de match_players desde round_results de la partida
        $aggStmt = $pdo->prepare(
            'SELECT rr.user_id,
                    SUM(rr.is_winner) AS rounds_won,
                    SUM(rr.valid_word_count) AS valid_word_count,
                    SUM(rr.competitive_points) AS competitive_points
             FROM round_results rr
             JOIN rounds r ON r.id = rr.round_id
             WHERE r.match_id = ?
             GROUP BY rr.user_id'
        );
        $aggStmt->execute([$matchId]);
        $agg = $aggStmt->fetchAll();

        $updMp = $pdo->prepare(
            'UPDATE match_players SET rounds_won = ?, valid_word_count = ?, competitive_points = ? WHERE match_id = ? AND user_id = ?'
        );
        foreach ($agg as $row) {
            $updMp->execute([
                (int) $row['rounds_won'],
                (int) $row['valid_word_count'],
                (int) $row['competitive_points'],
                $matchId,
                (int) $row['user_id'],
            ]);
        }
    }

    private static function grantRewardIdempotent(
        \PDO $pdo,
        int $userId,
        string $sourceType,
        int $sourceId,
        string $rewardType,
        int $amount,
        string $idempotencyKey,
        string $now
    ): void {
        if ($amount === 0) {
            return;
        }
        $check = $pdo->prepare('SELECT 1 FROM reward_transactions WHERE idempotency_key = ? LIMIT 1');
        $check->execute([$idempotencyKey]);
        if ($check->fetchColumn()) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO reward_transactions (user_id, source_type, source_id, reward_type, amount, idempotency_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$userId, $sourceType, $sourceId, $rewardType, $amount, $idempotencyKey, $now]);
    }
}
