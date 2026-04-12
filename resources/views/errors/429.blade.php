@include('errors.partials.friendly', [
    'status' => '429',
    'title' => 'Demasiadas solicitudes',
    'message' => 'Detectamos demasiados intentos en poco tiempo.',
    'hint' => 'Espera unos segundos y vuelve a intentar para evitar bloqueos temporales.',
    'actions' => [
        ['label' => 'Volver al inicio', 'href' => '/'],
        ['label' => 'Explorar negocios', 'href' => '/explore'],
    ],
])
