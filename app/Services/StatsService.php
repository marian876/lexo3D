<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use PDO;

final class StatsService
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function getProfileSummary(int $userId, array $config): array
    {
        AuthService::ensureStatsRow($userId);
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM player_stats WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $stats = $stmt->fetch() ?: [];

        $xpPerLevel = max(1, (int) ($config['progression']['xp_per_level'] ?? 100));
        $xpTotal = (int) ($stats['experience_total'] ?? 0);
        $level = (int) floor($xpTotal / $xpPerLevel) + 1;
        $xpIntoLevel = $xpTotal % $xpPerLevel;
        $levelProgressPct = (int) round(($xpIntoLevel / $xpPerLevel) * 100);

        $played = (int) ($stats['matches_played'] ?? 0);
        $won = (int) ($stats['matches_won'] ?? 0);
        $winRate = $played > 0 ? (int) round(($won / $played) * 100) : 0;

        return [
            'stats' => $stats,
            'level' => $level,
            'xp_into_level' => $xpIntoLevel,
            'xp_for_next' => $xpPerLevel,
            'level_progress_pct' => $levelProgressPct,
            'win_rate' => $winRate,
        ];
    }

    public static function getUserAchievements(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT a.*, pa.unlocked_at
             FROM achievements a
             LEFT JOIN player_achievements pa ON pa.achievement_id = a.id AND pa.user_id = ?
             WHERE a.is_active = 1
             ORDER BY (pa.unlocked_at IS NULL) ASC, a.id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getMatchHistory(int $userId, int $limit = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT m.id AS match_id, m.started_at, m.finished_at, m.round_count_snapshot,
                    r.code AS room_code, r.name AS room_name, r.max_players,
                    mp.final_position, mp.rounds_won, mp.valid_word_count, mp.competitive_points,
                    mp.experience_earned, mp.stars_earned
             FROM match_players mp
             JOIN matches m ON m.id = mp.match_id
             JOIN rooms r ON r.id = m.room_id
             WHERE mp.user_id = ? AND m.status = ?
             ORDER BY m.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, 'finished', PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getLeaderboard(string $orderBy = 'points', int $limit = 30): array
    {
        $column = match ($orderBy) {
            'wins' => 'COALESCE(ps.matches_won, 0)',
            'xp' => 'COALESCE(ps.experience_total, 0)',
            default => 'COALESCE(ps.competitive_points_total, 0)',
        };

        $pdo = Database::connection();
        // Privacidad (lexo.md §8.14): nunca exponer email ni google_sub en rankings públicos
        $stmt = $pdo->prepare(
            "SELECT u.id, u.display_name, u.avatar_url, u.avatar_color, u.is_guest,
                    COALESCE(ps.matches_played, 0) AS matches_played,
                    COALESCE(ps.matches_won, 0) AS matches_won,
                    COALESCE(ps.valid_words_total, 0) AS valid_words_total,
                    COALESCE(ps.competitive_points_total, 0) AS competitive_points_total,
                    COALESCE(ps.experience_total, 0) AS experience_total,
                    COALESCE(ps.stars_total, 0) AS stars_total,
                    COALESCE(ps.best_win_streak, 0) AS best_win_streak
             FROM users u
             LEFT JOIN player_stats ps ON ps.user_id = u.id
             WHERE u.status = 'active' AND (u.is_guest = 0 OR COALESCE(ps.matches_played, 0) > 0)
             ORDER BY {$column} DESC, COALESCE(ps.matches_won, 0) DESC, COALESCE(ps.stars_total, 0) DESC, u.created_at ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array{xp: int, stars: int}
     */
    public static function evaluateAndUnlockAchievements(PDO $pdo, int $userId, int $matchId, string $now): array
    {
        $psStmt = $pdo->prepare('SELECT * FROM player_stats WHERE user_id = ? LIMIT 1');
        $psStmt->execute([$userId]);
        $stats = $psStmt->fetch();
        if (!$stats) {
            return ['xp' => 0, 'stars' => 0];
        }

        // Máximo de palabras válidas en una ronda de esta partida para evaluar logro "Velocista"
        $maxRoundWordsStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(rr.valid_word_count), 0)
             FROM round_results rr
             JOIN rounds r ON r.id = rr.round_id
             WHERE r.match_id = ? AND rr.user_id = ?'
        );
        $maxRoundWordsStmt->execute([$matchId, $userId]);
        $maxRoundWords = (int) $maxRoundWordsStmt->fetchColumn();

        $achStmt = $pdo->query('SELECT * FROM achievements WHERE is_active = 1');
        $achievements = $achStmt ? $achStmt->fetchAll() : [];

        $bonusXp = 0;
        $bonusStars = 0;

        foreach ($achievements as $ach) {
            $owned = $pdo->prepare('SELECT 1 FROM player_achievements WHERE user_id = ? AND achievement_id = ? LIMIT 1');
            $owned->execute([$userId, (int) $ach['id']]);
            if ($owned->fetchColumn()) {
                continue;
            }

            $metric = match ($ach['condition_type']) {
                'rounds_won' => (int) $stats['rounds_won'],
                'matches_won' => (int) $stats['matches_won'],
                'matches_played' => (int) $stats['matches_played'],
                'valid_words_total' => (int) $stats['valid_words_total'],
                'best_win_streak' => (int) $stats['best_win_streak'],
                'round_words' => $maxRoundWords,
                default => 0,
            };

            if ($metric >= (int) $ach['condition_value']) {
                $pdo->prepare(
                    'INSERT INTO player_achievements (user_id, achievement_id, unlocked_at, reward_granted) VALUES (?, ?, ?, 1)'
                )->execute([$userId, (int) $ach['id'], $now]);

                $axp = (int) $ach['reward_experience'];
                $astars = (int) $ach['reward_stars'];
                $apts = (int) $ach['reward_points'];
                $bonusXp += $axp;
                $bonusStars += $astars;

                $pdo->prepare(
                    'UPDATE player_stats
                     SET competitive_points_total = competitive_points_total + ?,
                         experience_total = experience_total + ?,
                         stars_total = stars_total + ?,
                         updated_at = ?
                     WHERE user_id = ?'
                )->execute([$apts, $axp, $astars, $now, $userId]);
            }
        }

        return ['xp' => $bonusXp, 'stars' => $bonusStars];
    }

    public static function getMatchUnlockedAchievements(int $matchId, int $userId): array
    {
        $pdo = Database::connection();
        $mStmt = $pdo->prepare('SELECT started_at FROM matches WHERE id = ? LIMIT 1');
        $mStmt->execute([$matchId]);
        $startedAt = $mStmt->fetchColumn();
        if (!$startedAt) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT a.code, a.name, a.description, a.icon, a.reward_stars, a.reward_experience
             FROM player_achievements pa
             JOIN achievements a ON a.id = pa.achievement_id
             WHERE pa.user_id = ? AND pa.unlocked_at >= ?
             ORDER BY pa.id DESC'
        );
        $stmt->execute([$userId, $startedAt]);
        return $stmt->fetchAll();
    }
}
