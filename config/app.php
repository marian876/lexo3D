<?php
declare(strict_types=1);

return [
    'name' => 'LEXO',
    'slogan' => 'El desafío de las palabras',
    'version' => '1.0.0',

    // Reglas configurables de salas (centralizadas, sin números mágicos dispersos)
    'room' => [
        'code_prefix' => 'LEX',
        'min_players' => 2,
        'max_players_options' => [2, 4, 6, 8, 12],
        'default_max_players' => 8,
        'round_counts' => [1, 3, 5, 10],
        'default_round_count' => 5,
        'round_durations' => [30, 60, 90, 120],
        'default_round_duration' => 60,
        'scoring_modes' => [
            'count' => 'Por cantidad de palabras',
            'length' => 'Por longitud de palabra',
        ],
        'default_scoring_mode' => 'length',
        'validation_modes' => [
            'table' => 'Mesa libre (consenso físico + reglas ES)',
            'dictionary' => 'Diccionario español local',
        ],
        'default_validation_mode' => 'table',
        'min_word_lengths' => [2, 3, 4],
        'default_min_word_length' => 3,
        'room_ttl_hours' => 12,
    ],

    // Reglas de puntuación por longitud (lexo.md §10.1)
    'scoring' => [
        'length_points' => [
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5, // 6 o más letras = 5 puntos
        ],
        'round_win_competitive_points' => 10,
        'match_win_bonus_points' => 25,
    ],

    // Experiencia y niveles (lexo.md §10.3)
    'progression' => [
        'xp_per_match_played' => 10,
        'xp_per_round_won' => 5,
        'xp_per_match_won' => 20,
        'xp_per_level' => 100,
    ],

    // Polling adaptativo amigable con Hostinger compartido (ms)
    'sync' => [
        'lobby_poll_ms' => 2500,
        'match_poll_ms' => 3000,
        'results_poll_ms' => 2500,
        'grace_period_ms' => 1500, // Tolerancia mínima de red al cierre
    ],
];
