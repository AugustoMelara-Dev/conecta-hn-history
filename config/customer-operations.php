<?php

declare(strict_types=1);

return [
    'groups' => [
        'sales' => [
            'label' => 'Ventas',
            'sla_minutes' => 90,
            'escalation_minutes' => 120,
        ],
        'retention' => [
            'label' => 'Retencion',
            'sla_minutes' => 180,
            'escalation_minutes' => 240,
        ],
        'appointments' => [
            'label' => 'Agenda',
            'sla_minutes' => 60,
            'escalation_minutes' => 90,
        ],
        'support' => [
            'label' => 'Soporte',
            'sla_minutes' => 120,
            'escalation_minutes' => 180,
        ],
    ],

    'segment_groups' => [
        'new' => 'sales',
        'prospect' => 'sales',
        'repeat' => 'retention',
        'active' => 'retention',
        'vip' => 'retention',
        'reactivation' => 'retention',
        'booked_service' => 'appointments',
    ],

    'business_segment_overrides' => [
        'dental_clinic' => [
            'group' => 'appointments',
            'sla_minutes' => 45,
            'escalation_minutes' => 75,
        ],
        'corporate_law_firm' => [
            'group' => 'sales',
            'sla_minutes' => 120,
            'escalation_minutes' => 180,
        ],
        'premium_barbershop' => [
            'group' => 'appointments',
            'sla_minutes' => 45,
            'escalation_minutes' => 75,
        ],
        'hardware_store' => [
            'group' => 'sales',
            'sla_minutes' => 75,
            'escalation_minutes' => 120,
        ],
        'premium_phone_store' => [
            'group' => 'sales',
            'sla_minutes' => 60,
            'escalation_minutes' => 90,
        ],
        'casual_restaurant' => [
            'group' => 'support',
            'sla_minutes' => 30,
            'escalation_minutes' => 45,
        ],
    ],

    'channels' => [
        'whatsapp' => [
            'sla_delta_minutes' => -15,
        ],
        'email' => [
            'sla_delta_minutes' => 30,
        ],
        'crm' => [
            'sla_delta_minutes' => 0,
        ],
    ],
];
