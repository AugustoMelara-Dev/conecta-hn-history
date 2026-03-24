<?php

declare(strict_types=1);

return [
    'defaults' => [
        'manual_follow_up' => [
            'subject' => 'Seguimiento de {business_name}',
            'body' => "Hola {customer_name},\n\nTe escribimos desde {business_name} para ayudarte a retomar lo que estabas buscando.\n\nSi te queda bien, responde este correo o entra aqui: {cta_url}\n\nGracias por seguir en contacto.",
            'cta_label' => 'Ver negocio',
            'requires_marketing_opt_in' => false,
        ],
        'reactivation_email' => [
            'subject' => 'Todavia hay una buena oportunidad para volver a {business_name}',
            'body' => "Hola {customer_name},\n\nNotamos que hace tiempo no vuelves por {business_name}. Preparamos una forma mas facil de retomar contacto y seguir donde quedaste.\n\nPuedes volver aqui: {cta_url}\n\nSi prefieres, responde este correo y te ayudamos personalmente.",
            'cta_label' => 'Volver ahora',
            'requires_marketing_opt_in' => true,
        ],
    ],

    'profiles' => [
        'dental_clinic' => [
            'reactivation_email' => [
                'subject' => 'Tu siguiente control dental puede quedar listo hoy en {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar tu control, limpieza o valoracion pendiente sin complicarte el proceso.\n\nPuedes revisar disponibilidad aqui: {cta_url}\n\nSi necesitas orientacion antes de agendar, responde este correo y te acompaniamos.",
                'cta_label' => 'Agendar control',
            ],
        ],
        'corporate_law_firm' => [
            'reactivation_email' => [
                'subject' => 'Retoma tu seguimiento legal con {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos retomar la conversacion pendiente para que tu proceso avance con mas claridad.\n\nPuedes revisar el siguiente paso aqui: {cta_url}\n\nSi necesitas contexto adicional, responde este correo y lo preparamos contigo.",
                'cta_label' => 'Retomar seguimiento',
            ],
        ],
        'financial_consultancy' => [
            'reactivation_email' => [
                'subject' => 'Todavia podemos ayudarte a ordenar mejor tus finanzas en {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar el diagnostico o seguimiento financiero que quedo pendiente.\n\nPuedes volver aqui: {cta_url}\n\nSi prefieres, responde este correo y armamos el siguiente paso contigo.",
                'cta_label' => 'Retomar diagnostico',
            ],
        ],
        'premium_phone_store' => [
            'reactivation_email' => [
                'subject' => 'Tu siguiente upgrade o bundle te espera en {business_name}',
                'body' => "Hola {customer_name},\n\nEn {business_name} dejamos lista una forma mas simple de retomar tu interes en equipos, bundles o accesorios premium.\n\nPuedes volver aqui: {cta_url}\n\nSi quieres una recomendacion puntual, responde este correo y te ayudamos.",
                'cta_label' => 'Ver opciones',
            ],
        ],
        'hardware_store' => [
            'reactivation_email' => [
                'subject' => 'Retoma tu cotizacion o proyecto con {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar tu compra, cotizacion o proyecto pendiente.\n\nPuedes volver aqui: {cta_url}\n\nSi necesitas soporte rapido, responde este correo y te guiamos.",
                'cta_label' => 'Retomar proyecto',
            ],
        ],
        'premium_barbershop' => [
            'reactivation_email' => [
                'subject' => 'Tu siguiente cita premium puede quedar lista hoy en {business_name}',
                'body' => "Hola {customer_name},\n\nDesde {business_name} queremos ayudarte a retomar tu proxima cita, look o experiencia premium.\n\nPuedes revisar disponibilidad aqui: {cta_url}\n\nSi quieres ayuda para elegir servicio o horario, responde este correo.",
                'cta_label' => 'Reservar ahora',
            ],
        ],
        'spa_studio' => [
            'reactivation_email' => [
                'subject' => 'Vuelve a reservar tu siguiente experiencia en {business_name}',
                'body' => "Hola {customer_name},\n\nEn {business_name} queremos ayudarte a retomar tu siguiente experiencia, paquete o ritual de bienestar.\n\nPuedes volver aqui: {cta_url}\n\nSi necesitas una recomendacion, responde este correo y te acompanamos.",
                'cta_label' => 'Volver a reservar',
            ],
        ],
    ],
];
