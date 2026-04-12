@include('errors.partials.friendly', [
    'status' => '500',
    'title' => 'Error interno del servidor',
    'message' => 'Tuvimos un problema inesperado al procesar tu solicitud.',
    'hint' => 'Nuestro equipo fue notificado. Puedes intentar nuevamente en unos segundos.',
    'actions' => [
        ['label' => 'Intentar de nuevo', 'href' => '/'],
        ['label' => 'Explorar negocios', 'href' => '/explore'],
    ],
])
