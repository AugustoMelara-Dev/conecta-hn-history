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
            ],
            'segments' => [
                'booked_service' => [
                    'journey' => [
                        ['title' => 'Confirmar valoracion o control', 'copy' => 'Responde con tono clinico claro y deja la siguiente cita definida.'],
                        ['title' => 'Reducir ansiedad del paciente', 'copy' => 'Aclara procedimiento, tiempo o siguiente paso para evitar abandono.'],
                        ['title' => 'Cerrar continuidad', 'copy' => 'Busca que el paciente salga con control, tratamiento o recordatorio ya planteado.'],
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
            ],
            'segments' => [
                'prospect' => [
                    'journey' => [
                        ['title' => 'Responder con criterio', 'copy' => 'Aclara si conviene discovery, llamada breve o filtro inicial.'],
                        ['title' => 'Definir siguiente accion formal', 'copy' => 'No dejes la conversacion abierta sin reunion o envio de contexto.'],
                        ['title' => 'Empujar continuidad ejecutiva', 'copy' => 'Busca que el lead salga con fecha o responsable asignado.'],
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
            ],
            'segments' => [
                'repeat' => [
                    'journey' => [
                        ['title' => 'No dejes caer la recurrencia', 'copy' => 'Empuja la siguiente reserva antes de que se enfrie la rutina.'],
                        ['title' => 'Usa membership o combo', 'copy' => 'Si aplica, mueve continuidad con paquete o beneficio visible.'],
                        ['title' => 'Cierra la proxima visita', 'copy' => 'Busca que el cliente salga con nuevo slot o seguimiento claro.'],
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
            ],
            'segments' => [
                'prospect' => [
                    'journey' => [
                        ['title' => 'Responder hoy', 'copy' => 'En ferreteria la velocidad pesa mucho cuando el cliente tiene una urgencia.'],
                        ['title' => 'Traducir necesidad a solucion', 'copy' => 'Aterriza la consulta en productos, cantidad o cobertura.'],
                        ['title' => 'Cerrar pedido o cotizacion', 'copy' => 'No dejes la conversacion sin salida concreta.'],
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
            ],
            'segments' => [
                'repeat' => [
                    'journey' => [
                        ['title' => 'Responder con confianza', 'copy' => 'Ticket alto necesita rapidez y respaldo, no solo precio.'],
                        ['title' => 'Subir bundle o garantia', 'copy' => 'Usa accesorios, cobertura o soporte como parte del cierre.'],
                        ['title' => 'Dejar salida clara', 'copy' => 'Busca cotizacion cerrada, reserva o visita a tienda.'],
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
            ],
            'segments' => [
                'repeat' => [
                    'journey' => [
                        ['title' => 'Responder con hambre', 'copy' => 'No dejes que una recompra se enfrie por minutos.'],
                        ['title' => 'Usar combo o extra', 'copy' => 'Empuja ticket promedio desde la conversacion.'],
                        ['title' => 'Cerrar pedido hoy', 'copy' => 'Busca compra inmediata o pedido programado.'],
                    ],
                    'templates' => ['close_sale', 'fast_follow_up', 'reactivation_recovery'],
                ],
            ],
        ],
    ],
];
