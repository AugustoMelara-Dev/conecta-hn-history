@include('errors.partials.friendly', [
    'status' => '401',
    'title' => 'Necesitas iniciar sesion',
    'message' => 'Esta seccion requiere autenticacion para continuar.',
    'hint' => 'Inicia sesion y vuelve a intentar. Si ya estabas autenticado, actualiza la pagina.',
    'actions' => [
        ['label' => 'Iniciar sesion', 'href' => '/login'],
        ['label' => 'Volver al inicio', 'href' => '/'],
    ],
])
