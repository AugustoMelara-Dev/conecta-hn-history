<?php

declare(strict_types=1);

use App\Models\Tenant;

return [
    'teams' => [
        Tenant::CRM_TEAM_ACTIVATION => [
            'label' => 'Activacion',
            'focus' => 'Mover negocios nuevos a primer valor visible, oferta publicada y canal de cierre.',
            'queues' => ['activation'],
        ],
        Tenant::CRM_TEAM_GROWTH => [
            'label' => 'Growth',
            'focus' => 'Abrir upsell, plan PRO, bundles y expansion sobre cuentas que ya mostraron base real.',
            'queues' => ['growth'],
        ],
        Tenant::CRM_TEAM_BILLING => [
            'label' => 'Cobros',
            'focus' => 'Proteger continuidad de ingresos, renovaciones y pagos que no deben caer por friccion.',
            'queues' => ['billing'],
        ],
        Tenant::CRM_TEAM_RETENTION => [
            'label' => 'Retencion',
            'focus' => 'Rescatar cuentas en watchlist, evitar churn y sostener seguimiento sobre cartera caliente.',
            'queues' => ['retention'],
        ],
    ],
    'channels' => [
        'multi' => [
            'label' => 'Multi-canal',
            'copy' => 'WhatsApp y otro canal de cierre ya operan juntos.',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp directo',
            'copy' => 'El cierre principal ocurre por conversacion directa.',
        ],
        'website' => [
            'label' => 'Sitio web',
            'copy' => 'La cuenta depende mas del sitio para convertir y explicar valor.',
        ],
        'phone' => [
            'label' => 'Telefono directo',
            'copy' => 'La conversion se apoya en llamada o contacto telefonico.',
        ],
        'none' => [
            'label' => 'Sin canal directo',
            'copy' => 'La cuenta aun no deja un puente claro de cierre comercial.',
        ],
    ],
    'monthly_vertical_goals' => [
        'premium_phone_store' => [
            'label' => 'Tienda de celulares premium',
            'target' => 4,
            'focus' => 'Empuja bundles, aftercare, trade-in y cierres de mayor ticket hacia PRO.',
        ],
        'hardware_store' => [
            'label' => 'Ferreteria',
            'target' => 3,
            'focus' => 'Convierte cotizacion por proyecto y cobertura local en expansion de plan.',
        ],
        'dental_clinic' => [
            'label' => 'Clinica dental',
            'target' => 3,
            'focus' => 'Mueve agenda, valoracion y continuidad de tratamientos hacia una operacion PRO estable.',
        ],
        'corporate_law_firm' => [
            'label' => 'Bufete corporativo',
            'target' => 2,
            'focus' => 'Empuja discovery, retainer y seguimiento B2B con owner claro y cierre consultivo.',
        ],
        'financial_consultancy' => [
            'label' => 'Consultoria financiera',
            'target' => 2,
            'focus' => 'Convierte diagnosticos, recurrentes y fractional CFO en expansion comercial real.',
        ],
        'casual_restaurant' => [
            'label' => 'Restaurante casual',
            'target' => 4,
            'focus' => 'Convierte ticket, combos y recompra semanal en crecimiento medible.',
        ],
        'coffee_shop' => [
            'label' => 'Cafeteria',
            'target' => 3,
            'focus' => 'Empuja rutina, specials y comunidad a una operacion PRO que sostenga recompra.',
        ],
        'spa_studio' => [
            'label' => 'Spa',
            'target' => 2,
            'focus' => 'Mueve paquetes de bienestar, agenda y continuidad de tratamiento hacia mayor LTV.',
        ],
        'default' => [
            'target' => 2,
            'focus' => 'Combina activacion, oferta y owner comercial hasta cerrar conversiones PRO reales.',
        ],
    ],
];
