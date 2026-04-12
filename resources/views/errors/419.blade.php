@include('errors.partials.friendly', [
    'status' => '419',
    'title' => 'Sesion expirada',
    'message' => 'La solicitud expiro por inactividad o por seguridad.',
    'hint' => 'Recarga la pagina y vuelve a enviar el formulario.',
    'actions' => [
        ['label' => 'Ir a login', 'href' => '/login'],
        ['label' => 'Volver al inicio', 'href' => '/'],
    ],
])
