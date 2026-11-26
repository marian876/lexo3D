-- ============================================================================
-- LEXO — Esquema MySQL 8 / MariaDB para Hostinger
-- Ejecutar una sola vez en phpMyAdmin o MySQL CLI.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_sub VARCHAR(128) NULL UNIQUE,
  email VARCHAR(190) NULL,
  first_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NULL,
  display_name VARCHAR(60) NOT NULL,
  avatar_url VARCHAR(500) NULL,
  avatar_color VARCHAR(16) NOT NULL DEFAULT '#FF5A36',
  is_guest TINYINT(1) NOT NULL DEFAULT 0,
  role ENUM('player', 'admin') NOT NULL DEFAULT 'player',
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  host_user_id INT UNSIGNED NOT NULL,
  status ENUM('waiting', 'playing', 'finished', 'closed') NOT NULL DEFAULT 'waiting',
  max_players TINYINT UNSIGNED NOT NULL DEFAULT 8,
  round_count TINYINT UNSIGNED NOT NULL DEFAULT 5,
  round_duration_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  scoring_mode ENUM('count', 'length') NOT NULL DEFAULT 'length',
  validation_mode ENUM('table', 'dictionary') NOT NULL DEFAULT 'table',
  min_word_length TINYINT UNSIGNED NOT NULL DEFAULT 3,
  language VARCHAR(10) NOT NULL DEFAULT 'es',
  created_at DATETIME NOT NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  CONSTRAINT fk_rooms_host FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('active', 'left') NOT NULL DEFAULT 'active',
  is_host TINYINT(1) NOT NULL DEFAULT 0,
  is_ready TINYINT(1) NOT NULL DEFAULT 1,
  connection_state ENUM('online', 'offline') NOT NULL DEFAULT 'online',
  last_seen_at DATETIME NOT NULL,
  joined_at DATETIME NOT NULL,
  left_at DATETIME NULL,
  UNIQUE KEY uq_room_user (room_id, user_id),
  CONSTRAINT fk_room_players_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_room_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  status ENUM('active', 'finished', 'cancelled') NOT NULL DEFAULT 'active',
  scoring_mode_snapshot ENUM('count', 'length') NOT NULL,
  validation_mode_snapshot ENUM('table', 'dictionary') NOT NULL,
  min_word_length_snapshot TINYINT UNSIGNED NOT NULL,
  round_count_snapshot TINYINT UNSIGNED NOT NULL,
  round_duration_snapshot SMALLINT UNSIGNED NOT NULL,
  winner_user_id INT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_matches_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_winner FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  final_position TINYINT UNSIGNED NULL,
  rounds_won TINYINT UNSIGNED NOT NULL DEFAULT 0,
  valid_word_count INT UNSIGNED NOT NULL DEFAULT 0,
  competitive_points INT UNSIGNED NOT NULL DEFAULT 0,
  experience_earned INT UNSIGNED NOT NULL DEFAULT 0,
  stars_earned INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('playing', 'finished', 'left') NOT NULL DEFAULT 'playing',
  UNIQUE KEY uq_match_user (match_id, user_id),
  CONSTRAINT fk_match_players_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rounds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id INT UNSIGNED NOT NULL,
  round_number TINYINT UNSIGNED NOT NULL,
  status ENUM('active', 'finished') NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  started_at_ms BIGINT UNSIGNED NOT NULL,
  ends_at_ms BIGINT UNSIGNED NOT NULL,
  finished_at DATETIME NULL,
  winner_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_match_round (match_id, round_number),
  CONSTRAINT fk_rounds_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_rounds_winner FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS words (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  round_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  original_text VARCHAR(64) NOT NULL,
  normalized_text VARCHAR(64) NOT NULL,
  letter_count TINYINT UNSIGNED NOT NULL,
  points_awarded SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  validation_status ENUM('valid', 'rejected') NOT NULL DEFAULT 'valid',
  validation_source ENUM('table', 'dictionary', 'format') NOT NULL DEFAULT 'table',
  rejection_reason VARCHAR(160) NULL,
  submitted_at DATETIME NOT NULL,
  UNIQUE KEY uq_round_user_word (round_id, user_id, normalized_text),
  KEY idx_words_round_user (round_id, user_id),
  CONSTRAINT fk_words_round FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_words_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS round_results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  round_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  valid_word_count INT UNSIGNED NOT NULL DEFAULT 0,
  competitive_points INT UNSIGNED NOT NULL DEFAULT 0,
  longest_word VARCHAR(64) NULL,
  position TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_winner TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_round_result_user (round_id, user_id),
  CONSTRAINT fk_round_results_round FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_round_results_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_stats (
  user_id INT UNSIGNED PRIMARY KEY,
  matches_played INT UNSIGNED NOT NULL DEFAULT 0,
  matches_won INT UNSIGNED NOT NULL DEFAULT 0,
  rounds_played INT UNSIGNED NOT NULL DEFAULT 0,
  rounds_won INT UNSIGNED NOT NULL DEFAULT 0,
  valid_words_total INT UNSIGNED NOT NULL DEFAULT 0,
  competitive_points_total INT UNSIGNED NOT NULL DEFAULT 0,
  experience_total INT UNSIGNED NOT NULL DEFAULT 0,
  stars_total INT UNSIGNED NOT NULL DEFAULT 0,
  current_win_streak INT UNSIGNED NOT NULL DEFAULT 0,
  best_win_streak INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_player_stats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achievements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(200) NOT NULL,
  icon VARCHAR(16) NOT NULL DEFAULT '🏆',
  condition_type VARCHAR(40) NOT NULL,
  condition_value INT UNSIGNED NOT NULL DEFAULT 1,
  reward_points INT UNSIGNED NOT NULL DEFAULT 0,
  reward_experience INT UNSIGNED NOT NULL DEFAULT 15,
  reward_stars INT UNSIGNED NOT NULL DEFAULT 1,
  is_repeatable TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_achievements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  achievement_id INT UNSIGNED NOT NULL,
  unlocked_at DATETIME NOT NULL,
  reward_granted TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_user_achievement (user_id, achievement_id),
  CONSTRAINT fk_player_ach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_player_ach_def FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reward_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  source_type VARCHAR(40) NOT NULL,
  source_id INT UNSIGNED NOT NULL,
  reward_type ENUM('points', 'xp', 'stars') NOT NULL,
  amount INT NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_reward_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO achievements (code, name, description, icon, condition_type, condition_value, reward_points, reward_experience, reward_stars, is_repeatable, is_active) VALUES
('first_round_win', 'Primera chispa', 'Ganá tu primera ronda en una mesa.', '⚡', 'rounds_won', 1, 5, 15, 1, 0, 1),
('first_match_win', 'Campeón de mesa', 'Ganá una partida completa.', '👑', 'matches_won', 1, 10, 25, 1, 0, 1),
('speedster_8', 'Velocista léxico', 'Registrá 8 o más palabras válidas en una sola ronda.', '🚀', 'round_words', 8, 10, 20, 1, 0, 1),
('word_master_50', 'Maestro de palabras', 'Acumulá 50 palabras válidas en tu historial.', '📚', 'valid_words_total', 50, 15, 30, 2, 0, 1),
('streak_3', 'Imparable', 'Alcanzá una racha de 3 partidas ganadas seguidas.', '🔥', 'best_win_streak', 3, 20, 40, 2, 0, 1),
('constant_5', 'Habitual de la mesa', 'Completá 5 partidas en LEXO.', '🎲', 'matches_played', 5, 10, 25, 1, 0, 1);

SET FOREIGN_KEY_CHECKS = 1;
