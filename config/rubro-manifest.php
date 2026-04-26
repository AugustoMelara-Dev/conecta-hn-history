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
                'entities' => [],
            ],
            'onboarding' => [
                'title' => 'Activa tu tienda digital',
                'copy' => 'Empieza por perfil, catalogo y formas de cobro para que el directorio entienda que vendes.',
                'playbook_title' => 'Playbook de ventas por catalogo',
                'recommended_modules' => ['profile', 'catalog', 'payments', 'whatsapp'],
                'health_checks' => ['has_profile', 'has_products', 'has_payment_method'],
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
                'entities' => [],
            ],
            'onboarding' => [
                'title' => 'Publica tu menu y prepara pedidos',
                'copy' => 'Configura menu, canales de pedido y pagos para que cada orden llegue clara al equipo.',
                'playbook_title' => 'Playbook operativo de restaurante',
                'recommended_modules' => ['profile', 'menu', 'orders', 'payments'],
                'health_checks' => ['has_profile', 'has_menu_items', 'has_order_channel'],
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
                ],
            ],
            'onboarding' => [
                'title' => 'Activa tus servicios reservables',
                'copy' => 'Crea servicios, disponibilidad y equipo para que el cliente pueda reservar sin escribirte diez veces.',
                'playbook_title' => 'Playbook de agenda y seguimiento',
                'recommended_modules' => ['profile', 'services', 'staff', 'booking'],
                'health_checks' => ['has_profile', 'has_services', 'has_provider'],
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
                'entities' => [],
            ],
            'onboarding' => [
                'title' => 'Ordena tu operacion mixta',
                'copy' => 'Activa primero perfil, catalogo y servicios para que el sistema sepa cuando vender, reservar o ambas.',
                'playbook_title' => 'Playbook hibrido de operaciones',
                'recommended_modules' => ['profile', 'catalog', 'services', 'payments'],
                'health_checks' => ['has_profile', 'has_products_or_services'],
            ],
        ],
    ],
];
