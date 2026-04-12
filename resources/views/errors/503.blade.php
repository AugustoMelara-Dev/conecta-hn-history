@include('errors.partials.friendly', [
    'status' => '503',
    'title' => 'Servicio no disponible',
    'message' => 'Estamos aplicando mantenimiento o capacidad temporal.',
    'hint' => 'Vuelve a intentar en unos minutos. Si estabas en checkout, tu carrito no se pierde.',
    'actions' => [
        ['label' => 'Volver al inicio', 'href' => '/'],
        ['label' => 'Explorar negocios', 'href' => '/explore'],
    ],
])
