<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;

return [
    'version' => 'rubro-manifest-v1',
    'default_rubro' => 'retail_general',

    'base_capabilities' => [
        Capability::CATALOG_BASIC->value,
        Capability::CHECKOUT_BASIC->value,
        Capability::CUSTOMER_DIRECTORY->value,
        Capability::PAYMENTS_BASIC->value,
        Capability::NOTIFICATIONS_BASIC->value,
        Capability::ANALYTICS_BASIC->value,
        Capability::COUPONS_BASIC->value,
        Capability::STAFF_BASIC->value,
        Capability::AI_CONTENT->value,
        Capability::WEB_PUSH->value,
    ],

    'business_type_defaults' => [
        BusinessType::RETAIL->value => 'retail_general',
        BusinessType::FOOD->value => 'restaurant_general',
        BusinessType::SERVICE->value => 'service_general',
        BusinessType::HYBRID->value => 'hybrid_general',
    ],

    // Runtime storefront resolution lives here so RubroManifest is the single source for public microsites.
    'resolution' => [
  'slug_overrides' =>
  [
    'bufete-lex-hn' => 'law_firm',
    'lex-corporativo-hn' => 'corporate_law_firm',
  ],
  'keyword_map' =>
  [
    'law_firm' =>
    [
      0 => 'abogado',
      1 => 'abogados',
      2 => 'legal',
      3 => 'bufete',
      4 => 'notaria',
      5 => 'jurid',
    ],
    'clinic' =>
    [
      0 => 'clinica',
      1 => 'odont',
      2 => 'dental',
      3 => 'medic',
      4 => 'salud',
      5 => 'fisioterapia',
      6 => 'laboratorio',
    ],
    'barbershop' =>
    [
      0 => 'barber',
      1 => 'barberia',
      2 => 'salon',
      3 => 'spa',
      4 => 'beauty',
      5 => 'estetica',
      6 => 'unas',
    ],
    'cellphone_store' =>
    [
      0 => 'celular',
      1 => 'celulares',
      2 => 'iphone',
      3 => 'android',
      4 => 'smartphone',
      5 => 'movil',
      6 => 'accesorio',
      7 => 'pantalla',
      8 => 'tech',
    ],
    'workshop' =>
    [
      0 => 'taller',
      1 => 'mecanica',
      2 => 'automotriz',
      3 => 'repuesto',
      4 => 'diagnostico',
      5 => 'servicio tecnico',
    ],
    'restaurant' =>
    [
      0 => 'restaurante',
      1 => 'cafe',
      2 => 'food',
      3 => 'pizza',
      4 => 'burger',
      5 => 'sushi',
      6 => 'comedor',
      7 => 'kitchen',
    ],
    'consultancy' =>
    [
      0 => 'consultoria',
      1 => 'asesoria',
      2 => 'consultor',
      3 => 'agencia',
      4 => 'coach',
      5 => 'marketing',
      6 => 'contable',
    ],
    'corporate_law_firm' =>
    [
      0 => 'corporativo',
      1 => 'corporativa',
      2 => 'compliance',
      3 => 'societario',
      4 => 'contratos empresariales',
    ],
    'dental_clinic' =>
    [
      0 => 'odont',
      1 => 'dental',
      2 => 'ortodoncia',
      3 => 'implante',
      4 => 'sonrisa',
      5 => 'endodoncia',
    ],
    'financial_consultancy' =>
    [
      0 => 'financiera',
      1 => 'financiero',
      2 => 'finanzas',
      3 => 'flujo de caja',
      4 => 'rentabilidad',
      5 => 'tesoreria',
      6 => 'cfo',
    ],
    'premium_barbershop' =>
    [
      0 => 'barber premium',
      1 => 'barberia premium',
      2 => 'grooming premium',
      3 => 'barber lounge',
      4 => 'gentlemen',
    ],
    'spa_studio' =>
    [
      0 => 'spa studio',
      1 => 'masaje',
      2 => 'facial',
      3 => 'relajacion',
      4 => 'wellness',
      5 => 'ritual de bienestar',
    ],
    'premium_phone_store' =>
    [
      0 => 'flagship',
      1 => 'gama alta',
      2 => 'premium phones',
      3 => 'magsafe',
      4 => 'trade in',
    ],
    'hardware_store' =>
    [
      0 => 'ferreteria',
      1 => 'herramienta',
      2 => 'construccion',
      3 => 'materiales',
      4 => 'tornillo',
      5 => 'pintura',
    ],
    'auto_parts_store' =>
    [
      0 => 'autopartes',
      1 => 'suspension',
      2 => 'bateria',
      3 => 'compatibilidad',
      4 => 'montaje',
    ],
    'coffee_shop' =>
    [
      0 => 'cafeteria',
      1 => 'cafe de especialidad',
      2 => 'espresso',
      3 => 'brew',
      4 => 'latte',
    ],
    'casual_restaurant' =>
    [
      0 => 'casual',
      1 => 'burger',
      2 => 'pizza',
      3 => 'street food',
      4 => 'lunch special',
      5 => 'almuerzo ejecutivo',
    ],
  ],
  'category_map' =>
  [
    'restaurantes' => 'restaurant',
    'tecnologia' => 'cellphone_store',
    'salud' => 'clinic',
    'belleza' => 'barbershop',
    'servicios' => 'consultancy',
    'automotriz' => 'workshop',
  ],
  'business_type_defaults' =>
  [
    'retail' => 'retail_general',
    'food' => 'restaurant',
    'service' => 'service_general',
    'hybrid' => 'hybrid_general',
  ],
],

    'storefront' => [
  'profiles' =>
  [
    'retail_general' =>
    [
      'vertical_label' => 'Tienda especializada',
      'hero_title' => 'Productos visibles, contacto directo y una vitrina lista para vender todos los dias.',
      'hero_copy' => 'Ideal para retail moderno que necesita catalogo, confianza y respuesta rapida desde el primer clic.',
      'offer_title' => 'Lo que hace fuerte a esta tienda',
      'offer_copy' => 'Catalogo, disponibilidad y contacto directo pensados para mover ventas sin friccion.',
      'catalog_title' => 'Catalogo disponible',
      'catalog_copy' => 'Explora productos visibles, precios y razones para comprar aqui.',
      'footer_copy' => 'Micrositio comercial conectado a Vito Business OS para vender mejor, responder mas rapido y sostener una presencia profesional.',
      'trust_points' =>
      [
        0 => 'Respuesta directa',
        1 => 'Catalogo actualizado',
        2 => 'Negocio local visible',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Vitrina preparada para vender',
          'subtitle' => 'Muestra lo mejor del negocio sin obligar al cliente a escribir primero.',
          'bullets' =>
          [
            0 => 'Productos visibles con precio',
            1 => 'CTA claros a WhatsApp o carrito',
            2 => 'Experiencia adaptable a movil',
          ],
          'cta_label' => 'Ver catalogo',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Confianza desde la primera visita',
          'subtitle' => 'Ubicacion, horarios y resenas ayudan a decidir mas rapido.',
          'bullets' =>
          [
            0 => 'Datos publicos del negocio',
            1 => 'Contacto directo',
            2 => 'Presentacion consistente',
          ],
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
    ],
    'restaurant' =>
    [
      'vertical_label' => 'Restaurante',
      'hero_title' => 'Menu apetitoso, pedidos rapidos y una presencia que abre hambre desde la primera pantalla.',
      'hero_copy' => 'Perfecto para restaurantes, cafeterias, dark kitchens y negocios que viven de combos, platos estrella y velocidad para atender.',
      'offer_title' => 'Como atiende este restaurante',
      'offer_copy' => 'Muestra secciones claras, platos visibles y canales rapidos para pedir o consultar.',
      'catalog_title' => 'Menu y platos disponibles',
      'catalog_copy' => 'Explora entradas, platos fuertes, bebidas y promociones del dia.',
      'primary_cta_label' => 'Pedir ahora',
      'secondary_cta_label' => 'Hablar por WhatsApp',
      'footer_copy' => 'Micrositio gastronomico conectado a Vito Business OS para mostrar menu, captar pedidos y crecer con mejor presencia digital.',
      'trust_points' =>
      [
        0 => 'Menu claro',
        1 => 'Pedido rapido',
        2 => 'Horario visible',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Combos, platos y bebidas',
          'subtitle' => 'Organiza el menu por secciones para que el cliente encuentre rapido lo que quiere.',
          'bullets' =>
          [
            0 => 'Entradas y platos estrella',
            1 => 'Bebidas y extras',
            2 => 'Bloques listos para promociones',
          ],
          'cta_label' => 'Ver menu',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Pedidos sin friccion',
          'subtitle' => 'Lleva la visita a una conversacion, un pedido o una recompra con menos pasos.',
          'bullets' =>
          [
            0 => 'CTA directo a WhatsApp',
            1 => 'Version movil rapida',
            2 => 'Experiencia lista para campanas',
          ],
          'cta_label' => 'Pedir por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'cellphone_store' =>
    [
      'vertical_label' => 'Tienda de celulares',
      'hero_title' => 'Equipos, accesorios y soporte tecnico presentados como una vitrina que si convierte.',
      'hero_copy' => 'Pensado para tiendas de celulares, electronica ligera y negocios que combinan venta, accesorios y atencion postventa.',
      'offer_title' => 'Lo que mas busca tu cliente',
      'offer_copy' => 'Equipos destacados, accesorios listos para upsell y una forma directa de cotizar o comprar.',
      'catalog_title' => 'Equipos y accesorios visibles',
      'catalog_copy' => 'Explora smartphones, accesorios, repuestos o paquetes activos en esta tienda.',
      'primary_cta_label' => 'Explorar equipos',
      'secondary_cta_label' => 'Cotizar por WhatsApp',
      'footer_copy' => 'Micrositio retail conectado a Vito Business OS para vender celulares, accesorios y soporte con una experiencia mas clara y confiable.',
      'trust_points' =>
      [
        0 => 'Equipos destacados',
        1 => 'Accesorios para upsell',
        2 => 'Soporte tecnico visible',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Equipos que mueven la vitrina',
          'subtitle' => 'Destaca smartphones, promos y modelos mas buscados para acelerar la decision.',
          'bullets' =>
          [
            0 => 'Catalogo de equipos visibles',
            1 => 'Precios listos para cotizar',
            2 => 'Imagen fuerte para campanas',
          ],
          'cta_label' => 'Ver catalogo',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Accesorios y soporte',
          'subtitle' => 'No solo vendas el equipo: empuja cargadores, protectores, repuestos y servicio.',
          'bullets' =>
          [
            0 => 'Upsell desde la misma vitrina',
            1 => 'Canal directo a consulta',
            2 => 'Ideal para negocio mixto',
          ],
          'cta_label' => 'Consultar disponibilidad',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'clinic' =>
    [
      'vertical_label' => 'Clinica o centro de salud',
      'hero_title' => 'Confianza, agenda y servicios claros para que el paciente sepa como avanzar.',
      'hero_copy' => 'Disenado para clinicas, consultorios, odontologia, fisioterapia y negocios de salud donde reservar y generar confianza es parte del producto.',
      'offer_title' => 'Como se presenta la atencion',
      'offer_copy' => 'Servicios visibles, orientacion clara y canales de reserva o consulta desde la primera visita.',
      'catalog_title' => 'Servicios y programas disponibles',
      'catalog_copy' => 'Aqui puedes mostrar consultas, paquetes, controles y complementos visibles.',
      'primary_cta_label' => 'Reservar evaluacion',
      'secondary_cta_label' => 'Hablar con recepcion',
      'footer_copy' => 'Micrositio profesional conectado a Vito Business OS para mostrar atencion clinica, captar consultas y ordenar mejor la agenda.',
      'trust_points' =>
      [
        0 => 'Agenda visible',
        1 => 'Servicios claros',
        2 => 'Ubicacion y horarios',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Especialidades y consultas',
          'subtitle' => 'Haz visible que atiendes, cuanto dura y como reservar.',
          'bullets' =>
          [
            0 => 'Consultas y paquetes',
            1 => 'Precios o rangos visibles',
            2 => 'Reservas desde movil',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Confianza operativa',
          'subtitle' => 'Ubicacion, horarios y contacto ayudan a reducir dudas antes de escribir.',
          'bullets' =>
          [
            0 => 'Presentacion profesional',
            1 => 'Recepcion por WhatsApp',
            2 => 'Informacion institucional clara',
          ],
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
    ],
    'barbershop' =>
    [
      'vertical_label' => 'Barberia o salon',
      'hero_title' => 'Cortes, grooming y agenda visibles para llenar mas horas con mejor presencia.',
      'hero_copy' => 'Pensado para barberias, salones, unas, spa y negocios de belleza donde el cliente decide rapido si le das claridad y estilo.',
      'offer_title' => 'Como se vende la experiencia',
      'offer_copy' => 'Servicios visibles, equipo disponible y una forma rapida de reservar o consultar promociones.',
      'catalog_title' => 'Servicios, productos y extras',
      'catalog_copy' => 'Si vendes tratamientos, grooming kits o combos, aqui pueden convivir con tu agenda.',
      'primary_cta_label' => 'Reservar cita',
      'secondary_cta_label' => 'Escribir por WhatsApp',
      'footer_copy' => 'Micrositio de belleza conectado a Vito Business OS para mostrar servicios, llenar agenda y destacar promociones sin perder estilo.',
      'trust_points' =>
      [
        0 => 'Agenda rapida',
        1 => 'Servicios visibles',
        2 => 'Promos semanales',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Cortes y grooming',
          'subtitle' => 'Haz facil elegir entre corte, barba, color, unas o spa.',
          'bullets' =>
          [
            0 => 'Servicios por duracion',
            1 => 'Reservas o consultas',
            2 => 'Experiencia lista para movil',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Equipo y disponibilidad',
          'subtitle' => 'Muestra que hay agenda y personas listas para atender.',
          'bullets' =>
          [
            0 => 'Barberos o especialistas visibles',
            1 => 'Promociones mas claras',
            2 => 'Confianza desde la primera visita',
          ],
          'cta_label' => 'Reservar',
          'cta_target' => '#servicios',
        ],
      ],
    ],
    'law_firm' =>
    [
      'vertical_label' => 'Firma legal',
      'hero_title' => 'Consulta legal con claridad, confianza y una primera conversacion mejor guiada.',
      'hero_copy' => 'Creado para despachos, abogados independientes y firmas que necesitan convertir visitas en consultas serias sin verse improvisados.',
      'offer_title' => 'Como aterriza la propuesta legal',
      'offer_copy' => 'Areas de practica, canal de consulta y una presencia mas confiable para clientes personales o corporativos.',
      'catalog_title' => 'Servicios y acompanamiento legal',
      'catalog_copy' => 'Si tambien vendes paquetes, asesorias o servicios recurrentes, apareceran aqui.',
      'primary_cta_label' => 'Agendar consulta',
      'secondary_cta_label' => 'Hablar con el despacho',
      'footer_copy' => 'Micrositio profesional conectado a Vito Business OS para presentar servicios legales, captar consultas y ordenar mejor el primer contacto.',
      'trust_points' =>
      [
        0 => 'Areas de practica',
        1 => 'Consulta inicial clara',
        2 => 'Atencion corporativa o personal',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Areas de practica visibles',
          'subtitle' => 'El cliente entiende rapido si lo puedes ayudar y como iniciar.',
          'bullets' =>
          [
            0 => 'Consultas y asesorias',
            1 => 'Casos personales o empresariales',
            2 => 'CTA directo a cita o contacto',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Primera conversacion mejor filtrada',
          'subtitle' => 'Ahorra tiempo mostrando alcance, horarios y canal de contacto desde el micrositio.',
          'bullets' =>
          [
            0 => 'Menos consultas frias',
            1 => 'Mas claridad antes de reservar',
            2 => 'Presencia seria y consistente',
          ],
          'cta_label' => 'Consultar',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'consultancy' =>
    [
      'vertical_label' => 'Consultoria',
      'hero_title' => 'Diagnostico, propuesta y acompanamiento visibles para convertir mas reuniones utiles.',
      'hero_copy' => 'Ideal para consultores, agencias, coaches y servicios profesionales que venden claridad, experiencia y seguimiento.',
      'offer_title' => 'Como vende esta consultoria',
      'offer_copy' => 'Servicios visibles, propuesta concreta y una forma ordenada de pedir una llamada o diagnostico.',
      'catalog_title' => 'Servicios, paquetes y recursos',
      'catalog_copy' => 'Si vendes recursos, plantillas o productos digitales, aqui tambien pueden convivir.',
      'primary_cta_label' => 'Agendar llamada',
      'secondary_cta_label' => 'Solicitar informacion',
      'footer_copy' => 'Micrositio consultivo conectado a Vito Business OS para presentar servicios, captar leads y estructurar mejor el funnel comercial.',
      'trust_points' =>
      [
        0 => 'Servicios claros',
        1 => 'Discovery call visible',
        2 => 'Propuesta mas creible',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Servicios con foco',
          'subtitle' => 'Resume que haces, para quien y como empiezan a trabajar contigo.',
          'bullets' =>
          [
            0 => 'Paquetes y sesiones',
            1 => 'Llamadas de diagnostico',
            2 => 'CTA pensados para leads reales',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Embudo mas ordenado',
          'subtitle' => 'Haz que la visita termine en una consulta, no en una duda.',
          'bullets' =>
          [
            0 => 'Contacto directo',
            1 => 'Presentacion profesional',
            2 => 'Mejor base para campanas',
          ],
          'cta_label' => 'Solicitar informacion',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'workshop' =>
    [
      'vertical_label' => 'Taller o servicio tecnico',
      'hero_title' => 'Diagnostico, repuestos y atencion tecnica organizados en una sola vitrina digital.',
      'hero_copy' => 'Pensado para talleres, servicio tecnico, mecanica ligera y negocios que mezclan trabajo tecnico con venta de repuestos o accesorios.',
      'offer_title' => 'Como se vende la atencion tecnica',
      'offer_copy' => 'Productos, servicios y consulta rapida alineados para resolver mejor desde el primer contacto.',
      'catalog_title' => 'Repuestos, servicios y soluciones',
      'catalog_copy' => 'Explora servicios tecnicos, repuestos visibles y soluciones activas para este negocio.',
      'primary_cta_label' => 'Consultar diagnostico',
      'secondary_cta_label' => 'Ver catalogo tecnico',
      'footer_copy' => 'Micrositio hibrido conectado a Vito Business OS para mostrar servicios tecnicos, repuestos y canales de consulta sin fragmentar la experiencia.',
      'trust_points' =>
      [
        0 => 'Diagnostico claro',
        1 => 'Servicios tecnicos visibles',
        2 => 'Repuestos y extras',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Servicios tecnicos y repuestos',
          'subtitle' => 'Muestra que reparas, que vendes y como iniciar el proceso.',
          'bullets' =>
          [
            0 => 'Diagnostico y mano de obra',
            1 => 'Repuestos o accesorios',
            2 => 'Experiencia hibrida lista para convertir',
          ],
          'cta_label' => 'Explorar negocio',
          'cta_target' => '#ofertas',
        ],
        1 =>
        [
          'title' => 'Consulta rapida antes de visitar',
          'subtitle' => 'Ideal para filtrar casos y acelerar atencion desde WhatsApp o reserva.',
          'bullets' =>
          [
            0 => 'Menos friccion para el cliente',
            1 => 'Mas claridad operativa',
            2 => 'Mejor soporte comercial',
          ],
          'cta_label' => 'Consultar por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'service_general' =>
    [
      'vertical_label' => 'Negocio de servicios',
      'trust_points' =>
      [
        0 => 'Servicios visibles',
        1 => 'Agenda o consulta',
        2 => 'Presencia profesional',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Servicios con mejor contexto',
          'subtitle' => 'Haz visible lo que resuelves y como empezar.',
          'bullets' =>
          [
            0 => 'Servicios y duracion',
            1 => 'Canal directo a cita o consulta',
            2 => 'Presentacion profesional',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
      ],
    ],
    'hybrid_general' =>
    [
      'vertical_label' => 'Negocio hibrido',
      'trust_points' =>
      [
        0 => 'Productos y servicios',
        1 => 'Atencion coordinada',
        2 => 'Experiencia unificada',
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Oferta mixta mas clara',
          'subtitle' => 'Combina productos, soporte y agenda sin obligar al cliente a cambiar de canal.',
          'bullets' =>
          [
            0 => 'Frente comercial unico',
            1 => 'CTA mas claros',
            2 => 'Base lista para crecer',
          ],
          'cta_label' => 'Ver ofertas',
          'cta_target' => '#ofertas',
        ],
      ],
    ],
    'casual_restaurant' =>
    [
      'vertical_label' => 'Restaurante casual',
      'hero_title' => 'Combos, platos estrella y una experiencia que convierte hambre en pedido sin friccion.',
      'hero_copy' => 'Ideal para bistros, burgers, comida casual y marcas que viven del ticket promedio, las promociones y la recompra semanal.',
      'offer_title' => 'Operacion comercial del local',
      'offer_copy' => 'La vitrina prioriza platos gancho, combos, bebidas y CTA claros para pedir o reservar mesa.',
      'catalog_title' => 'Menu casual listo para vender',
      'catalog_copy' => 'Explora platos estrella, promos del dia, add-ons y bebidas con una narrativa pensada para mover ticket.',
      'primary_cta_label' => 'Pedir combo',
      'secondary_cta_label' => 'Reservar mesa',
      'footer_copy' => 'Micrositio gastronomico conectado a Vito Business OS para empujar ticket, pedidos y recompra en restaurantes casuales.',
      'trust_points' =>
      [
        0 => 'Combos visibles',
        1 => 'Pedido rapido',
        2 => 'Promos rotativas',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Platos que abren hambre',
          'copy' => 'Presenta hamburguesas, pizzas, bowls o almuerzos ejecutivos como productos que se venden solos.',
          'cta_label' => 'Ver menu',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Combos y upsells',
          'copy' => 'Empuja bebidas, extras y postres para subir ticket sin meter ruido en el pedido.',
          'cta_label' => 'Explorar combos',
          'cta_target' => '#catalogo',
        ],
        2 =>
        [
          'title' => 'Reserva o pedido directo',
          'copy' => 'Convierte trafico frio en una accion concreta desde WhatsApp o reserva.',
          'cta_label' => 'Pedir ahora',
          'cta_target' => 'whatsapp',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Menu pensado para rotacion',
          'subtitle' => 'Platos estrella, almuerzos, hamburguesas y promos aparecen donde mas convierten.',
          'bullets' =>
          [
            0 => 'Bloques para ticket promedio',
            1 => 'Secciones faciles de escanear',
            2 => 'Fotos que trabajan para vender',
          ],
          'cta_label' => 'Ver menu',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Promociones que se sienten vivas',
          'subtitle' => 'La portada deja espacio para combos de temporada, happy hour o platos del dia.',
          'bullets' =>
          [
            0 => 'Narrativa para recompra',
            1 => 'Espacio para specials',
            2 => 'Ideal para pauta y redes',
          ],
          'cta_label' => 'Pedir por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'coffee_shop' =>
    [
      'vertical_label' => 'Cafeteria',
      'hero_title' => 'Cafe, brunch y productos de impulso presentados como una marca a la que quieres volver.',
      'hero_copy' => 'Pensado para cafeterias, coffee shops y conceptos que mezclan bebida, experiencia de marca, brunch y pickup rapido.',
      'offer_title' => 'Experiencia de cafeteria',
      'offer_copy' => 'La vitrina combina carta, ambiente, especiales y CTA rapidos para pickup o mensaje directo.',
      'catalog_title' => 'Carta de cafe y brunch',
      'catalog_copy' => 'Explora bebidas, panaderia, brunch y productos de temporada con una experiencia visual mas cuidada.',
      'primary_cta_label' => 'Ver carta',
      'secondary_cta_label' => 'Pedir para llevar',
      'footer_copy' => 'Micrositio conectado a Vito Business OS para cafeterias que quieren verse premium, captar pedidos y sostener comunidad.',
      'trust_points' =>
      [
        0 => 'Carta clara',
        1 => 'Pickup rapido',
        2 => 'Marca con personalidad',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Bebidas firma',
          'copy' => 'Destaca cafes especiales, cold brew, matcha o bebidas de temporada desde la primera pantalla.',
          'cta_label' => 'Ver carta',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Brunch y panaderia',
          'copy' => 'Mezcla comida ligera con productos de impulso para aumentar recurrencia y ticket.',
          'cta_label' => 'Explorar brunch',
          'cta_target' => '#catalogo',
        ],
        2 =>
        [
          'title' => 'Ordena sin filas',
          'copy' => 'Activa pedidos para llevar y conversaciones rapidas en horarios pico.',
          'cta_label' => 'Pedir para llevar',
          'cta_target' => 'whatsapp',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Marca que invita a quedarse',
          'subtitle' => 'No solo vende cafe: transmite ambiente, consistencia y una rutina que el cliente quiere repetir.',
          'bullets' =>
          [
            0 => 'Bloques para especialidades',
            1 => 'Storytelling de temporada',
            2 => 'Ideal para redes y comunidad',
          ],
          'cta_label' => 'Explorar carta',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Pickup y pedidos ligeros',
          'subtitle' => 'Convierte el trafico del mediodia en pedidos rapidos sin sacrificar la experiencia.',
          'bullets' =>
          [
            0 => 'CTA a WhatsApp',
            1 => 'Brunch mas facil de vender',
            2 => 'Perfecto para horas pico',
          ],
          'cta_label' => 'Pedir ahora',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'premium_phone_store' =>
    [
      'vertical_label' => 'Tienda de celulares premium',
      'hero_title' => 'Flagships, accesorios y soporte presentados como una vitrina de alto valor, no como una lista generica.',
      'hero_copy' => 'Ideal para tiendas de celulares premium que quieren vender equipos de gama alta, bundles y confianza postventa.',
      'offer_title' => 'Venta retail de alto ticket',
      'offer_copy' => 'La vitrina prioriza equipos flagship, bundles de accesorios, garantia y soporte para una conversion mas creible.',
      'catalog_title' => 'Equipos premium y bundles',
      'catalog_copy' => 'Explora equipos de gama alta, accesorios premium, kits de proteccion y propuestas de upgrade.',
      'primary_cta_label' => 'Explorar flagships',
      'secondary_cta_label' => 'Cotizar por WhatsApp',
      'footer_copy' => 'Micrositio retail conectado a Vito Business OS para tiendas de celulares premium que quieren vender con mas confianza y margen.',
      'trust_points' =>
      [
        0 => 'Equipos premium',
        1 => 'Bundles listos',
        2 => 'Soporte visible',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Flagships que venden solos',
          'copy' => 'Destaca iPhone, Galaxy y equipos de gama alta con mejor narrativa comercial.',
          'cta_label' => 'Ver catalogo',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Bundles con margen',
          'copy' => 'Cases, cargadores, vidrio y accesorios premium aparecen como parte natural de la compra.',
          'cta_label' => 'Ver bundles',
          'cta_target' => '#catalogo',
        ],
        2 =>
        [
          'title' => 'Cotizacion con confianza',
          'copy' => 'La presencia publica hace que el cliente llegue mejor preparado y con mas intencion de compra.',
          'cta_label' => 'Cotizar ahora',
          'cta_target' => 'whatsapp',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Retail de alta confianza',
          'subtitle' => 'El sitio deja claro que vendes equipos reales, accesorios premium y una experiencia cuidada.',
          'bullets' =>
          [
            0 => 'Hero para equipos estrella',
            1 => 'Bundles de accesorios',
            2 => 'Mensaje de garantia y soporte',
          ],
          'cta_label' => 'Ver equipos',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Posventa mas visible',
          'subtitle' => 'La tienda no se queda solo en vender el equipo: tambien proyecta respaldo.',
          'bullets' =>
          [
            0 => 'Canal directo a soporte',
            1 => 'Mas credibilidad',
            2 => 'Base para trade-in o upgrade',
          ],
          'cta_label' => 'Hablar por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'hardware_store' =>
    [
      'vertical_label' => 'Ferreteria',
      'hero_title' => 'Materiales, herramientas y cotizacion rapida para proyectos pequenos, medianos y urgentes.',
      'hero_copy' => 'Pensado para ferreterias y negocios de construccion que necesitan mostrar disponibilidad, orden y respuesta comercial rapida.',
      'offer_title' => 'Operacion de mostrador y proyecto',
      'offer_copy' => 'La vitrina mezcla catalogo visible, consulta directa y bloques orientados a obra, reparacion y mantenimiento.',
      'catalog_title' => 'Herramientas y materiales visibles',
      'catalog_copy' => 'Explora herramientas, pinturas, accesorios y materiales con una experiencia mas facil de cotizar.',
      'primary_cta_label' => 'Cotizar materiales',
      'secondary_cta_label' => 'Ver catalogo',
      'footer_copy' => 'Micrositio comercial conectado a Vito Business OS para ferreterias que quieren vender mas por WhatsApp y moverse mejor por proyecto.',
      'trust_points' =>
      [
        0 => 'Cotizacion rapida',
        1 => 'Materiales visibles',
        2 => 'Atencion por proyecto',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Herramientas listas para descubrir',
          'copy' => 'Muestra taladros, pintura, tornilleria y materiales con menos dependencia del mostrador.',
          'cta_label' => 'Ver productos',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Cotiza por obra o reparacion',
          'copy' => 'Lleva al cliente directo a una conversacion util para cerrar pedidos medianos o grandes.',
          'cta_label' => 'Cotizar por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
        2 =>
        [
          'title' => 'Presencia util para barrio y proyecto',
          'copy' => 'La tienda se vuelve referencia local para mantenimiento, construccion y urgencias.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Catalogo para necesidades reales',
          'subtitle' => 'La vitrina esta pensada para quien busca resolver hoy, no para navegar sin rumbo.',
          'bullets' =>
          [
            0 => 'Productos por problema o proyecto',
            1 => 'Espacio para combos utiles',
            2 => 'Ideal para ventas por WhatsApp',
          ],
          'cta_label' => 'Explorar productos',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Canal directo a cotizacion',
          'subtitle' => 'El cliente puede avanzar rapido cuando necesita precio, disponibilidad o entrega.',
          'bullets' =>
          [
            0 => 'Menos friccion comercial',
            1 => 'Mas conversion desde movil',
            2 => 'Mejor soporte para pedidos recurrentes',
          ],
          'cta_label' => 'Cotizar ahora',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
    'dental_clinic' =>
    [
      'vertical_label' => 'Clinica dental',
      'hero_title' => 'Valoraciones, tratamientos y confianza presentados con la seriedad que una clinica dental necesita.',
      'hero_copy' => 'Ideal para odontologia, ortodoncia, implantologia y clinicas dentales que quieren orientar mejor al paciente y llenar agenda con menos friccion.',
      'offer_title' => 'Experiencia del paciente',
      'offer_copy' => 'La vitrina presenta tratamientos, valoraciones y canales de agenda para bajar ansiedad y subir confianza.',
      'catalog_title' => 'Tratamientos y valoraciones',
      'catalog_copy' => 'Explora limpiezas, ortodoncia, valoraciones y procedimientos con una experiencia mas clara para el paciente.',
      'primary_cta_label' => 'Agendar valoracion',
      'secondary_cta_label' => 'Hablar con recepcion',
      'footer_copy' => 'Micrositio clinico conectado a Vito Business OS para clinicas dentales que quieren orientar mejor, agendar mejor y verse mas confiables.',
      'trust_points' =>
      [
        0 => 'Valoracion inicial',
        1 => 'Agenda clara',
        2 => 'Atencion profesional',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Tratamientos faciles de entender',
          'copy' => 'Presenta ortodoncia, limpieza, implantes y procedimientos sin depender de llamadas largas.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Agenda evaluaciones',
          'copy' => 'La primera visita ya llega mejor orientada hacia una valoracion o consulta.',
          'cta_label' => 'Agendar valoracion',
          'cta_target' => 'booking',
        ],
        2 =>
        [
          'title' => 'Confianza desde la portada',
          'copy' => 'Horarios, ubicacion y enfoque clinico ayudan a reducir la ansiedad del paciente.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Explica el siguiente paso correcto',
          'subtitle' => 'El paciente entiende si debe reservar limpieza, valoracion o una consulta de seguimiento.',
          'bullets' =>
          [
            0 => 'Menos friccion para recepcion',
            1 => 'Mas claridad en tratamientos',
            2 => 'Ideal para agenda movil',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Recepcion mejor preparada',
          'subtitle' => 'Las consultas llegan con mejor contexto y menos preguntas basicas.',
          'bullets' =>
          [
            0 => 'Canal directo a recepcion',
            1 => 'Mas orden en la demanda',
            2 => 'Presencia institucional mas fuerte',
          ],
          'cta_label' => 'Agendar valoracion',
          'cta_target' => 'booking',
        ],
      ],
    ],
    'corporate_law_firm' =>
    [
      'vertical_label' => 'Bufete corporativo',
      'hero_title' => 'Servicios legales para empresa presentados con claridad, rigor y una narrativa que transmite control.',
      'hero_copy' => 'Pensado para bufetes corporativos, firmas mercantiles y equipos legales que venden confianza, velocidad de respuesta y criterio de negocio.',
      'offer_title' => 'Operacion legal B2B',
      'offer_copy' => 'La vitrina prioriza areas de practica corporativa, discovery call y un intake mas serio para clientes empresa.',
      'catalog_title' => 'Servicios legales corporativos',
      'catalog_copy' => 'Explora servicios mercantiles, contractuales y de compliance con una narrativa mas orientada a empresa.',
      'primary_cta_label' => 'Agendar reunion',
      'secondary_cta_label' => 'Hablar con el equipo legal',
      'footer_copy' => 'Micrositio profesional conectado a Vito Business OS para bufetes corporativos que necesitan seriedad, claridad y un mejor primer filtro.',
      'trust_points' =>
      [
        0 => 'Enfoque empresa',
        1 => 'Intake serio',
        2 => 'Servicios corporativos',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Areas de practica B2B',
          'copy' => 'Contratos, compliance, societario o laboral empresarial quedan visibles sin sonar genericos.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Intake mas filtrado',
          'copy' => 'La reunion inicial llega con mejor contexto y una expectativa comercial mas clara.',
          'cta_label' => 'Agendar reunion',
          'cta_target' => 'booking',
        ],
        2 =>
        [
          'title' => 'Presencia de despacho serio',
          'copy' => 'El sitio transmite control, criterio y capacidad de respuesta para clientes empresa.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Servicios legales que hablan de negocio',
          'subtitle' => 'La firma deja de verse como un listado abstracto y empieza a vender criterio empresarial.',
          'bullets' =>
          [
            0 => 'Compliance y contratos',
            1 => 'Narrativa para clientes empresa',
            2 => 'Discovery call mejor enfocada',
          ],
          'cta_label' => 'Explorar servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Primera reunion mas util',
          'subtitle' => 'El equipo legal recibe menos ruido y conversaciones mejor orientadas.',
          'bullets' =>
          [
            0 => 'Filtro inicial mas serio',
            1 => 'Canal B2B mas claro',
            2 => 'Mejor base para seguimiento',
          ],
          'cta_label' => 'Agendar reunion',
          'cta_target' => 'booking',
        ],
      ],
    ],
    'financial_consultancy' =>
    [
      'vertical_label' => 'Consultoria financiera',
      'hero_title' => 'Flujo de caja, rentabilidad y decisiones financieras presentadas como una propuesta concreta para crecer.',
      'hero_copy' => 'Ideal para consultorias financieras, fractional CFO, firmas de analisis o equipos que ayudan a PYMEs a ordenar sus numeros.',
      'offer_title' => 'Consultoria con impacto financiero',
      'offer_copy' => 'La vitrina pone en primer plano diagnostico, seguimiento y resultados tangibles para negocio.',
      'catalog_title' => 'Servicios financieros y acompanamiento',
      'catalog_copy' => 'Explora diagnosticos, tableros, flujo de caja y sesiones de acompanamiento con una narrativa mas vendible.',
      'primary_cta_label' => 'Agendar diagnostico',
      'secondary_cta_label' => 'Solicitar informacion',
      'footer_copy' => 'Micrositio consultivo conectado a Vito Business OS para firmas financieras que necesitan vender claridad, control y seguimiento.',
      'trust_points' =>
      [
        0 => 'Flujo de caja',
        1 => 'Diagnostico inicial',
        2 => 'Seguimiento ejecutivo',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Problemas financieros bien aterrizados',
          'copy' => 'Habla de caja, costos, margen o proyecciones como temas que el cliente entiende rapido.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Discovery con contexto',
          'copy' => 'La llamada inicial deja de ser una charla abierta y se vuelve un punto de diagnostico.',
          'cta_label' => 'Agendar diagnostico',
          'cta_target' => 'booking',
        ],
        2 =>
        [
          'title' => 'Seguimiento comercial mas serio',
          'copy' => 'WhatsApp, agenda y contenido trabajan juntos para sostener el funnel.',
          'cta_label' => 'Solicitar informacion',
          'cta_target' => 'whatsapp',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Convierte complejidad en claridad',
          'subtitle' => 'La consultoria financiera vende mejor cuando aterriza decisiones y no solo conceptos.',
          'bullets' =>
          [
            0 => 'Servicios por problema financiero',
            1 => 'Discovery orientada a valor',
            2 => 'Narrativa para PYMEs en crecimiento',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Embudo de seguimiento mas ordenado',
          'subtitle' => 'Cada visita puede terminar en llamada, documento compartido o siguiente paso comercial.',
          'bullets' =>
          [
            0 => 'Lead mejor filtrado',
            1 => 'Mas seriedad en el mensaje',
            2 => 'Mejor continuidad comercial',
          ],
          'cta_label' => 'Agendar diagnostico',
          'cta_target' => 'booking',
        ],
      ],
    ],
    'premium_barbershop' =>
    [
      'vertical_label' => 'Barber premium',
      'hero_title' => 'Cortes, grooming y experiencias premium presentados con una marca que se siente aspiracional.',
      'hero_copy' => 'Pensado para barber lounges, barberias premium y conceptos que combinan estilo, agenda y ticket alto por experiencia.',
      'offer_title' => 'Experiencia de grooming premium',
      'offer_copy' => 'La portada vende estilo, slots premium, especialistas y bundles de cuidado personal.',
      'catalog_title' => 'Servicios premium y rituales',
      'catalog_copy' => 'Explora cortes premium, rituales de barba, cuidado personal y experiencias pensadas para recompra.',
      'primary_cta_label' => 'Reservar grooming',
      'secondary_cta_label' => 'Hablar con el lounge',
      'footer_copy' => 'Micrositio de belleza conectado a Vito Business OS para barberias premium que quieren verse tan bien como atienden.',
      'trust_points' =>
      [
        0 => 'Slots premium',
        1 => 'Equipo visible',
        2 => 'Experiencia aspiracional',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Rituales que justifican el ticket',
          'copy' => 'La vitrina muestra cortes premium, barba y experiencias de grooming con mejor posicionamiento.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Agenda que se llena mejor',
          'copy' => 'Empuja reservas desde la portada y reduce mensajes frios por disponibilidad.',
          'cta_label' => 'Reservar cita',
          'cta_target' => 'booking',
        ],
        2 =>
        [
          'title' => 'Marca con personalidad',
          'copy' => 'El negocio se siente mas aspiracional, compartible y listo para redes o pauta.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Grooming como experiencia',
          'subtitle' => 'No solo se vende un corte: se vende el ritual, el ambiente y el resultado.',
          'bullets' =>
          [
            0 => 'Servicios premium visibles',
            1 => 'Narrativa aspiracional',
            2 => 'Mejor base para recompra',
          ],
          'cta_label' => 'Explorar servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Agenda y promociones con estilo',
          'subtitle' => 'Promos, membresias o grooming kits pueden convivir con una presencia premium.',
          'bullets' =>
          [
            0 => 'Slots visibles',
            1 => 'Promos mejor presentadas',
            2 => 'WhatsApp como cierre natural',
          ],
          'cta_label' => 'Reservar grooming',
          'cta_target' => 'booking',
        ],
      ],
    ],
    'spa_studio' =>
    [
      'vertical_label' => 'Spa y bienestar',
      'hero_title' => 'Rituales, relajacion y bienestar presentados como una experiencia premium, no como un listado de servicios.',
      'hero_copy' => 'Ideal para spas, cabinas faciales y estudios de bienestar que necesitan transmitir calma, confianza y reservas con menos friccion.',
      'offer_title' => 'Experiencia de bienestar',
      'offer_copy' => 'La vitrina presenta rituales, paquetes y un canal claro para reservar o consultar.',
      'catalog_title' => 'Rituales y tratamientos',
      'catalog_copy' => 'Explora masajes, faciales y experiencias de bienestar con una narrativa mas sensorial y organizada.',
      'primary_cta_label' => 'Reservar ritual',
      'secondary_cta_label' => 'Consultar por WhatsApp',
      'footer_copy' => 'Micrositio de bienestar conectado a Vito Business OS para spas que quieren elevar experiencia, agenda y recompra.',
      'trust_points' =>
      [
        0 => 'Rituales visibles',
        1 => 'Agenda abierta',
        2 => 'Experiencia premium',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Servicios con atmosfera',
          'copy' => 'Masajes, faciales y paquetes se presentan como experiencias deseables y no como fichas frias.',
          'cta_label' => 'Ver tratamientos',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Reserva sin friccion',
          'copy' => 'La agenda o WhatsApp cierran mejor cuando la propuesta ya transmite calma y valor.',
          'cta_label' => 'Reservar ahora',
          'cta_target' => 'booking',
        ],
        2 =>
        [
          'title' => 'Bienestar que se vuelve rutina',
          'copy' => 'La portada deja espacio para paquetes, bonos y recompra mensual.',
          'cta_label' => 'Consultar paquetes',
          'cta_target' => 'whatsapp',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Narrativa mas sensorial',
          'subtitle' => 'La marca transmite descanso, cuidado y ritual desde la primera pantalla.',
          'bullets' =>
          [
            0 => 'Tratamientos mejor explicados',
            1 => 'Espacio para paquetes',
            2 => 'Presencia mas premium',
          ],
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'title' => 'Agenda para recompra',
          'subtitle' => 'El micrositio apoya reservas recurrentes y paquetes con una experiencia mas cuidada.',
          'bullets' =>
          [
            0 => 'Slots mas visibles',
            1 => 'Bonos o packs mas vendibles',
            2 => 'Mejor continuidad de clientes',
          ],
          'cta_label' => 'Reservar ritual',
          'cta_target' => 'booking',
        ],
      ],
    ],
    'auto_parts_store' =>
    [
      'vertical_label' => 'Tienda de repuestos',
      'hero_title' => 'Repuestos, diagnostico y atencion tecnica presentados como una solucion completa para el conductor.',
      'hero_copy' => 'Pensado para tiendas de repuestos, autopartes y negocios que combinan venta de piezas con instalacion o soporte tecnico.',
      'offer_title' => 'Operacion de autopartes',
      'offer_copy' => 'La vitrina mezcla catalogo tecnico, consulta rapida y un puente claro entre pieza, diagnostico e instalacion.',
      'catalog_title' => 'Repuestos y soluciones tecnicas',
      'catalog_copy' => 'Explora baterias, frenos, suspension, filtros y servicios tecnicos con una experiencia mas clara para cotizar.',
      'primary_cta_label' => 'Consultar repuesto',
      'secondary_cta_label' => 'Ver catalogo tecnico',
      'footer_copy' => 'Micrositio hibrido conectado a Vito Business OS para negocios de repuestos que quieren vender piezas y soporte en una sola experiencia.',
      'trust_points' =>
      [
        0 => 'Repuestos visibles',
        1 => 'Diagnostico rapido',
        2 => 'Soporte tecnico',
      ],
      'focus_cards' =>
      [
        0 =>
        [
          'title' => 'Piezas faciles de encontrar',
          'copy' => 'Filtros, frenos, baterias y repuestos clave aparecen con mejor orden para cotizar.',
          'cta_label' => 'Ver repuestos',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Diagnostico antes de comprar',
          'copy' => 'El cliente puede validar compatibilidad, falla o instalacion desde un solo punto de entrada.',
          'cta_label' => 'Consultar diagnostico',
          'cta_target' => 'whatsapp',
        ],
        2 =>
        [
          'title' => 'Negocio mixto bien explicado',
          'copy' => 'La marca deja claro que no solo vende piezas: tambien acompana la solucion.',
          'cta_label' => 'Ver soluciones',
          'cta_target' => '#ofertas',
        ],
      ],
      'specialized_blocks' =>
      [
        0 =>
        [
          'title' => 'Catalogo tecnico para venta real',
          'subtitle' => 'La vitrina esta pensada para resolver compatibilidad, urgencia y ticket medio.',
          'bullets' =>
          [
            0 => 'Autopartes mas visibles',
            1 => 'Bloques para compatibilidad',
            2 => 'Mejor base para cotizacion',
          ],
          'cta_label' => 'Explorar repuestos',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'title' => 'Puente entre pieza y servicio',
          'subtitle' => 'El cliente entiende rapido si debe comprar, consultar o instalar.',
          'bullets' =>
          [
            0 => 'Diagnostico inicial',
            1 => 'Atencion por WhatsApp',
            2 => 'Experiencia hibrida mas clara',
          ],
          'cta_label' => 'Consultar por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
      ],
    ],
  ],
  'playbooks' =>
  [
    'retail_general' =>
    [
      'journey_title' => 'Descubre, compara y avanza',
      'journey_copy' => 'La vitrina tiene que mover una accion concreta: explorar, cotizar o escribir al negocio sin perder impulso.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Presenta lo mas buscado',
          'copy' => 'El cliente detecta rapido productos, precios y categorias con mejor salida.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Reduce la friccion de compra',
          'copy' => 'WhatsApp, catalogo y bloques claros evitan que la visita se enfrie.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Empuja una siguiente accion',
          'copy' => 'La experiencia termina en cotizacion, llamada o visita a tienda, no en una duda.',
        ],
      ],
      'growth_title' => 'Palancas para retail local',
      'growth_copy' => 'Vito Business OS ayuda a que una tienda se vea profesional y convierta mejor desde movil y escritorio.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Vitrina',
          'title' => 'Destaca productos ganadores',
          'copy' => 'Usa la portada y el catalogo para empujar novedades, top sellers y bundles.',
          'cta_label' => 'Ver catalogo',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'eyebrow' => 'Conversion',
          'title' => 'Acelera cotizaciones',
          'copy' => 'El cliente llega con mas contexto antes de escribir y pregunta con mejor intencion.',
          'cta_label' => 'Abrir WhatsApp',
          'cta_target' => 'whatsapp',
        ],
        2 =>
        [
          'eyebrow' => 'Operacion',
          'title' => 'Sostiene presencia constante',
          'copy' => 'Horarios, contacto y propuesta comercial quedan visibles aunque el negocio no este respondiendo al instante.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
      'catalog_panel_title' => 'Catalogo listo para vender',
      'catalog_panel_copy' => 'Productos visibles, precios claros y una vitrina que lleva al cliente a cotizar o comprar sin perderse.',
    ],
    'restaurant' =>
    [
      'journey_title' => 'Descubre, pide y vuelve',
      'journey_copy' => 'Un buen micrositio gastronomico hace tres cosas bien: abre apetito, acelera el pedido y deja espacio para recompra.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Muestra platos que venden solos',
          'copy' => 'Combos, platos estrella y bebidas quedan claros antes de abrir el chat.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Empuja el pedido rapido',
          'copy' => 'La transicion a WhatsApp o al menu ocurre sin perder el hambre del cliente.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Activa recompra y promos',
          'copy' => 'Promociones del dia y extras ayudan a subir ticket y retorno.',
        ],
      ],
      'growth_title' => 'Palancas para restaurante',
      'growth_copy' => 'La portada publica se convierte en un canal comercial real para menu, pedidos y promociones.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Menu',
          'title' => 'Combos que levantan ticket',
          'copy' => 'Organiza secciones para vender entradas, platos fuertes, bebidas y extras con mas claridad.',
          'cta_label' => 'Ver menu',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'eyebrow' => 'Pedidos',
          'title' => 'Canal directo a WhatsApp',
          'copy' => 'El cliente no tiene que preguntar desde cero para pedir; ya llega orientado.',
          'cta_label' => 'Pedir ahora',
          'cta_target' => 'whatsapp',
        ],
        2 =>
        [
          'eyebrow' => 'Retencion',
          'title' => 'Promos del dia mas visibles',
          'copy' => 'La experiencia deja espacio para especiales, combos de almuerzo y novedades.',
          'cta_label' => 'Explorar platos',
          'cta_target' => '#catalogo',
        ],
      ],
      'catalog_panel_title' => 'Platos, combos y bebidas',
      'catalog_panel_copy' => 'Explora el menu publico con secciones, platos destacados y bloques listos para convertir hambre en pedido.',
    ],
    'cellphone_store' =>
    [
      'journey_title' => 'Descubre, compara y cierra',
      'journey_copy' => 'En tecnologia ligera, la vitrina debe mostrar producto premium, bundles y una salida rapida a cotizacion o soporte.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Destaca equipos bandera',
          'copy' => 'El cliente detecta rapido smartphones, modelos premium y accesorios clave.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Muestra bundles y extras',
          'copy' => 'Cases, cargadores, protectores y kits elevan el valor promedio sin complicar la experiencia.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Integra venta y soporte',
          'copy' => 'La misma vitrina sirve para vender, cotizar y abrir una conversacion postventa.',
        ],
      ],
      'growth_title' => 'Palancas para retail avanzado',
      'growth_copy' => 'La tienda se ve como un negocio moderno: vitrina fuerte, bundles claros y soporte visible.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Flagship',
          'title' => 'Empuja equipos premium',
          'copy' => 'Los productos mas deseados deben aparecer primero y con mejor contexto comercial.',
          'cta_label' => 'Ver equipos',
          'cta_target' => '#catalogo',
        ],
        1 =>
        [
          'eyebrow' => 'Upsell',
          'title' => 'Convierte accesorios en margen',
          'copy' => 'La vitrina debe vender cargadores, cases y kits sin depender del mostrador fisico.',
          'cta_label' => 'Explorar catalogo',
          'cta_target' => '#catalogo',
        ],
        2 =>
        [
          'eyebrow' => 'Posventa',
          'title' => 'Haz visible garantia y soporte',
          'copy' => 'El cliente entiende rapido que no solo vendes el equipo, tambien lo acompanias.',
          'cta_label' => 'Cotizar por WhatsApp',
          'cta_target' => 'whatsapp',
        ],
      ],
      'catalog_panel_title' => 'Equipos, accesorios y bundles',
      'catalog_panel_copy' => 'Desde flagship phones hasta kits de upsell: esta vitrina esta pensada para retail tecnologico que quiere vender mas y mejor.',
    ],
    'clinic' =>
    [
      'journey_title' => 'Orienta, agenda y da seguimiento',
      'journey_copy' => 'Una clinica vende confianza antes de vender tiempo. El micrositio tiene que ordenar la primera consulta y bajar la ansiedad del paciente.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Muestra especialidades y valoraciones',
          'copy' => 'El paciente entiende que servicios existen y cual es el siguiente paso correcto.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Lleva a agenda o recepcion',
          'copy' => 'Reservar o escribir debe sentirse claro, serio y facil desde movil.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Refuerza seguimiento',
          'copy' => 'Horarios, ubicacion y contexto ayudan a que la visita termine en cita y no en abandono.',
        ],
      ],
      'growth_title' => 'Palancas para clinica o centro de salud',
      'growth_copy' => 'La experiencia publica ayuda a orientar mejor al paciente y a ordenar la demanda desde el primer clic.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Agenda',
          'title' => 'Reservas mas claras',
          'copy' => 'Servicios, duracion y recepcion visible reducen llamadas repetidas y dudas basicas.',
          'cta_label' => 'Reservar evaluacion',
          'cta_target' => 'booking',
        ],
        1 =>
        [
          'eyebrow' => 'Confianza',
          'title' => 'Mas seriedad institucional',
          'copy' => 'El paciente ve una clinica organizada, con especialidades, horarios y ubicacion bien explicados.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
        2 =>
        [
          'eyebrow' => 'Orientacion',
          'title' => 'Filtra mejor la demanda',
          'copy' => 'El primer contacto llega mas preparado y con mejor expectativa de atencion.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
      ],
      'service_panel_title' => 'Especialidades, evaluaciones y seguimiento',
      'service_panel_copy' => 'Servicios visibles, agenda clara y contexto suficiente para que el paciente reserve con mas confianza.',
    ],
    'barbershop' =>
    [
      'journey_title' => 'Muestra estilo, llena agenda y empuja promos',
      'journey_copy' => 'En belleza y grooming, la presencia manda. El micrositio tiene que verse premium y llevar rapido a reserva.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Explica servicios con claridad',
          'copy' => 'Cortes, barba, grooming y extras se entienden de inmediato.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Empuja la cita',
          'copy' => 'La agenda visible convierte la visita en una reserva real y no en un mensaje frio.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Mueve promociones y recurrencia',
          'copy' => 'Promos, combos y slots disponibles ayudan a llenar horas ociosas.',
        ],
      ],
      'growth_title' => 'Palancas para grooming y belleza',
      'growth_copy' => 'La portada publica ayuda a vender estilo, confianza y disponibilidad desde movil.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Agenda',
          'title' => 'Slots listos para reservar',
          'copy' => 'El cliente puede pasar de interes a cita sin pasos innecesarios.',
          'cta_label' => 'Reservar cita',
          'cta_target' => 'booking',
        ],
        1 =>
        [
          'eyebrow' => 'Imagen',
          'title' => 'Presencia premium desde la portada',
          'copy' => 'Una barberia o salon debe verse tan bien como atiende.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        2 =>
        [
          'eyebrow' => 'Promos',
          'title' => 'Campanas y combos mas visibles',
          'copy' => 'Ideal para impulsar grooming kits, promos de temporada o recompra.',
          'cta_label' => 'Consultar',
          'cta_target' => 'whatsapp',
        ],
      ],
      'service_panel_title' => 'Cortes, grooming y citas visibles',
      'service_panel_copy' => 'Servicios con precio, duracion y CTA listos para convertir la visita en reserva.',
    ],
    'law_firm' =>
    [
      'journey_title' => 'Filtra el caso, agenda y prepara la reunion',
      'journey_copy' => 'Una firma legal no solo necesita presencia; necesita reducir consultas frias y atraer conversaciones mejor preparadas.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Explica areas de practica',
          'copy' => 'El cliente sabe rapido si el despacho puede ayudarle y por donde empezar.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Lleva a consulta inicial',
          'copy' => 'La reserva o el contacto se sienten serios, claros y coherentes con un servicio profesional.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Ordena el primer filtro',
          'copy' => 'El despacho recibe casos con mejor contexto y menos ida y vuelta inicial.',
        ],
      ],
      'growth_title' => 'Palancas para firma legal',
      'growth_copy' => 'El micrositio profesional ayuda a elevar la seriedad percibida y a mejorar la calidad del primer contacto.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Practica',
          'title' => 'Areas visibles y mejor encaje',
          'copy' => 'Presenta servicios legales para clientes personales o corporativos con menos ambiguedad.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'eyebrow' => 'Intake',
          'title' => 'Primera consulta mejor filtrada',
          'copy' => 'Reduce ruido operativo dejando claro como reservar, escribir o preparar la reunion.',
          'cta_label' => 'Agendar consulta',
          'cta_target' => 'booking',
        ],
        2 =>
        [
          'eyebrow' => 'Confianza',
          'title' => 'Presencia mas seria y consistente',
          'copy' => 'Ubicacion, horarios y narrativa profesional ayudan a cerrar mejor el primer contacto.',
          'cta_label' => 'Hablar con el despacho',
          'cta_target' => 'whatsapp',
        ],
      ],
      'service_panel_title' => 'Consultas, areas de practica y primera llamada',
      'service_panel_copy' => 'Servicios legales visibles, propuesta clara y un canal serio para pasar de visita a consulta.',
    ],
    'consultancy' =>
    [
      'journey_title' => 'Diagnostica, propone y acompana',
      'journey_copy' => 'La consultoria crece cuando la visita termina en una discovery call util y no en una duda abierta.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Define el problema que resuelves',
          'copy' => 'Servicios y paquetes ayudan a que el lead entienda para quien es la propuesta.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Lleva a llamada o diagnostico',
          'copy' => 'La primera reunion llega mas preparada y con mejor intencion comercial.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Abre espacio para seguimiento',
          'copy' => 'WhatsApp y la narrativa del sitio ayudan a sostener el funnel despues de la primera conversacion.',
        ],
      ],
      'growth_title' => 'Palancas para consultoria',
      'growth_copy' => 'La presencia publica pasa de brochure a herramienta real de captura, filtro y seguimiento comercial.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Discovery',
          'title' => 'Llamadas de diagnostico mejor encuadradas',
          'copy' => 'El lead entiende el valor de la primera reunion antes de escribir.',
          'cta_label' => 'Agendar llamada',
          'cta_target' => 'booking',
        ],
        1 =>
        [
          'eyebrow' => 'Oferta',
          'title' => 'Servicios y paquetes mas vendibles',
          'copy' => 'La propuesta deja de verse abstracta y se vuelve mas concreta para la PYME.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        2 =>
        [
          'eyebrow' => 'Funnel',
          'title' => 'Seguimiento comercial mas ordenado',
          'copy' => 'Integra landing, llamada y WhatsApp dentro de una misma experiencia de marca.',
          'cta_label' => 'Solicitar informacion',
          'cta_target' => 'whatsapp',
        ],
      ],
      'service_panel_title' => 'Discovery calls, sesiones y paquetes de acompanamiento',
      'service_panel_copy' => 'Servicios visibles, CTA consultivos y una experiencia pensada para cerrar llamadas con mas contexto.',
    ],
    'workshop' =>
    [
      'journey_title' => 'Diagnostica, aprueba y entrega',
      'journey_copy' => 'Un taller o servicio tecnico debe dejar claro que hace, como inicia el diagnostico y que extras puede resolver en el mismo proceso.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Explica el servicio tecnico',
          'copy' => 'Diagnosticos, revisiones o mantenimientos se entienden rapido sin depender del mostrador.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Abre el canal correcto',
          'copy' => 'WhatsApp o reserva sirven para recibir mejor el caso antes de que llegue al negocio.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Coordina repuestos y extras',
          'copy' => 'La misma experiencia ayuda a vender repuestos, accesorios y soluciones complementarias.',
        ],
      ],
      'growth_title' => 'Palancas para taller o servicio tecnico',
      'growth_copy' => 'El micrositio deja de ser solo presencia y se vuelve una entrada operativa para diagnosticos y ventas complementarias.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Diagnostico',
          'title' => 'Primer contacto con mejor contexto',
          'copy' => 'El cliente puede explicar su caso despues de ver servicios y alcance.',
          'cta_label' => 'Consultar diagnostico',
          'cta_target' => 'whatsapp',
        ],
        1 =>
        [
          'eyebrow' => 'Repuestos',
          'title' => 'Catalogo tecnico mas vendible',
          'copy' => 'Repuestos y accesorios visibles elevan la oportunidad de ticket mixto.',
          'cta_label' => 'Ver catalogo tecnico',
          'cta_target' => '#catalogo',
        ],
        2 =>
        [
          'eyebrow' => 'Operacion',
          'title' => 'Servicio y venta sin fragmentar la experiencia',
          'copy' => 'Productos, mano de obra y contacto viven dentro del mismo frente comercial.',
          'cta_label' => 'Ver ofertas',
          'cta_target' => '#ofertas',
        ],
      ],
      'service_panel_title' => 'Diagnosticos, mano de obra y seguimiento tecnico',
      'service_panel_copy' => 'Servicios tecnicos visibles con un canal de entrada mas ordenado para cada caso.',
      'catalog_panel_title' => 'Repuestos, accesorios y soluciones tecnicas',
      'catalog_panel_copy' => 'El catalogo tecnico acompana al servicio: repuestos visibles, extras claros y mejor contexto comercial.',
    ],
    'service_general' =>
    [
      'journey_title' => 'Aclara, reserva y da seguimiento',
      'journey_copy' => 'Todo negocio de servicios necesita una ruta simple desde la duda hasta la consulta o la reserva.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Presenta servicios con contexto',
          'copy' => 'Duracion, precio y foco ayudan a tomar una mejor decision.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Abre el canal correcto',
          'copy' => 'Reserva, llamada o WhatsApp segun como convenga al negocio.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Mantiene una presencia creible',
          'copy' => 'Horarios, ubicacion y propuesta quedan visibles aunque el equipo este ocupado.',
        ],
      ],
      'growth_title' => 'Palancas para negocio de servicios',
      'growth_copy' => 'El micrositio ayuda a que el cliente llegue mas preparado y el negocio responda mejor.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Servicios',
          'title' => 'Oferta mas entendible',
          'copy' => 'Deja claro que resuelves y como se empieza a trabajar contigo.',
          'cta_label' => 'Ver servicios',
          'cta_target' => '#servicios',
        ],
        1 =>
        [
          'eyebrow' => 'Contacto',
          'title' => 'Canal directo a consulta',
          'copy' => 'El visitante pasa de interes a accion sin demasiados pasos.',
          'cta_label' => 'Consultar',
          'cta_target' => 'whatsapp',
        ],
        2 =>
        [
          'eyebrow' => 'Operacion',
          'title' => 'Presencia profesional constante',
          'copy' => 'Una base clara para crecer con campanas, SEO local y recomendaciones.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
      'service_panel_title' => 'Servicios visibles y contacto directo',
      'service_panel_copy' => 'Servicios, tiempos y precios listos para que el cliente avance con menos friccion.',
    ],
    'hybrid_general' =>
    [
      'journey_title' => 'Muestra, resuelve y acompana',
      'journey_copy' => 'Los negocios mixtos crecen cuando el cliente entiende que puede comprar, consultar y reservar desde una sola experiencia.',
      'journey_steps' =>
      [
        0 =>
        [
          'eyebrow' => 'Paso 1',
          'title' => 'Explica la mezcla del negocio',
          'copy' => 'Productos y servicios aparecen conectados, no como mundos separados.',
        ],
        1 =>
        [
          'eyebrow' => 'Paso 2',
          'title' => 'Lleva a la accion correcta',
          'copy' => 'Reserva, consulta o compra segun el contexto del negocio y del cliente.',
        ],
        2 =>
        [
          'eyebrow' => 'Paso 3',
          'title' => 'Empuja ticket y seguimiento',
          'copy' => 'La misma visita puede abrir espacio para upsells, soporte o recompra.',
        ],
      ],
      'growth_title' => 'Palancas para negocio mixto',
      'growth_copy' => 'Integra catalogo, agenda y contacto dentro de un solo frente comercial.',
      'growth_cards' =>
      [
        0 =>
        [
          'eyebrow' => 'Oferta',
          'title' => 'Productos y servicios sin friccion',
          'copy' => 'Una misma portada puede vender, explicar y activar seguimiento.',
          'cta_label' => 'Ver ofertas',
          'cta_target' => '#ofertas',
        ],
        1 =>
        [
          'eyebrow' => 'Conversion',
          'title' => 'CTA mas coherentes',
          'copy' => 'El cliente no tiene que adivinar si debe reservar, escribir o explorar catalogo.',
          'cta_label' => 'Abrir workspace',
          'cta_target' => '#workspace',
        ],
        2 =>
        [
          'eyebrow' => 'Crecimiento',
          'title' => 'Base lista para escalar',
          'copy' => 'Ideal para negocios que evolucionan y agregan nuevas lineas de ingreso.',
          'cta_label' => 'Ver informacion',
          'cta_target' => '#informacion',
        ],
      ],
    ],
  ],
],

    'rubros' => [
        'retail_general' => [
            'business_type' => BusinessType::RETAIL->value,
            'presentation' => [
                'label' => 'Tienda y catalogo',
                'description' => 'Para negocios que venden productos, manejan inventario y necesitan pedidos claros.',
                'examples' => ['Tienda de celulares', 'Ferreteria', 'Moda', 'Electronica'],
                'teaser' => 'Convierte tu catalogo en ventas medibles.',
            ],
            'registration' => [
                'suggested_categories' => ['tecnologia', 'moda', 'ferreteria', 'hogar'],
            ],
            'capabilities' => [
                'include' => [
                    Capability::PRODUCT_VARIANTS->value,
                    Capability::INVENTORY_TRACKING->value,
                    Capability::STOCK_ALERTS->value,
                    Capability::PICKUP_ORDERS->value,
                    Capability::SHIPPING_ORDERS->value,
                ],
                'exclude' => [],
            ],
            'modules' => [
                'business_app' => ['products', 'menu_sections', 'orders', 'customers', 'campaigns', 'reports'],
                'user_app' => ['directory', 'storefront', 'checkout', 'whatsapp'],
            ],
            'dynamic_forms' => [
                'entities' => [
                    ['module' => 'sales', 'entity_key' => 'customer_quote'],
                    ['module' => 'support', 'entity_key' => 'product_warranty_case'],
                ],
            ],
            'business_app' => [
                'dashboard_title' => 'Tu centro operativo para retail',
                'dashboard_copy' => 'Prioriza catalogo, pedidos, cobertura y atencion comercial para que la vitrina no se quede solo en bonita.',
                'workspace_title' => 'Frentes que debes activar primero',
                'workspace_copy' => 'Estos modulos te dan la mejor palanca inicial para vender mejor.',
                'playbook_copy' => 'Activa catalogo, canal de cierre y cobertura antes de invertir energia en soporte.',
            ],
            'crm' => [
                'preferred_segments' => ['prospect', 'active', 'repeat', 'reactivation', 'vip'],
                'templates' => ['fast_follow_up', 'close_sale', 'follow_up_reminder', 'final_close_push'],
                'routing' => [
                    'default' => ['group' => 'sales'],
                ],
                'automation' => [
                    'reactivation_recovery' => ['channel' => 'email'],
                    'follow_up_reminder' => ['channel' => 'whatsapp'],
                ],
                'journeys' => [
                    'prospect' => [
                        'journey' => [
                            ['key' => 'retail_prospect_fast_follow_up', 'title' => 'Responder hoy', 'copy' => 'Responde con contexto y CTA de cotizacion o compra.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                            ['key' => 'retail_prospect_close', 'title' => 'Mover al cierre', 'copy' => 'Empuja el siguiente paso comercial sin dejar la conversacion tibia.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 18],
                        ],
                        'templates' => ['fast_follow_up', 'close_sale', 'follow_up_reminder'],
                    ],
                    'repeat' => [
                        'journey' => [
                            ['key' => 'retail_repeat_fast_follow_up', 'title' => 'Responder rapido', 'copy' => 'No dejes que una recompra se enfrie por falta de seguimiento humano.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                            ['key' => 'retail_repeat_bundle', 'title' => 'Empujar bundle o complemento', 'copy' => 'Usa continuidad, bundle o accesorio para subir ticket.', 'template_key' => 'follow_up_reminder', 'lane' => 'follow_up', 'wait_hours' => 6],
                            ['key' => 'retail_repeat_close', 'title' => 'Cerrar recompra', 'copy' => 'Busca que el cliente salga con una nueva compra o pedido listo.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 20],
                        ],
                        'templates' => ['fast_follow_up', 'close_sale', 'follow_up_reminder', 'final_close_push'],
                    ],
                    'reactivation' => [
                        'journey' => [
                            ['key' => 'retail_reactivation_recover', 'title' => 'Volver a abrir la conversacion', 'copy' => 'Recupera contexto y baja friccion para el regreso.', 'template_key' => 'reactivation_recovery', 'lane' => 'reactivation', 'wait_hours' => 0],
                            ['key' => 'retail_reactivation_last_push', 'title' => 'Cerrar esta ventana', 'copy' => 'Usa un ultimo empuje claro antes de enfriar el lead o cliente.', 'template_key' => 'final_close_push', 'lane' => 'last_call', 'wait_hours' => 24],
                        ],
                        'templates' => ['reactivation_recovery', 'final_close_push'],
                    ],
                ],
            ],
            'onboarding' => [
                'title' => 'Activa tu tienda digital',
                'copy' => 'Empieza por perfil, catalogo y formas de cobro para que el directorio entienda que vendes.',
                'playbook_title' => 'Playbook de ventas por catalogo',
                'recommended_modules' => ['profile', 'catalog', 'payments', 'whatsapp'],
                'health_checks' => ['has_profile', 'has_products', 'has_payment_method'],
                'playbook_steps' => [
                    ['title' => 'Publica tu catálogo ganador', 'copy' => 'Empieza por los productos que más convierten o más margen dejan.'],
                    ['title' => 'Une cobro y contacto', 'copy' => 'Activa el canal de cierre antes de mandar trafico al negocio.'],
                    ['title' => 'Valida la vitrina publica', 'copy' => 'Revisa que el micrositio realmente explique que vendes y como comprar.'],
                ],
            ],
            'growth_center' => [
                'headline' => 'CRM y crecimiento retail',
                'copy' => 'Convierte catalogo, pedidos y recompra en una operacion comercial repetible.',
                'journeys' => [
                    ['eyebrow' => 'CRM', 'title' => 'Recuperar cotizaciones frias', 'copy' => 'Empuja seguimiento de prospectos con intencion de compra.', 'trigger' => 'prospect', 'channel' => 'Email', 'route_key' => 'customers', 'state' => 'audience_ready'],
                    ['eyebrow' => 'Recompra', 'title' => 'Mover clientes repetidos', 'copy' => 'Activa bundles, accesorios y continuidad comercial.', 'trigger' => 'repeat', 'channel' => 'WhatsApp', 'route_key' => 'campaigns', 'state' => 'promo_growth'],
                ],
                'cadence_playbooks' => [
                    ['title' => 'Cadencia de cierre retail', 'cadence' => '48h', 'trigger' => 'prospect', 'channel' => 'CRM', 'copy' => 'Recupera leads con contexto antes de que se enfrien.', 'route_key' => 'campaigns', 'state' => 'audience_ready'],
                    ['title' => 'Cadencia de recompra', 'cadence' => 'Semanal', 'trigger' => 'repeat', 'channel' => 'WhatsApp', 'copy' => 'Sostiene recompra y ticket promedio con ofertas relevantes.', 'route_key' => 'campaigns', 'state' => 'orders_or_audience'],
                ],
            ],
        ],

        'restaurant_general' => [
            'business_type' => BusinessType::FOOD->value,
            'presentation' => [
                'label' => 'Restaurante y comida',
                'description' => 'Para restaurantes, cafeterias, dark kitchens y negocios con menu operativo.',
                'examples' => ['Restaurante', 'Cafeteria', 'Food truck', 'Dark kitchen'],
                'teaser' => 'Menu, pedidos y cocina sin caos.',
            ],
            'registration' => [
                'suggested_categories' => ['restaurantes', 'cafeterias', 'comida-rapida', 'reposteria'],
            ],
            'capabilities' => [
                'include' => [
                    Capability::MENU_MODIFIERS->value,
                    Capability::KITCHEN_DISPLAY->value,
                    Capability::TABLE_MANAGEMENT->value,
                    Capability::DELIVERY_ORDERS->value,
                    Capability::PICKUP_ORDERS->value,
                ],
                'exclude' => [],
            ],
            'modules' => [
                'business_app' => ['products', 'menu_sections', 'orders', 'kitchen', 'campaigns'],
                'user_app' => ['directory', 'storefront', 'checkout', 'delivery', 'whatsapp'],
            ],
            'dynamic_forms' => [
                'entities' => [
                    ['module' => 'kitchen', 'entity_key' => 'catering_request'],
                    ['module' => 'delivery', 'entity_key' => 'special_order'],
                ],
            ],
            'business_app' => [
                'dashboard_title' => 'Tu centro operativo para menú y pedidos',
                'dashboard_copy' => 'El panel debe ayudarte a vender mas rapido, rotar productos y sostener servicio diario.',
                'workspace_title' => 'Frentes criticos para cocina y venta',
                'workspace_copy' => 'Estos espacios reducen friccion entre menu, pedidos, horarios y recompra.',
                'playbook_copy' => 'Menu claro, horarios y pedido directo son la base antes de promocionar.',
            ],
            'crm' => [
                'preferred_segments' => ['new', 'active', 'repeat', 'reactivation', 'vip'],
                'templates' => ['fast_follow_up', 'close_sale', 'follow_up_reminder', 'reactivation_recovery'],
                'routing' => [
                    'default' => ['group' => 'sales'],
                ],
                'automation' => [
                    'reactivation_recovery' => ['channel' => 'whatsapp'],
                    'follow_up_reminder' => ['channel' => 'whatsapp'],
                ],
                'journeys' => [
                    'prospect' => [
                        'journey' => [
                            ['key' => 'restaurant_prospect_fast_follow_up', 'title' => 'Responder con hambre', 'copy' => 'No dejes que el pedido potencial se enfrie.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                            ['key' => 'restaurant_prospect_close', 'title' => 'Cerrar pedido o reserva', 'copy' => 'Mueve combo, pedido o mesa con CTA directo.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 4],
                        ],
                        'templates' => ['fast_follow_up', 'close_sale', 'follow_up_reminder'],
                    ],
                    'repeat' => [
                        'journey' => [
                            ['key' => 'restaurant_repeat_combo', 'title' => 'Empujar combo o extra', 'copy' => 'Usa bebidas, extras y promociones para subir ticket.', 'template_key' => 'follow_up_reminder', 'lane' => 'upsell', 'wait_hours' => 4],
                            ['key' => 'restaurant_repeat_close', 'title' => 'Cerrar recompra del dia', 'copy' => 'Busca el siguiente pedido mientras el apetito sigue vivo.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 12],
                        ],
                        'templates' => ['follow_up_reminder', 'close_sale', 'reactivation_recovery'],
                    ],
                    'reactivation' => [
                        'journey' => [
                            ['key' => 'restaurant_reactivation_offer', 'title' => 'Reactivar con promo o motivo de regreso', 'copy' => 'Trae de vuelta al cliente con una razon concreta.', 'template_key' => 'reactivation_recovery', 'lane' => 'reactivation', 'wait_hours' => 0],
                            ['key' => 'restaurant_reactivation_last_call', 'title' => 'Cerrar la ventana de regreso', 'copy' => 'Haz un ultimo intento claro antes de perder la atencion.', 'template_key' => 'follow_up_reminder', 'lane' => 'last_call', 'wait_hours' => 24],
                        ],
                        'templates' => ['reactivation_recovery', 'follow_up_reminder'],
                    ],
                ],
            ],
            'onboarding' => [
                'title' => 'Publica tu menu y prepara pedidos',
                'copy' => 'Configura menu, canales de pedido y pagos para que cada orden llegue clara al equipo.',
                'playbook_title' => 'Playbook operativo de restaurante',
                'recommended_modules' => ['profile', 'menu', 'orders', 'payments'],
                'health_checks' => ['has_profile', 'has_menu_items', 'has_order_channel'],
                'playbook_steps' => [
                    ['title' => 'Destaca platos y combos', 'copy' => 'Haz visible lo que mejor vende antes de pensar en promociones.'],
                    ['title' => 'Acelera pedido y contacto', 'copy' => 'Aclara como pedir para reducir friccion operativa.'],
                    ['title' => 'Valida horarios y experiencia publica', 'copy' => 'El cliente debe entender cuando atiendes y como avanzar.'],
                ],
            ],
            'growth_center' => [
                'headline' => 'CRM y crecimiento gastronomico',
                'copy' => 'Combina menu, pedido directo y recompra con una operacion comercial mas disciplinada.',
                'journeys' => [
                    ['eyebrow' => 'Pedido', 'title' => 'Recuperar pedidos tibios', 'copy' => 'Mueve respuestas rapidas y cierra combos o reservas.', 'trigger' => 'prospect', 'channel' => 'WhatsApp', 'route_key' => 'campaigns', 'state' => 'whatsapp_channel'],
                    ['eyebrow' => 'Recompra', 'title' => 'Activar regreso semanal', 'copy' => 'Usa promos y segmentos repetidos para sostener volumen.', 'trigger' => 'repeat', 'channel' => 'CRM', 'route_key' => 'campaigns', 'state' => 'orders_or_audience'],
                ],
                'cadence_playbooks' => [
                    ['title' => 'Cadencia de lunch o cena', 'cadence' => 'Semanal', 'trigger' => 'repeat', 'channel' => 'WhatsApp', 'copy' => 'Sostiene recompra con combos y specials.', 'route_key' => 'campaigns', 'state' => 'orders_or_audience'],
                    ['title' => 'Cadencia de rescate de carrito', 'cadence' => '24h', 'trigger' => 'prospect', 'channel' => 'CRM', 'copy' => 'Recupera clientes que mostraron hambre pero no cerraron.', 'route_key' => 'campaigns', 'state' => 'promo_growth'],
                ],
            ],
        ],

        'service_general' => [
            'business_type' => BusinessType::SERVICE->value,
            'presentation' => [
                'label' => 'Servicios y citas',
                'description' => 'Para consultorias, clinicas, belleza y negocios donde agenda y seguimiento importan.',
                'examples' => ['Consultoria', 'Abogados', 'Clinica', 'Salon de belleza'],
                'teaser' => 'Agenda, clientes y seguimiento en una sola cabina.',
            ],
            'registration' => [
                'suggested_categories' => ['servicios', 'servicios-profesionales', 'salud', 'belleza', 'educacion'],
            ],
            'capabilities' => [
                'include' => [
                    Capability::APPOINTMENT_BOOKING->value,
                    Capability::STAFF_CALENDAR->value,
                    Capability::SERVICE_DURATION_BUFFERS->value,
                    Capability::SERVICE_PACKAGES->value,
                    Capability::APPOINTMENT_REMINDERS->value,
                ],
                'exclude' => [],
            ],
            'modules' => [
                'business_app' => ['services', 'appointments', 'customers', 'campaigns', 'reports'],
                'user_app' => ['directory', 'storefront', 'booking', 'whatsapp'],
            ],
            'dynamic_forms' => [
                'entities' => [
                    ['module' => 'appointments', 'entity_key' => 'service_intake'],
                    ['module' => 'crm', 'entity_key' => 'client_discovery'],
                ],
            ],
            'business_app' => [
                'dashboard_title' => 'Tu centro operativo para servicios y agenda',
                'dashboard_copy' => 'Ordena la forma en que te descubren, reservan y vuelven a contactarte.',
                'workspace_title' => 'Frentes clave para vender servicios',
                'workspace_copy' => 'Estos modulos empujan conversion, agenda y seguimiento sin depender de mensajes sueltos.',
                'playbook_copy' => 'Define oferta, responsables y disponibilidad antes de abrir agenda al publico.',
            ],
            'crm' => [
                'preferred_segments' => ['prospect', 'booked_service', 'active', 'repeat', 'reactivation', 'vip'],
                'templates' => ['service_rebook', 'fast_follow_up', 'close_sale', 'vip_priority'],
                'routing' => [
                    'default' => ['group' => 'support'],
                ],
                'automation' => [
                    'service_rebook' => ['channel' => 'whatsapp'],
                    'reactivation_recovery' => ['channel' => 'email'],
                ],
                'journeys' => [
                    'prospect' => [
                        'journey' => [
                            ['key' => 'service_prospect_fast_follow_up', 'title' => 'Responder y orientar', 'copy' => 'Aclara el servicio correcto y baja friccion para discovery o reserva.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                            ['key' => 'service_prospect_close', 'title' => 'Mover a cita o cierre', 'copy' => 'No dejes la conversacion sin fecha o siguiente paso.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 18],
                        ],
                        'templates' => ['fast_follow_up', 'close_sale', 'service_rebook'],
                    ],
                    'booked_service' => [
                        'journey' => [
                            ['key' => 'service_booked_continuity', 'title' => 'Confirmar continuidad', 'copy' => 'Une la cita actual con el siguiente control o servicio.', 'template_key' => 'service_rebook', 'lane' => 'continuity', 'wait_hours' => 0],
                            ['key' => 'service_booked_reduce_no_show', 'title' => 'Reducir no-show', 'copy' => 'Reagenda rapido y deja la siguiente accion visible.', 'template_key' => 'follow_up_reminder', 'lane' => 'reminder', 'wait_hours' => 16],
                        ],
                        'templates' => ['service_rebook', 'follow_up_reminder', 'vip_priority'],
                    ],
                    'repeat' => [
                        'journey' => [
                            ['key' => 'service_repeat_keep_pace', 'title' => 'Mantener la continuidad', 'copy' => 'Evita que el cliente repetido se enfrie entre servicios.', 'template_key' => 'service_rebook', 'lane' => 'continuity', 'wait_hours' => 8],
                            ['key' => 'service_repeat_expand', 'title' => 'Abrir paquete o plan', 'copy' => 'Usa la buena experiencia para mover expansion.', 'template_key' => 'vip_priority', 'lane' => 'expansion', 'wait_hours' => 30],
                        ],
                        'templates' => ['service_rebook', 'vip_priority', 'close_sale'],
                    ],
                    'reactivation' => [
                        'journey' => [
                            ['key' => 'service_reactivation_recover', 'title' => 'Reactivar continuidad', 'copy' => 'Recupera la conversacion con una salida clara a reserva o seguimiento.', 'template_key' => 'reactivation_recovery', 'lane' => 'reactivation', 'wait_hours' => 0],
                            ['key' => 'service_reactivation_last_call', 'title' => 'Cerrar la ultima ventana', 'copy' => 'Haz un ultimo rescate antes de perder la continuidad.', 'template_key' => 'follow_up_reminder', 'lane' => 'last_call', 'wait_hours' => 24],
                        ],
                        'templates' => ['reactivation_recovery', 'follow_up_reminder'],
                    ],
                ],
            ],
            'onboarding' => [
                'title' => 'Activa tus servicios reservables',
                'copy' => 'Crea servicios, disponibilidad y equipo para que el cliente pueda reservar sin escribirte diez veces.',
                'playbook_title' => 'Playbook de agenda y seguimiento',
                'recommended_modules' => ['profile', 'services', 'staff', 'booking'],
                'health_checks' => ['has_profile', 'has_services', 'has_provider'],
                'playbook_steps' => [
                    ['title' => 'Define tu oferta principal', 'copy' => 'Publica servicios con precio, duracion y alcance claros.'],
                    ['title' => 'Activa responsables y disponibilidad', 'copy' => 'Haz visible quien atiende y cuando existe capacidad real.'],
                    ['title' => 'Valida la reserva publica', 'copy' => 'Comprueba que el cliente pueda pasar de interes a cita sin soporte.'],
                ],
            ],
            'growth_center' => [
                'headline' => 'CRM y continuidad de servicios',
                'copy' => 'Convierte consultas, reservas y seguimiento en una operacion repetible y menos dependiente del chat manual.',
                'journeys' => [
                    ['eyebrow' => 'Agenda', 'title' => 'Confirmar continuidad del servicio', 'copy' => 'Mueve control, rebook y siguientes citas con ownership claro.', 'trigger' => 'booked_service', 'channel' => 'WhatsApp', 'route_key' => 'appointments', 'state' => 'appointments_ready'],
                    ['eyebrow' => 'CRM', 'title' => 'Reactivar clientes dormidos', 'copy' => 'Usa seguimiento antes de perder la relacion.', 'trigger' => 'reactivation', 'channel' => 'Email', 'route_key' => 'customers', 'state' => 'audience_ready'],
                ],
                'cadence_playbooks' => [
                    ['title' => 'Cadencia de rebook', 'cadence' => 'Post-servicio', 'trigger' => 'booked_service', 'channel' => 'CRM', 'copy' => 'Sostiene continuidad y baja no-show.', 'route_key' => 'campaigns', 'state' => 'appointments_ready'],
                    ['title' => 'Cadencia de rescate de prospectos', 'cadence' => '48h', 'trigger' => 'prospect', 'channel' => 'WhatsApp', 'copy' => 'Recupera consultas antes de que el lead se enfrie.', 'route_key' => 'campaigns', 'state' => 'whatsapp_and_leads'],
                ],
            ],
        ],

        'hybrid_general' => [
            'business_type' => BusinessType::HYBRID->value,
            'presentation' => [
                'label' => 'Mixto',
                'description' => 'Para negocios que combinan productos, servicios, agenda o entregas.',
                'examples' => ['Spa con tienda', 'Veterinaria', 'Taller con repuestos', 'Concept store'],
                'teaser' => 'Productos y servicios sin partir tu operacion.',
            ],
            'registration' => [
                'suggested_categories' => ['veterinarias', 'talleres', 'spas', 'servicios-tecnicos'],
            ],
            'capabilities' => [
                'include' => [
                    Capability::PRODUCT_VARIANTS->value,
                    Capability::INVENTORY_TRACKING->value,
                    Capability::PICKUP_ORDERS->value,
                    Capability::DELIVERY_ORDERS->value,
                    Capability::APPOINTMENT_BOOKING->value,
                    Capability::STAFF_CALENDAR->value,
                    Capability::SERVICE_PACKAGES->value,
                ],
                'exclude' => [],
            ],
            'modules' => [
                'business_app' => ['products', 'menu_sections', 'services', 'appointments', 'orders', 'customers'],
                'user_app' => ['directory', 'storefront', 'checkout', 'booking', 'whatsapp'],
            ],
            'dynamic_forms' => [
                'entities' => [
                    ['module' => 'appointments', 'entity_key' => 'service_intake'],
                    ['module' => 'sales', 'entity_key' => 'customer_quote'],
                    ['module' => 'crm', 'entity_key' => 'client_discovery'],
                ],
            ],
            'business_app' => [
                'dashboard_title' => 'Tu centro operativo para negocio mixto',
                'dashboard_copy' => 'Activa productos, servicios, agenda y cobertura sin partir la experiencia del cliente.',
                'workspace_title' => 'Frentes que sostienen la mezcla',
                'workspace_copy' => 'Estos modulos ayudan a que productos, servicios y seguimiento se sientan como una sola operacion.',
                'playbook_copy' => 'Empieza por una oferta principal y conecta despues catalogo, servicios, equipo y seguimiento.',
            ],
            'crm' => [
                'preferred_segments' => ['prospect', 'active', 'booked_service', 'repeat', 'reactivation', 'vip'],
                'templates' => ['fast_follow_up', 'service_rebook', 'close_sale', 'reactivation_recovery'],
                'routing' => [
                    'default' => ['group' => 'sales'],
                ],
                'automation' => [
                    'service_rebook' => ['channel' => 'whatsapp'],
                    'close_sale' => ['channel' => 'email'],
                ],
                'journeys' => [
                    'prospect' => [
                        'journey' => [
                            ['key' => 'hybrid_prospect_fast_follow_up', 'title' => 'Responder y clasificar', 'copy' => 'Aclara si el siguiente paso es compra, reserva o consulta tecnica.', 'template_key' => 'fast_follow_up', 'lane' => 'intake', 'wait_hours' => 0],
                            ['key' => 'hybrid_prospect_close', 'title' => 'Mover al carril correcto', 'copy' => 'No dejes una oportunidad hibrida sin CTA concreto.', 'template_key' => 'close_sale', 'lane' => 'closing', 'wait_hours' => 18],
                        ],
                        'templates' => ['fast_follow_up', 'close_sale', 'service_rebook'],
                    ],
                    'booked_service' => [
                        'journey' => [
                            ['key' => 'hybrid_booked_follow_up', 'title' => 'Cerrar continuidad del servicio', 'copy' => 'Ordena agenda, soporte y proxima accion.', 'template_key' => 'service_rebook', 'lane' => 'continuity', 'wait_hours' => 0],
                            ['key' => 'hybrid_booked_expand', 'title' => 'Abrir venta complementaria', 'copy' => 'Usa el servicio como punto de entrada para productos o paquetes.', 'template_key' => 'close_sale', 'lane' => 'expansion', 'wait_hours' => 24],
                        ],
                        'templates' => ['service_rebook', 'close_sale', 'vip_priority'],
                    ],
                    'reactivation' => [
                        'journey' => [
                            ['key' => 'hybrid_reactivation_recover', 'title' => 'Reactivar contacto hibrido', 'copy' => 'Recupera la relacion con una propuesta util y baja friccion.', 'template_key' => 'reactivation_recovery', 'lane' => 'reactivation', 'wait_hours' => 0],
                            ['key' => 'hybrid_reactivation_last_call', 'title' => 'Cerrar la ventana de rescate', 'copy' => 'Haz un ultimo toque claro para compra, cita o soporte.', 'template_key' => 'follow_up_reminder', 'lane' => 'last_call', 'wait_hours' => 24],
                        ],
                        'templates' => ['reactivation_recovery', 'follow_up_reminder'],
                    ],
                ],
            ],
            'onboarding' => [
                'title' => 'Ordena tu operacion mixta',
                'copy' => 'Activa primero perfil, catalogo y servicios para que el sistema sepa cuando vender, reservar o ambas.',
                'playbook_title' => 'Playbook hibrido de operaciones',
                'recommended_modules' => ['profile', 'catalog', 'services', 'payments'],
                'health_checks' => ['has_profile', 'has_products_or_services'],
                'playbook_steps' => [
                    ['title' => 'Activa una oferta principal', 'copy' => 'Define primero que debe vender o reservar el negocio para no fragmentar la experiencia.'],
                    ['title' => 'Integra productos y servicios', 'copy' => 'Haz que ambos frentes se sientan parte de la misma operacion.'],
                    ['title' => 'Valida el flujo publico completo', 'copy' => 'Comprueba que el cliente entienda si debe comprar, reservar o ambas.'],
                ],
            ],
            'growth_center' => [
                'headline' => 'CRM y crecimiento hibrido',
                'copy' => 'Coordina compra, reserva y seguimiento desde una sola lectura comercial del negocio.',
                'journeys' => [
                    ['eyebrow' => 'Operacion', 'title' => 'Mover oportunidad al carril correcto', 'copy' => 'Clasifica entre pedido, agenda o soporte sin perder conversion.', 'trigger' => 'prospect', 'channel' => 'CRM', 'route_key' => 'customers', 'state' => 'mixed_activity'],
                    ['eyebrow' => 'Continuidad', 'title' => 'Expandir despues del servicio', 'copy' => 'Usa el servicio como puerta a productos o paquetes.', 'trigger' => 'booked_service', 'channel' => 'WhatsApp', 'route_key' => 'campaigns', 'state' => 'mixed_activity'],
                ],
                'cadence_playbooks' => [
                    ['title' => 'Cadencia mixta de cierre', 'cadence' => '48h', 'trigger' => 'prospect', 'channel' => 'CRM', 'copy' => 'Recupera oportunidades que todavia no saben si comprar o reservar.', 'route_key' => 'campaigns', 'state' => 'mixed_activity'],
                    ['title' => 'Cadencia de expansion hibrida', 'cadence' => 'Semanal', 'trigger' => 'repeat', 'channel' => 'WhatsApp', 'copy' => 'Sostiene continuidad entre servicios, productos y seguimiento.', 'route_key' => 'campaigns', 'state' => 'audience_ready'],
                ],
            ],
        ],
    ],
];
