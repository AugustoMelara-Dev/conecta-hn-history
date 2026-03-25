<?php

declare(strict_types=1);

return [
    'segments' => [
        'vip' => [
            'label' => 'VIP',
            'promise' => 'Clientes con alto valor que merecen trato preferente.',
            'next_touch' => 'Llamado personal o WhatsApp premium en menos de 24 horas.',
            'loyalty_move' => 'Ofrece acceso anticipado, bundle premium o beneficio exclusivo.',
            'response_sla_hours' => 4,
        ],
        'repeat' => [
            'label' => 'Recurrente',
            'promise' => 'Clientes que ya demostraron recompra o continuidad.',
            'next_touch' => 'Refuerza recompra con incentivo puntual y seguimiento corto.',
            'loyalty_move' => 'Activa programa de puntos o recompensa de siguiente compra.',
            'response_sla_hours' => 12,
        ],
        'reactivation' => [
            'label' => 'Reactivacion',
            'promise' => 'Clientes con senales de haber bajado ritmo pero recuperables.',
            'next_touch' => 'Toca con mensaje de retorno y oferta clara esta semana.',
            'loyalty_move' => 'Usa beneficio de regreso y recordatorio de valor acumulado.',
            'response_sla_hours' => 24,
        ],
        'booked_service' => [
            'label' => 'Con citas',
            'promise' => 'Clientes de servicio que ya reservaron y piden continuidad.',
            'next_touch' => 'Confirma experiencia y abre siguiente cita antes de 48 horas.',
            'loyalty_move' => 'Convierte continuidad en plan, control o paquete recurrente.',
            'response_sla_hours' => 8,
        ],
        'active' => [
            'label' => 'Activo',
            'promise' => 'Clientes comprando o reservando con flujo saludable.',
            'next_touch' => 'Mantén presencia ligera y detecta upsell natural.',
            'loyalty_move' => 'Empuja puntos, cupon de continuidad o bundle suave.',
            'response_sla_hours' => 12,
        ],
        'prospect' => [
            'label' => 'Prospecto',
            'promise' => 'Interes real, pero todavia sin conversion cerrada.',
            'next_touch' => 'Responde hoy con CTA simple y friccion minima.',
            'loyalty_move' => 'No fuerces loyalty aun; enfocate en cerrar primera conversion.',
            'response_sla_hours' => 4,
        ],
        'new' => [
            'label' => 'Nuevo',
            'promise' => 'Cliente recien detectado o con muy poca historia.',
            'next_touch' => 'Presenta la oferta principal y guia el primer paso.',
            'loyalty_move' => 'Explica como gana valor si vuelve o completa perfil.',
            'response_sla_hours' => 24,
        ],
    ],
    'loyalty_tiers' => [
        'vip' => 'Tiene base para experiencia premium y referidos.',
        'gold' => 'Ya amerita incentivo de retencion visible.',
        'silver' => 'Listo para empujar una siguiente compra o cita.',
        'base' => 'Conviene llevarlo a su primer hito de fidelidad.',
    ],
];
