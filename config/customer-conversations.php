<?php

declare(strict_types=1);

return [
    'defaults' => [
        'window_hours' => 168,
        'thread_limit' => 16,
    ],

    'templates' => [
        'fast_follow_up' => [
            'label' => 'Seguimiento rapido',
            'description' => 'Responder hoy con contexto corto y CTA directo.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_fast_follow_up',
            'requires_marketing_opt_in' => false,
            'subject' => 'Seguimos atentos desde {business_name}',
            'body' => "Hola {customer_name},\n\nGracias por escribirnos. Ya revisamos tu consulta y te podemos ayudar con el siguiente paso sin complicarte el proceso.\n\nSi te sirve, responde este mensaje o entra aqui: {cta_url}\n\nQuedamos pendientes.",
            'cta_label' => 'Continuar ahora',
            'template_name' => 'conversation_fast_follow_up',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 1,
        ],
        'close_sale' => [
            'label' => 'Cerrar venta',
            'description' => 'Empujar cierre con siguiente paso comercial claro.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_close_sale',
            'requires_marketing_opt_in' => false,
            'subject' => 'Tu siguiente paso con {business_name} esta listo',
            'body' => "Hola {customer_name},\n\nYa dejamos listo el siguiente paso para cerrar contigo de forma simple y clara.\n\nPuedes retomarlo aqui: {cta_url}\n\nSi prefieres, responde este mensaje y lo vemos contigo.",
            'cta_label' => 'Cerrar ahora',
            'template_name' => 'conversation_close_sale',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 2,
        ],
        'service_rebook' => [
            'label' => 'Reagendar servicio',
            'description' => 'Mover continuidad, cita o control con urgencia sana.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_service_rebook',
            'requires_marketing_opt_in' => false,
            'subject' => 'Podemos dejar tu siguiente cita lista en {business_name}',
            'body' => "Hola {customer_name},\n\nPodemos ayudarte a dejar tu siguiente cita, control o servicio ya confirmado.\n\nRevisa el siguiente paso aqui: {cta_url}\n\nSi quieres que te ayudemos personalmente, responde este mensaje.",
            'cta_label' => 'Agendar siguiente paso',
            'template_name' => 'conversation_service_rebook',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 1,
        ],
        'vip_priority' => [
            'label' => 'Atencion VIP',
            'description' => 'Respuesta blanca con tono de experiencia premium.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_vip_priority',
            'requires_marketing_opt_in' => false,
            'subject' => 'Seguimiento prioritario de {business_name}',
            'body' => "Hola {customer_name},\n\nTe estamos dando seguimiento prioritario para que no pierdas tiempo y puedas avanzar con claridad.\n\nPuedes retomar aqui: {cta_url}\n\nSi prefieres, responde este mensaje y te atendemos con prioridad.",
            'cta_label' => 'Retomar con prioridad',
            'template_name' => 'conversation_vip_priority',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 1,
        ],
        'reactivation_recovery' => [
            'label' => 'Recuperar cliente',
            'description' => 'Traer de vuelta una oportunidad tibia o dormida.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_reactivation_recovery',
            'requires_marketing_opt_in' => true,
            'subject' => 'Todavia estamos a tiempo de retomar en {business_name}',
            'body' => "Hola {customer_name},\n\nTodavia estamos a tiempo de retomar tu caso, compra o servicio con una salida simple.\n\nPuedes volver aqui: {cta_url}\n\nSi prefieres, responde este mensaje y te ayudamos personalmente.",
            'cta_label' => 'Retomar ahora',
            'template_name' => 'conversation_reactivation_recovery',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 2,
        ],
    ],

    'segments' => [
        'vip' => [
            'journey' => [
                ['title' => 'Responder en la ventana premium', 'copy' => 'Tomar ownership y responder con prioridad real antes de 4 horas.'],
                ['title' => 'Mover el siguiente paso', 'copy' => 'No dejes la conversacion abierta sin CTA claro de cita, compra o llamada.'],
                ['title' => 'Cerrar con experiencia', 'copy' => 'Si se resuelve, deja beneficio, seguimiento o invitacion a siguiente compra.'],
            ],
            'templates' => ['vip_priority', 'close_sale', 'fast_follow_up'],
        ],
        'repeat' => [
            'journey' => [
                ['title' => 'Responder rapido', 'copy' => 'No dejes que una recompra se enfrie por falta de seguimiento humano.'],
                ['title' => 'Conectar bundle o reward', 'copy' => 'Usa fidelidad o continuidad como palanca del siguiente paso.'],
                ['title' => 'Cerrar continuidad', 'copy' => 'Busca salida a recompra, paquete o nueva cita.'],
            ],
            'templates' => ['close_sale', 'fast_follow_up', 'service_rebook'],
        ],
        'reactivation' => [
            'journey' => [
                ['title' => 'Volver a abrir interes', 'copy' => 'Recupera contexto y baja friccion en el siguiente paso.'],
                ['title' => 'Ofrecer motivo de regreso', 'copy' => 'Usa incentivo claro, control, bundle o regreso asistido.'],
                ['title' => 'Cerrar en esta semana', 'copy' => 'No dejes la reactivacion sin fecha o CTA concreto.'],
            ],
            'templates' => ['reactivation_recovery', 'fast_follow_up', 'close_sale'],
        ],
        'booked_service' => [
            'journey' => [
                ['title' => 'Confirmar continuidad', 'copy' => 'Conecta la respuesta del cliente con la siguiente cita o control.'],
                ['title' => 'Reducir no-show', 'copy' => 'Reagenda rapido y deja siguiente accion visible.'],
                ['title' => 'Abrir siguiente paquete', 'copy' => 'Si hay buena experiencia, propone continuidad o plan.'],
            ],
            'templates' => ['service_rebook', 'fast_follow_up', 'vip_priority'],
        ],
        'active' => [
            'journey' => [
                ['title' => 'Mantener ritmo', 'copy' => 'Responde rapido y no dejes dudas abiertas en clientes activos.'],
                ['title' => 'Detectar upsell', 'copy' => 'Mira si hay oportunidad de bundle, upgrade o cita siguiente.'],
                ['title' => 'Documentar continuidad', 'copy' => 'Deja owner, sede y proximo toque claros.'],
            ],
            'templates' => ['fast_follow_up', 'close_sale', 'service_rebook'],
        ],
        'prospect' => [
            'journey' => [
                ['title' => 'Responder hoy', 'copy' => 'La primera velocidad importa mas que el texto perfecto.'],
                ['title' => 'Aclarar propuesta', 'copy' => 'Reduce friccion y deja CTA simple de cierre o discovery.'],
                ['title' => 'No perder el seguimiento', 'copy' => 'Si no responde, reprograma siguiente toque con owner claro.'],
            ],
            'templates' => ['close_sale', 'fast_follow_up', 'reactivation_recovery'],
        ],
        'new' => [
            'journey' => [
                ['title' => 'Bienvenida clara', 'copy' => 'Muestra propuesta principal y siguiente paso.'],
                ['title' => 'Guiar primer movimiento', 'copy' => 'No dejes al cliente adivinando que hacer.'],
                ['title' => 'Cerrar primer hito', 'copy' => 'Busca primera compra, cita o llamada cerrada.'],
            ],
            'templates' => ['fast_follow_up', 'close_sale', 'service_rebook'],
        ],
    ],
];
