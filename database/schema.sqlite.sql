-- ============================================================================
-- LEXO — Esquema SQLite para desarrollo local y pruebas automatizadas
-- ============================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  google_sub TEXT NULL UNIQUE,
  email TEXT NULL,
  first_name TEXT NULL,
  last_name TEXT NULL,
  display_name TEXT NOT NULL,
  avatar_url TEXT NULL,
  avatar_color TEXT NOT NULL DEFAULT '#FF5A36',
  is_guest INTEGER NOT NULL DEFAULT 0,
  role TEXT NOT NULL DEFAULT 'player',
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  last_login_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS rooms (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  host_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'waiting',
  max_players INTEGER NOT NULL DEFAULT 8,
  round_count INTEGER NOT NULL DEFAULT 5,
  round_duration_seconds INTEGER NOT NULL DEFAULT 60,
  scoring_mode TEXT NOT NULL DEFAULT 'length',
  validation_mode TEXT NOT NULL DEFAULT 'table',
  min_word_length INTEGER NOT NULL DEFAULT 3,
  sound_enabled INTEGER NOT NULL DEFAULT 1,
  language TEXT NOT NULL DEFAULT 'es',
  created_at TEXT NOT NULL,
  started_at TEXT NULL,
  finished_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS room_players (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'active',
  is_host INTEGER NOT NULL DEFAULT 0,
  is_ready INTEGER NOT NULL DEFAULT 1,
  connection_state TEXT NOT NULL DEFAULT 'online',
  last_seen_at TEXT NOT NULL,
  joined_at TEXT NOT NULL,
  left_at TEXT NULL,
  UNIQUE (room_id, user_id)
);

CREATE TABLE IF NOT EXISTS matches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'active',
  scoring_mode_snapshot TEXT NOT NULL,
  validation_mode_snapshot TEXT NOT NULL,
  min_word_length_snapshot INTEGER NOT NULL,
  round_count_snapshot INTEGER NOT NULL,
  round_duration_snapshot INTEGER NOT NULL,
  winner_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  started_at TEXT NOT NULL,
  finished_at TEXT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS match_players (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  match_id INTEGER NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  final_position INTEGER NULL,
  rounds_won INTEGER NOT NULL DEFAULT 0,
  valid_word_count INTEGER NOT NULL DEFAULT 0,
  competitive_points INTEGER NOT NULL DEFAULT 0,
  experience_earned INTEGER NOT NULL DEFAULT 0,
  stars_earned INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'playing',
  UNIQUE (match_id, user_id)
);

CREATE TABLE IF NOT EXISTS rounds (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  match_id INTEGER NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
  round_number INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  started_at TEXT NOT NULL,
  ends_at TEXT NOT NULL,
  started_at_ms INTEGER NOT NULL,
  ends_at_ms INTEGER NOT NULL,
  finished_at TEXT NULL,
  winner_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL,
  UNIQUE (match_id, round_number)
);

CREATE TABLE IF NOT EXISTS words (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  round_id INTEGER NOT NULL REFERENCES rounds(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  original_text TEXT NOT NULL,
  normalized_text TEXT NOT NULL,
  letter_count INTEGER NOT NULL,
  points_awarded INTEGER NOT NULL DEFAULT 0,
  validation_status TEXT NOT NULL DEFAULT 'valid',
  validation_source TEXT NOT NULL DEFAULT 'table',
  rejection_reason TEXT NULL,
  submitted_at TEXT NOT NULL,
  UNIQUE (round_id, user_id, normalized_text)
);
CREATE INDEX IF NOT EXISTS idx_words_round_user ON words(round_id, user_id);

CREATE TABLE IF NOT EXISTS round_results (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  round_id INTEGER NOT NULL REFERENCES rounds(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  valid_word_count INTEGER NOT NULL DEFAULT 0,
  competitive_points INTEGER NOT NULL DEFAULT 0,
  longest_word TEXT NULL,
  position INTEGER NOT NULL DEFAULT 1,
  is_winner INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  UNIQUE (round_id, user_id)
);

CREATE TABLE IF NOT EXISTS player_stats (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  matches_played INTEGER NOT NULL DEFAULT 0,
  matches_won INTEGER NOT NULL DEFAULT 0,
  rounds_played INTEGER NOT NULL DEFAULT 0,
  rounds_won INTEGER NOT NULL DEFAULT 0,
  valid_words_total INTEGER NOT NULL DEFAULT 0,
  competitive_points_total INTEGER NOT NULL DEFAULT 0,
  experience_total INTEGER NOT NULL DEFAULT 0,
  stars_total INTEGER NOT NULL DEFAULT 0,
  current_win_streak INTEGER NOT NULL DEFAULT 0,
  best_win_streak INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS achievements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT NOT NULL,
  icon TEXT NOT NULL DEFAULT '🏆',
  condition_type TEXT NOT NULL,
  condition_value INTEGER NOT NULL DEFAULT 1,
  reward_points INTEGER NOT NULL DEFAULT 0,
  reward_experience INTEGER NOT NULL DEFAULT 15,
  reward_stars INTEGER NOT NULL DEFAULT 1,
  is_repeatable INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS player_achievements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  achievement_id INTEGER NOT NULL REFERENCES achievements(id) ON DELETE CASCADE,
  unlocked_at TEXT NOT NULL,
  reward_granted INTEGER NOT NULL DEFAULT 1,
  UNIQUE (user_id, achievement_id)
);

CREATE TABLE IF NOT EXISTS reward_transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  source_type TEXT NOT NULL,
  source_id INTEGER NOT NULL,
  reward_type TEXT NOT NULL,
  amount INTEGER NOT NULL,
  idempotency_key TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

INSERT OR IGNORE INTO achievements (code, name, description, icon, condition_type, condition_value, reward_points, reward_experience, reward_stars, is_repeatable, is_active) VALUES
('first_round_win', 'Primera chispa', 'Ganá tu primera ronda en una mesa.', '⚡', 'rounds_won', 1, 5, 15, 1, 0, 1),
('first_match_win', 'Campeón de mesa', 'Ganá una partida completa.', '👑', 'matches_won', 1, 10, 25, 1, 0, 1),
('speedster_8', 'Velocista léxico', 'Registrá 8 o más palabras válidas en una sola ronda.', '🚀', 'round_words', 8, 10, 20, 1, 0, 1),
('word_master_50', 'Maestro de palabras', 'Acumulá 50 palabras válidas en tu historial.', '📚', 'valid_words_total', 50, 15, 30, 2, 0, 1),
('streak_3', 'Imparable', 'Alcanzá una racha de 3 partidas ganadas seguidas.', '🔥', 'best_win_streak', 3, 20, 40, 2, 0, 1),
('constant_5', 'Habitual de la mesa', 'Completá 5 partidas en LEXO.', '🎲', 'matches_played', 5, 10, 25, 1, 0, 1);
