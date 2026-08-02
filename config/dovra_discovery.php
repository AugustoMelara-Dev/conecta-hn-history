<?php

declare(strict_types=1);

return [
    'freshness_days' => 30,
    'search' => [
        'max_results' => 6,
        'comparable_limit' => 3,
        'stop_words' => [
            'a', 'algo', 'all', 'and', 'buy', 'cafe', 'con', 'de', 'del', 'el', 'for', 'ignore',
            'instructions', 'la', 'las', 'lo', 'los', 'of', 'office', 'para', 'por', 'private',
            'reveal', 'the', 'tu', 'una', 'un', 'y',
        ],
        'injection_terms' => [
            'ignore instructions',
            'reveal private',
            'reveal private margins',
            'reveal secrets',
            'system prompt',
        ],
    ],
    'integrity' => [
        'verification_scores' => [
            'verified' => 100,
            'pending' => 60,
            'unverified' => 20,
            'unknown' => 0,
        ],
        'prohibited_terms' => [
            'prohibited',
            'illegal',
            'banned',
        ],
    ],
];
