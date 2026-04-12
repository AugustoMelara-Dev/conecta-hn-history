@include('errors.partials.friendly', [
    'status' => '403',
    'title' => 'Acceso denegado',
    'message' => 'No tienes permisos para acceder a este recurso.',
    'hint' => 'Si deberias tener acceso, valida tu cuenta o intenta nuevamente desde el panel principal.',
    'actions' => [
        ['label' => 'Volver al inicio', 'href' => '/'],
        ['label' => 'Ir a login', 'href' => '/login'],
    ],
])
