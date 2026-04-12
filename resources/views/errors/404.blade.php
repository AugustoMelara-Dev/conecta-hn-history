@include('errors.partials.friendly', [
    'status' => '404',
    'title' => 'Pagina no encontrada',
    'message' => 'La ruta que buscabas no existe o ya fue movida.',
    'hint' => 'Usa explorar para volver al flujo principal o regresa al inicio.',
    'actions' => [
        ['label' => 'Volver al inicio', 'href' => '/'],
        ['label' => 'Explorar negocios', 'href' => '/explore'],
    ],
])
