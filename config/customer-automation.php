<?php

declare(strict_types=1);

return [
    'email_provider' => env('CUSTOMER_AUTOMATION_EMAIL_PROVIDER', 'mail'),
    'whatsapp_provider' => env('CUSTOMER_AUTOMATION_WHATSAPP_PROVIDER', 'whatsapp_cloud'),

    'opt_in_keywords' => [
        'accept' => ['ALTA', 'START', 'SI', 'ACEPTO', 'QUIERO'],
        'stop' => ['STOP', 'BAJA', 'SALIR', 'NO'],
    ],

    'defaults' => [
        'manual_follow_up' => [
            'subject' => 'Seguimiento de {business_name}',
            'body' => "Hola {customer_name},\n\nTe escribimos desde {business_name} para ayudarte a retomar lo que estabas buscando.\n\nSi te queda bien, responde este correo o entra aqui: {cta_url}\n\nGracias por seguir en contacto.",
            'cta_label' => 'Ver negocio',
            'requires_marketing_opt_in' => false,
        ],
        'manual_follow_up_whatsapp' => [
            'channel' => 'whatsapp',
            'body' => 'Hola {customer_name}, somos {business_name}. Quedamos atentos para ayudarte a retomar tu consulta. Si te sirve, vuelve aqui: {cta_url}',
            'template_name' => 'manual_follow_up',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'requires_marketing_opt_in' => false,
        ],
        'reactivation_email' => [
            'subject' => 'Todavia hay una buena oportunidad para volver a {business_name}',
            'body' => "Hola {customer_name},\n\nNotamos que hace tiempo no vuelves por {business_name}. Preparamos una forma mas facil de retomar contacto y seguir donde quedaste.\n\nPuedes volver aqui: {cta_url}\n\nSi prefieres, responde este correo y te ayudamos personalmente.",
            'cta_label' => 'Volver ahora',
            'requires_marketing_opt_in' => true,
        ],
        'reactivation_whatsapp' => [
            'channel' => 'whatsapp',
            'body' => 'Hola {customer_name}, te escribimos desde {business_name}. Vimos una buena oportunidad para retomar contigo. Si quieres volver, te esperamos aqui: {cta_url}',
            'template_name' => 'reactivation_touchpoint',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'requires_marketing_opt_in' => true,
        ],
        'purchase_follow_up_email' => [
            'subject' => 'Gracias por comprar en {business_name}',
            'body' => "Hola {customer_name},\n\nGracias por confiar en {business_name}. Queremos dejarte una via clara para soporte, recompra o seguimiento.\n\nPuedes volver aqui: {cta_url}\n\nSi necesitas algo mas, responde este correo.",
            'cta_label' => 'Volver al negocio',
            'requires_marketing_opt_in' => false,
        ],
        'purchase_follow_up_whatsapp' => [
            'channel' => 'whatsapp',
            'body' => 'Hola {customer_name}, gracias por comprar en {business_name}. Si quieres repetir, resolver una duda o pedir soporte, responde este mensaje o vuelve aqui: {cta_url}',
            'template_name' => 'purchase_follow_up',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'requires_marketing_opt_in' => false,
        ],
        'appointment_follow_up_email' => [
            'subject' => 'Tu cita con {business_name} ya quedo en seguimiento',
            'body' => "Hola {customer_name},\n\nYa dejamos lista la continuidad de tu cita con {business_name}. Si quieres confirmar detalles o agendar el siguiente paso, entra aqui: {cta_url}\n\nTambien puedes responder este correo.",
            'cta_label' => 'Ver agenda',
            'requires_marketing_opt_in' => false,
        ],
        'appointment_follow_up_whatsapp' => [
            'channel' => 'whatsapp',
            'body' => 'Hola {customer_name}, ya dejamos lista la continuidad de tu cita con {business_name}. Si quieres confirmar detalles o reservar el siguiente paso, estamos aqui: {cta_url}',
            'template_name' => 'appointment_follow_up',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'requires_marketing_opt_in' => false,
        ],
        'repeat_purchase_reward_email' => [
            'subject' => 'Ya eres cliente recurrente de {business_name}',
            'body' => "Hola {customer_name},\n\nYa notamos recompra en {business_name}. Es un buen momento para activarte un beneficio, bundle o seguimiento personalizado.\n\nPuedes volver aqui: {cta_url}\n\nSi prefieres, responde este correo y te ayudamos personalmente.",
            'cta_label' => 'Ver beneficio',
            'requires_marketing_opt_in' => true,
        ],
        'repeat_purchase_reward_whatsapp' => [
            'channel' => 'whatsapp',
            'body' => 'Hola {customer_name}, ya eres cliente recurrente de {business_name}. Tenemos una forma mas simple de darte continuidad o beneficio en tu siguiente compra: {cta_url}',
            'template_name' => 'repeat_purchase_reward',
            'template_language' => 'es_HN',
            'template_variables' => ['customer_name', 'business_name'],
            'requires_marketing_opt_in' => true,
        ],
    ],

    'profiles' => [
        'dental_clinic' => [
            'appointment_follow_up_whatsapp' => [
                'body' => 'Hola {customer_name}, en {business_name} dejamos listo el seguimiento de tu control o valoracion. Si quieres confirmar o reagendar, te ayudamos aqui: {cta_url}',
                'template_name' => 'dental_follow_up',
            ],
            'reactivation_email' => [
                'subject' => 'Tu siguiente control dental puede quedar listo hoy en {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar tu control, limpieza o valoracion pendiente sin complicarte el proceso.\n\nPuedes revisar disponibilidad aqui: {cta_url}\n\nSi necesitas orientacion antes de agendar, responde este correo y te acompaniamos.",
                'cta_label' => 'Agendar control',
            ],
            'reactivation_whatsapp' => [
                'body' => 'Hola {customer_name}, en {business_name} queremos ayudarte a retomar tu control, limpieza o valoracion pendiente. Puedes revisar disponibilidad aqui: {cta_url}',
                'template_name' => 'dental_reactivation',
            ],
        ],
        'corporate_law_firm' => [
            'reactivation_email' => [
                'subject' => 'Retoma tu seguimiento legal con {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos retomar la conversacion pendiente para que tu proceso avance con mas claridad.\n\nPuedes revisar el siguiente paso aqui: {cta_url}\n\nSi necesitas contexto adicional, responde este correo y lo preparamos contigo.",
                'cta_label' => 'Retomar seguimiento',
            ],
            'reactivation_whatsapp' => [
                'body' => 'Hola {customer_name}, desde {business_name} queremos retomar el seguimiento de tu caso. Si te sirve, continuamos por aqui: {cta_url}',
                'template_name' => 'legal_reactivation',
            ],
        ],
        'financial_consultancy' => [
            'reactivation_email' => [
                'subject' => 'Todavia podemos ayudarte a ordenar mejor tus finanzas en {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar el diagnostico o seguimiento financiero que quedo pendiente.\n\nPuedes volver aqui: {cta_url}\n\nSi prefieres, responde este correo y armamos el siguiente paso contigo.",
                'cta_label' => 'Retomar diagnostico',
            ],
            'repeat_purchase_reward_whatsapp' => [
                'body' => 'Hola {customer_name}, en {business_name} ya vemos una relacion mas constante contigo. Podemos proponerte el siguiente paso financiero aqui: {cta_url}',
                'template_name' => 'finance_repeat_reward',
            ],
        ],
        'premium_phone_store' => [
            'reactivation_email' => [
                'subject' => 'Tu siguiente upgrade o bundle te espera en {business_name}',
                'body' => "Hola {customer_name},\n\nEn {business_name} dejamos lista una forma mas simple de retomar tu interes en equipos, bundles o accesorios premium.\n\nPuedes volver aqui: {cta_url}\n\nSi quieres una recomendacion puntual, responde este correo y te ayudamos.",
                'cta_label' => 'Ver opciones',
            ],
            'repeat_purchase_reward_whatsapp' => [
                'body' => 'Hola {customer_name}, en {business_name} ya vemos recompra tuya. Podemos ayudarte con un siguiente upgrade, bundle o soporte aqui: {cta_url}',
                'template_name' => 'phone_store_repeat_reward',
            ],
        ],
        'hardware_store' => [
            'reactivation_email' => [
                'subject' => 'Retoma tu cotizacion o proyecto con {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar tu compra, cotizacion o proyecto pendiente.\n\nPuedes volver aqui: {cta_url}\n\nSi necesitas soporte rapido, responde este correo y te guiamos.",
                'cta_label' => 'Retomar proyecto',
            ],
            'purchase_follow_up_whatsapp' => [
                'body' => 'Hola {customer_name}, gracias por comprar en {business_name}. Si necesitas completar proyecto o cotizar otra pieza, estamos listos aqui: {cta_url}',
                'template_name' => 'hardware_purchase_follow_up',
            ],
        ],
        'premium_barbershop' => [
            'appointment_follow_up_whatsapp' => [
                'body' => 'Hola {customer_name}, en {business_name} dejamos lista la continuidad de tu cita premium. Si quieres mover fecha o reservar otra experiencia, entra aqui: {cta_url}',
                'template_name' => 'barber_follow_up',
            ],
            'reactivation_email' => [
                'subject' => 'Tu siguiente cita premium puede quedar lista hoy en {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar tu proxima cita, look o experiencia premium.\n\nPuedes revisar disponibilidad aqui: {cta_url}\n\nSi quieres ayuda para elegir servicio o horario, responde este correo.",
                'cta_label' => 'Reservar ahora',
            ],
        ],
        'spa_studio' => [
            'appointment_follow_up_whatsapp' => [
                'body' => 'Hola {customer_name}, en {business_name} ya dejamos lista tu continuidad de bienestar. Si quieres reagendar o tomar otro paquete, vuelve aqui: {cta_url}',
                'template_name' => 'spa_follow_up',
            ],
            'reactivation_email' => [
                'subject' => 'Vuelve a reservar tu siguiente experiencia en {business_name}',
                'body' => "Hola {customer_name},\n\nEn {business_name} queremos ayudarte a retomar tu siguiente experiencia, paquete o ritual de bienestar.\n\nPuedes volver aqui: {cta_url}\n\nSi necesitas una recomendacion, responde este correo y te acompanamos.",
                'cta_label' => 'Volver a reservar',
            ],
        ],
    ],
];
