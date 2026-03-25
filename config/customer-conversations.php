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
        'follow_up_reminder' => [
            'label' => 'Recordatorio de seguimiento',
            'description' => 'Segundo toque para rescatar una conversacion que se enfrio.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_follow_up_reminder',
            'requires_marketing_opt_in' => false,
            'subject' => 'Te dejamos un recordatorio rapido de {business_name}',
            'body' => "Hola {customer_name},\n\nTe dejamos un recordatorio rapido para que no se enfrie este seguimiento.\n\nSi quieres retomarlo ahora, entra aqui: {cta_url}\n\nSi prefieres, responde este mensaje y te ayudamos personalmente.",
            'cta_label' => 'Retomar seguimiento',
            'template_name' => 'conversation_follow_up_reminder',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 2,
        ],
        'final_close_push' => [
            'label' => 'Empuje final de cierre',
            'description' => 'Ultimo toque claro para llevar la conversacion a cierre o decision.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_final_close_push',
            'requires_marketing_opt_in' => false,
            'subject' => 'Tu siguiente decision con {business_name} sigue abierta',
            'body' => "Hola {customer_name},\n\nTodavia podemos dejar esto cerrado contigo de forma simple y clara.\n\nRetomalo aqui: {cta_url}\n\nSi necesitas ayuda final para decidir, responde este mensaje.",
            'cta_label' => 'Cerrar decision',
            'template_name' => 'conversation_final_close_push',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 1,
        ],
        'reactivation_last_call' => [
            'label' => 'Ultimo toque de reactivacion',
            'description' => 'Ultima ventana de regreso antes de enfriar definitivamente la secuencia.',
            'channel' => 'preferred',
            'automation_key' => 'conversation_reactivation_last_call',
            'requires_marketing_opt_in' => true,
            'subject' => 'Ultima oportunidad de retomar con {business_name}',
            'body' => "Hola {customer_name},\n\nEsta es la ultima oportunidad para retomar tu caso, compra o servicio con acompanamiento del equipo.\n\nPuedes volver aqui: {cta_url}\n\nSi deseas que lo dejemos listo contigo, responde este mensaje.",
            'cta_label' => 'Retomar ultima oportunidad',
            'template_name' => 'conversation_reactivation_last_call',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'daily_limit' => 1,
            'retry_limit' => 1,
        ],
    ],

    'segments' => [
        'vip' => [
            'journey' => [
                ['key' => 'vip_priority_touch', 'title' => 'Responder en la ventana premium', 'copy' => 'Tomar ownership y responder con prioridad real antes de 4 horas.', 'template_key' => 'vip_priority', 'lane' => 'premium', 'wait_hours' => 4],
                ['key' => 'vip_close_path', 'title' => 'Mover el siguiente paso', 'copy' => 'No dejes la conversacion abierta sin CTA claro de cita, compra o llamada.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 10],
                ['key' => 'vip_experience_close', 'title' => 'Cerrar con experiencia', 'copy' => 'Si se resuelve, deja beneficio, seguimiento o invitacion a siguiente compra.', 'template_key' => 'final_close_push', 'lane' => 'retention', 'wait_hours' => 24],
            ],
            'templates' => ['vip_priority', 'close_sale', 'fast_follow_up'],
        ],
        'repeat' => [
            'journey' => [
                ['key' => 'repeat_fast_follow_up', 'title' => 'Responder rapido', 'copy' => 'No dejes que una recompra se enfrie por falta de seguimiento humano.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                ['key' => 'repeat_bundle_offer', 'title' => 'Conectar bundle o reward', 'copy' => 'Usa fidelidad o continuidad como palanca del siguiente paso.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 18, 'branches' => [
                    ['key' => 'reward_ready', 'label' => 'Canje listo', 'title' => 'Conectar recompensa lista', 'copy' => 'Ya hay una recompensa disponible: usa ese momento para mover recompra o continuidad.', 'template_key' => 'close_sale', 'lane' => 'loyalty_follow_up', 'when' => 'reward_ready'],
                    ['key' => 'vip_priority', 'label' => 'Ruta VIP', 'title' => 'Mover continuidad premium', 'copy' => 'Usa tono premium, beneficio visible y prioridad real para no enfriar un cliente de alto valor.', 'template_key' => 'vip_priority', 'lane' => 'premium_follow_up', 'when' => 'vip_customer'],
                ]],
                ['key' => 'repeat_close_continuity', 'title' => 'Cerrar continuidad', 'copy' => 'Busca salida a recompra, paquete o nueva cita.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 30, 'branches' => [
                    ['key' => 'high_value_close', 'label' => 'Cierre alto valor', 'title' => 'Cerrar continuidad de alto valor', 'copy' => 'Enfatiza paquete, bundle o beneficio premium porque el cliente ya tiene valor claro.', 'template_key' => 'final_close_push', 'lane' => 'high_value_close', 'when' => 'high_value_customer'],
                ]],
            ],
            'templates' => ['close_sale', 'fast_follow_up', 'service_rebook'],
        ],
        'reactivation' => [
            'journey' => [
                ['key' => 'reactivation_reopen_interest', 'title' => 'Volver a abrir interes', 'copy' => 'Recupera contexto y baja friccion en el siguiente paso.', 'template_key' => 'reactivation_recovery', 'lane' => 'reactivation', 'wait_hours' => 0],
                ['key' => 'reactivation_reason_to_return', 'title' => 'Ofrecer motivo de regreso', 'copy' => 'Usa incentivo claro, control, bundle o regreso asistido.', 'template_key' => 'follow_up_reminder', 'lane' => 'reminder', 'wait_hours' => 24, 'branches' => [
                    ['key' => 'high_risk_reactivation', 'label' => 'Riesgo alto', 'title' => 'Mover regreso con urgencia sana', 'copy' => 'El cliente muestra riesgo alto de fuga: deja una salida clara y acompaniada.', 'template_key' => 'reactivation_last_call', 'lane' => 'urgent_reactivation', 'when' => ['high_reactivation_risk', 'stalled_sequence']],
                    ['key' => 'email_recovery', 'label' => 'Recuperacion por email', 'title' => 'Recuperar con recordatorio formal', 'copy' => 'Usa email como carril principal cuando el cliente responde mejor ahi.', 'template_key' => 'follow_up_reminder', 'lane' => 'email_reactivation', 'when' => 'email_preferred'],
                ]],
                ['key' => 'reactivation_close_week', 'title' => 'Cerrar en esta semana', 'copy' => 'No dejes la reactivacion sin fecha o CTA concreto.', 'template_key' => 'reactivation_last_call', 'lane' => 'last_call', 'wait_hours' => 48],
            ],
            'templates' => ['reactivation_recovery', 'fast_follow_up', 'close_sale'],
        ],
        'booked_service' => [
            'journey' => [
                ['key' => 'service_confirm_continuity', 'title' => 'Confirmar continuidad', 'copy' => 'Conecta la respuesta del cliente con la siguiente cita o control.', 'template_key' => 'service_rebook', 'lane' => 'continuity', 'wait_hours' => 0],
                ['key' => 'service_reduce_no_show', 'title' => 'Reducir no-show', 'copy' => 'Reagenda rapido y deja siguiente accion visible.', 'template_key' => 'follow_up_reminder', 'lane' => 'reminder', 'wait_hours' => 16],
                ['key' => 'service_open_next_package', 'title' => 'Abrir siguiente paquete', 'copy' => 'Si hay buena experiencia, propone continuidad o plan.', 'template_key' => 'vip_priority', 'lane' => 'expansion', 'wait_hours' => 32],
            ],
            'templates' => ['service_rebook', 'fast_follow_up', 'vip_priority'],
        ],
        'active' => [
            'journey' => [
                ['key' => 'active_keep_pace', 'title' => 'Mantener ritmo', 'copy' => 'Responde rapido y no dejes dudas abiertas en clientes activos.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                ['key' => 'active_detect_upsell', 'title' => 'Detectar upsell', 'copy' => 'Mira si hay oportunidad de bundle, upgrade o cita siguiente.', 'template_key' => 'close_sale', 'lane' => 'follow_up', 'wait_hours' => 18],
                ['key' => 'active_document_continuity', 'title' => 'Documentar continuidad', 'copy' => 'Deja owner, sede y proximo toque claros.', 'template_key' => 'final_close_push', 'lane' => 'closing', 'wait_hours' => 30],
            ],
            'templates' => ['fast_follow_up', 'close_sale', 'service_rebook'],
        ],
        'prospect' => [
            'journey' => [
                ['key' => 'prospect_first_touch', 'title' => 'Responder hoy', 'copy' => 'La primera velocidad importa mas que el texto perfecto.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                ['key' => 'prospect_clarify_offer', 'title' => 'Aclarar propuesta', 'copy' => 'Reduce friccion y deja CTA simple de cierre o discovery.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 12, 'branches' => [
                    ['key' => 'whatsapp_speed', 'label' => 'WhatsApp rapido', 'title' => 'Aclarar propuesta por WhatsApp', 'copy' => 'Empuja velocidad y baja friccion desde el canal donde este prospecto responde mejor.', 'template_key' => 'fast_follow_up', 'lane' => 'whatsapp_follow_up', 'when' => 'whatsapp_preferred'],
                    ['key' => 'warm_close', 'label' => 'Prospecto caliente', 'title' => 'Acercar cierre comercial', 'copy' => 'Ya hay varias respuestas: mueve discovery o cierre en vez de otro toque tibio.', 'template_key' => 'close_sale', 'lane' => 'warm_closing', 'when' => ['multiple_replies', 'closing_ready']],
                ]],
                ['key' => 'prospect_close_or_archive', 'title' => 'No perder el seguimiento', 'copy' => 'Si no responde, reprograma siguiente toque con owner claro.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 24],
            ],
            'templates' => ['close_sale', 'fast_follow_up', 'reactivation_recovery'],
        ],
        'new' => [
            'journey' => [
                ['key' => 'new_welcome', 'title' => 'Bienvenida clara', 'copy' => 'Muestra propuesta principal y siguiente paso.', 'template_key' => 'fast_follow_up', 'lane' => 'welcome', 'wait_hours' => 0],
                ['key' => 'new_guide_first_move', 'title' => 'Guiar primer movimiento', 'copy' => 'No dejes al cliente adivinando que hacer.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 12],
                ['key' => 'new_close_first_milestone', 'title' => 'Cerrar primer hito', 'copy' => 'Busca primera compra, cita o llamada cerrada.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 24],
            ],
            'templates' => ['fast_follow_up', 'close_sale', 'service_rebook'],
        ],
    ],

    'verticals' => [
        'dental_clinic' => [
            'templates' => [
                'service_rebook' => [
                    'label' => 'Confirmar valoracion dental',
                    'description' => 'Mover diagnostico, control o tratamiento sin dejar ansiedad en el paciente.',
                    'subject' => 'Podemos dejar tu valoracion lista en {business_name}',
                    'body' => "Hola {customer_name},\n\nPodemos ayudarte a dejar tu valoracion, control o tratamiento agendado sin complicarte el proceso.\n\nRevisa aqui el siguiente paso: {cta_url}\n\nSi prefieres, responde este mensaje y lo vemos contigo.",
                    'cta_label' => 'Confirmar valoracion',
                    'template_name' => 'dental_rebook',
                ],
                'vip_priority' => [
                    'label' => 'Atencion prioritaria dental',
                    'description' => 'Respuesta premium para tratamientos o pacientes de alto valor.',
                    'subject' => 'Seguimiento prioritario de tu caso dental',
                    'body' => "Hola {customer_name},\n\nEstamos dando seguimiento prioritario a tu caso para que avances con tranquilidad y claridad.\n\nPuedes retomarlo aqui: {cta_url}\n\nSi deseas apoyo directo, responde este mensaje y te acompanamos.",
                ],
                'follow_up_reminder' => [
                    'label' => 'Recordatorio de valoracion dental',
                    'description' => 'Segundo toque para no dejar caer un tratamiento o control importante.',
                    'subject' => 'Todavia podemos dejar tu valoracion lista',
                    'body' => "Hola {customer_name},\n\nTe dejamos un recordatorio rapido para no dejar tu valoracion o control sin fecha.\n\nPuedes retomarlo aqui: {cta_url}\n\nSi lo prefieres, responde este mensaje y te ayudamos a coordinar.",
                ],
                'final_close_push' => [
                    'label' => 'Cierre de tratamiento dental',
                    'description' => 'Ultimo empuje claro para cerrar tratamiento, control o diagnostico.',
                    'subject' => 'Podemos dejar tu siguiente paso dental confirmado hoy',
                    'body' => "Hola {customer_name},\n\nTodavia podemos dejar tu tratamiento o control confirmado hoy mismo.\n\nRetomalo aqui: {cta_url}\n\nSi necesitas apoyo final, responde este mensaje y te acompanamos.",
                ],
            ],
            'segments' => [
                'booked_service' => [
                    'journey' => [
                        ['key' => 'dental_confirm_visit', 'title' => 'Confirmar valoracion o control', 'copy' => 'Responde con tono clinico claro y deja la siguiente cita definida.', 'template_key' => 'service_rebook', 'lane' => 'continuity', 'wait_hours' => 0],
                        ['key' => 'dental_reduce_anxiety', 'title' => 'Reducir ansiedad del paciente', 'copy' => 'Aclara procedimiento, tiempo o siguiente paso para evitar abandono.', 'template_key' => 'follow_up_reminder', 'lane' => 'reminder', 'wait_hours' => 12],
                        ['key' => 'dental_close_continuity', 'title' => 'Cerrar continuidad', 'copy' => 'Busca que el paciente salga con control, tratamiento o recordatorio ya planteado.', 'template_key' => 'final_close_push', 'lane' => 'closing', 'wait_hours' => 24],
                    ],
                    'templates' => ['service_rebook', 'vip_priority', 'fast_follow_up'],
                ],
            ],
        ],
        'corporate_law_firm' => [
            'templates' => [
                'close_sale' => [
                    'label' => 'Cerrar discovery legal',
                    'description' => 'Convertir consulta inicial en reunion ejecutiva o siguiente accion formal.',
                    'subject' => 'Podemos avanzar con tu siguiente paso legal',
                    'body' => "Hola {customer_name},\n\nPodemos ordenar el siguiente paso para revisar tu caso o necesidad legal con el contexto correcto.\n\nRetomalo aqui: {cta_url}\n\nSi prefieres, responde este mensaje y coordinamos contigo.",
                    'cta_label' => 'Agendar discovery',
                    'template_name' => 'legal_close_discovery',
                ],
                'follow_up_reminder' => [
                    'label' => 'Recordatorio de discovery legal',
                    'description' => 'Segundo toque ejecutivo para no dejar abierta una oportunidad legal seria.',
                    'subject' => 'Seguimos atentos a tu siguiente paso legal',
                    'body' => "Hola {customer_name},\n\nSeguimos atentos para ayudarte a ordenar tu siguiente paso legal con el contexto correcto.\n\nPuedes retomarlo aqui: {cta_url}\n\nSi prefieres, responde este mensaje y coordinamos contigo.",
                ],
                'final_close_push' => [
                    'label' => 'Cierre ejecutivo del caso',
                    'description' => 'Ultimo toque para convertir discovery en reunion o encuadre formal.',
                    'subject' => 'Podemos dejar tu reunion ejecutiva confirmada',
                    'body' => "Hola {customer_name},\n\nTodavia podemos dejar tu siguiente reunion o definicion legal confirmada hoy.\n\nRetomalo aqui: {cta_url}\n\nSi deseas, responde este mensaje y te ayudamos a cerrarlo.",
                ],
            ],
            'segments' => [
                'prospect' => [
                    'journey' => [
                        ['key' => 'legal_first_criteria', 'title' => 'Responder con criterio', 'copy' => 'Aclara si conviene discovery, llamada breve o filtro inicial.', 'template_key' => 'close_sale', 'lane' => 'intake', 'wait_hours' => 0],
                        ['key' => 'legal_formal_next_step', 'title' => 'Definir siguiente accion formal', 'copy' => 'No dejes la conversacion abierta sin reunion o envio de contexto.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 18],
                        ['key' => 'legal_executive_close', 'title' => 'Empujar continuidad ejecutiva', 'copy' => 'Busca que el lead salga con fecha o responsable asignado.', 'template_key' => 'final_close_push', 'lane' => 'closing', 'wait_hours' => 36],
                    ],
                    'templates' => ['close_sale', 'fast_follow_up', 'reactivation_recovery'],
                ],
            ],
        ],
        'premium_barbershop' => [
            'templates' => [
                'service_rebook' => [
                    'label' => 'Reagendar slot premium',
                    'description' => 'Mover continuidad de grooming con tono premium y sin friccion.',
                    'subject' => 'Podemos dejar tu siguiente slot premium listo',
                    'body' => "Hola {customer_name},\n\nPodemos dejar tu siguiente corte o grooming premium ya reservado.\n\nRetomalo aqui: {cta_url}\n\nSi quieres, responde este mensaje y te ayudamos personalmente.",
                    'cta_label' => 'Reservar slot',
                    'template_name' => 'barber_rebook_premium',
                ],
                'follow_up_reminder' => [
                    'label' => 'Recordatorio de slot premium',
                    'description' => 'Segundo toque elegante para no dejar caer la siguiente visita.',
                    'subject' => 'Todavia podemos dejar tu siguiente slot listo',
                    'body' => "Hola {customer_name},\n\nTodavia podemos dejar tu siguiente slot premium reservado sin friccion.\n\nRetomalo aqui: {cta_url}\n\nSi prefieres, responde este mensaje y te ayudamos.",
                ],
            ],
            'segments' => [
                'repeat' => [
                    'journey' => [
                        ['key' => 'barber_keep_recurring', 'title' => 'No dejes caer la recurrencia', 'copy' => 'Empuja la siguiente reserva antes de que se enfrie la rutina.', 'template_key' => 'service_rebook', 'lane' => 'continuity', 'wait_hours' => 0],
                        ['key' => 'barber_membership_offer', 'title' => 'Usa membership o combo', 'copy' => 'Si aplica, mueve continuidad con paquete o beneficio visible.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 14],
                        ['key' => 'barber_close_next_visit', 'title' => 'Cierra la proxima visita', 'copy' => 'Busca que el cliente salga con nuevo slot o seguimiento claro.', 'template_key' => 'vip_priority', 'lane' => 'closing', 'wait_hours' => 28],
                    ],
                    'templates' => ['service_rebook', 'vip_priority', 'fast_follow_up'],
                ],
            ],
        ],
        'hardware_store' => [
            'templates' => [
                'fast_follow_up' => [
                    'label' => 'Seguimiento de cotizacion rapida',
                    'description' => 'Bajar friccion y mover cotizacion o consulta de materiales.',
                    'subject' => 'Tu cotizacion con {business_name} sigue abierta',
                    'body' => "Hola {customer_name},\n\nSeguimos pendientes de tu cotizacion o consulta para que avances sin perder tiempo.\n\nPuedes retomarla aqui: {cta_url}\n\nSi necesitas apoyo, responde este mensaje.",
                    'cta_label' => 'Retomar cotizacion',
                    'template_name' => 'hardware_fast_quote',
                ],
                'follow_up_reminder' => [
                    'label' => 'Recordatorio de cotizacion de materiales',
                    'description' => 'Segundo toque para rescatar una cotizacion o consulta urgente.',
                    'subject' => 'Seguimos atentos a tu cotizacion de materiales',
                    'body' => "Hola {customer_name},\n\nSeguimos atentos para ayudarte a cerrar tu cotizacion o pedido de materiales.\n\nRetomalo aqui: {cta_url}\n\nSi deseas apoyo, responde este mensaje.",
                ],
            ],
            'segments' => [
                'prospect' => [
                    'journey' => [
                        ['key' => 'hardware_speed_first', 'title' => 'Responder hoy', 'copy' => 'En ferreteria la velocidad pesa mucho cuando el cliente tiene una urgencia.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                        ['key' => 'hardware_translate_need', 'title' => 'Traducir necesidad a solucion', 'copy' => 'Aterriza la consulta en productos, cantidad o cobertura.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 8],
                        ['key' => 'hardware_close_quote', 'title' => 'Cerrar pedido o cotizacion', 'copy' => 'No dejes la conversacion sin salida concreta.', 'template_key' => 'final_close_push', 'lane' => 'closing', 'wait_hours' => 20],
                    ],
                    'templates' => ['fast_follow_up', 'close_sale', 'reactivation_recovery'],
                ],
            ],
        ],
        'premium_phone_store' => [
            'templates' => [
                'close_sale' => [
                    'label' => 'Cerrar flagship o bundle',
                    'description' => 'Empujar cierre de equipo premium con confianza y bundle.',
                    'subject' => 'Tu siguiente equipo ya puede quedar listo',
                    'body' => "Hola {customer_name},\n\nPodemos ayudarte a cerrar tu equipo o bundle con la mejor configuracion para ti.\n\nRetomalo aqui: {cta_url}\n\nSi prefieres, responde este mensaje y lo revisamos contigo.",
                    'cta_label' => 'Cerrar equipo',
                    'template_name' => 'phone_close_flagship',
                ],
                'final_close_push' => [
                    'label' => 'Cierre de flagship premium',
                    'description' => 'Ultimo toque con foco en bundle, respaldo y cierre de ticket alto.',
                    'subject' => 'Todavia podemos dejar tu equipo premium confirmado',
                    'body' => "Hola {customer_name},\n\nTodavia podemos dejar tu equipo premium, bundle o garantia cerrados hoy mismo.\n\nRetomalo aqui: {cta_url}\n\nSi quieres apoyo final, responde este mensaje y lo revisamos contigo.",
                ],
            ],
            'segments' => [
                'repeat' => [
                    'journey' => [
                        ['key' => 'phone_confident_reply', 'title' => 'Responder con confianza', 'copy' => 'Ticket alto necesita rapidez y respaldo, no solo precio.', 'template_key' => 'close_sale', 'lane' => 'intake', 'wait_hours' => 0],
                        ['key' => 'phone_bundle_offer', 'title' => 'Subir bundle o garantia', 'copy' => 'Usa accesorios, cobertura o soporte como parte del cierre.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 16],
                        ['key' => 'phone_clear_exit', 'title' => 'Dejar salida clara', 'copy' => 'Busca cotizacion cerrada, reserva o visita a tienda.', 'template_key' => 'final_close_push', 'lane' => 'closing', 'wait_hours' => 30],
                    ],
                    'templates' => ['close_sale', 'vip_priority', 'fast_follow_up'],
                ],
            ],
        ],
        'casual_restaurant' => [
            'templates' => [
                'close_sale' => [
                    'label' => 'Cerrar pedido de menu',
                    'description' => 'Mover pedido rapido con combos o extras como siguiente paso.',
                    'subject' => 'Tu pedido en {business_name} sigue listo para cerrar',
                    'body' => "Hola {customer_name},\n\nTu siguiente pedido sigue listo y podemos ayudarte a cerrarlo en pocos pasos.\n\nRetomalo aqui: {cta_url}\n\nSi quieres, responde este mensaje y te apoyamos.",
                    'cta_label' => 'Pedir ahora',
                    'template_name' => 'restaurant_close_order',
                ],
                'follow_up_reminder' => [
                    'label' => 'Recordatorio de pedido',
                    'description' => 'Segundo toque para cerrar un pedido o recompra del menu.',
                    'subject' => 'Tu pedido sigue listo para retomarse',
                    'body' => "Hola {customer_name},\n\nTu pedido sigue listo para retomarse con pocos pasos.\n\nPuedes volver aqui: {cta_url}\n\nSi lo prefieres, responde este mensaje y te ayudamos.",
                ],
            ],
            'segments' => [
                'repeat' => [
                    'journey' => [
                        ['key' => 'restaurant_fast_hunger', 'title' => 'Responder con hambre', 'copy' => 'No dejes que una recompra se enfrie por minutos.', 'template_key' => 'close_sale', 'lane' => 'intake', 'wait_hours' => 0],
                        ['key' => 'restaurant_combo_offer', 'title' => 'Usar combo o extra', 'copy' => 'Empuja ticket promedio desde la conversacion.', 'template_key' => 'follow_up_reminder', 'lane' => 'upsell', 'wait_hours' => 6],
                        ['key' => 'restaurant_close_today', 'title' => 'Cerrar pedido hoy', 'copy' => 'Busca compra inmediata o pedido programado.', 'template_key' => 'final_close_push', 'lane' => 'closing', 'wait_hours' => 12],
                    ],
                    'templates' => ['close_sale', 'fast_follow_up', 'reactivation_recovery'],
                ],
            ],
        ],
    ],
];
