<?php

declare(strict_types=1);

return [
    'identity' => [
        'name' => 'Dovra AI',
        'short_name' => 'Dovra',
        'category' => 'Global Conversational Business OS',
        'commercial_promise' => 'Dovra deploys AI employees that know a business, work through its channels, and turn conversations into measurable results.',
        'origin_market' => 'HN',
    ],

    'legal' => [
        'entity_name' => env('DOVRA_LEGAL_ENTITY_NAME') ?: null,
        'name_clearance_confirmed' => (bool) env('DOVRA_NAME_CLEARANCE_CONFIRMED', false),
    ],
];
