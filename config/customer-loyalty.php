<?php

declare(strict_types=1);

return [
    'default_earn_ratio' => 10,

    'tiers' => [
        'base' => [
            'label' => 'Base',
            'copy' => 'Todavia esta construyendo historial. Enfoca continuidad y segunda compra.',
        ],
        'silver' => [
            'label' => 'Silver',
            'copy' => 'Ya vale la pena ofrecer continuidad, bundle suave o bono de regreso.',
        ],
        'gold' => [
            'label' => 'Gold',
            'copy' => 'Cliente de alto valor. Conviene mover recompensa visible y prioridad comercial.',
        ],
        'vip' => [
            'label' => 'VIP',
            'copy' => 'Cliente estrategico. Merece beneficio premium, trato preferencial y seguimiento humano.',
        ],
    ],

    'conversation' => [
        'window_hours' => 72,
        'follow_up_grace_hours' => 48,
    ],
];
