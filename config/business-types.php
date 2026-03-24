<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;

return [
    'default' => BusinessType::RETAIL->value,

    'options' => [
        BusinessType::RETAIL->value => [
            'label' => 'Tienda y catálogo',
            'description' => 'Pensado para tiendas de celulares, ferreterías, moda, hogar, repuestos y negocios que operan con inventario.',
            'examples' => ['Tienda de celulares', 'Ferretería', 'Moda', 'Electrónica'],
            'capabilities' => ['Catálogo y variantes', 'Inventario y alertas', 'Pickup y envíos'],
            'suggested_categories' => ['tecnologia', 'moda', 'hogar', 'automotriz'],
        ],
        BusinessType::FOOD->value => [
            'label' => 'Restaurante y comida',
            'description' => 'Ideal para restaurantes, cafeterías, dark kitchens y negocios que venden menú con operación de pedidos.',
            'examples' => ['Restaurante', 'Cafetería', 'Food truck', 'Dark kitchen'],
            'capabilities' => ['Menú y modificadores', 'Pedidos y delivery', 'Operación de cocina'],
            'suggested_categories' => ['restaurantes'],
        ],
        BusinessType::SERVICE->value => [
            'label' => 'Servicios y citas',
            'description' => 'Hecho para consultorías, abogados, clínicas, belleza y negocios donde la agenda importa tanto como la venta.',
            'examples' => ['Consultoría', 'Abogados', 'Clínica', 'Salón de belleza'],
            'capabilities' => ['Servicios y paquetes', 'Reservas y agenda', 'Recordatorios'],
            'suggested_categories' => ['servicios', 'salud', 'belleza'],
        ],
        BusinessType::HYBRID->value => [
            'label' => 'Mixto',
            'description' => 'Para negocios que venden productos y además atienden citas, servicios o entregas desde varias líneas.',
            'examples' => ['Spa con tienda', 'Veterinaria', 'Taller con repuestos', 'Concept store'],
            'capabilities' => ['Catálogo e inventario', 'Servicios y agenda', 'Pickup o delivery'],
            'suggested_categories' => [],
        ],
    ],
];
