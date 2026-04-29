@include('errors.partials.friendly', [
    'status' => '401',
    'title' => 'Necesitas iniciar sesión',
    'message' => 'Esta sección requiere autenticación para continuar.',
    'hint' => 'Inicia sesión y vuelve a intentar. Si ya estabas autenticado, actualiza la página.',
    'actions' => [
        ['label' => 'Iniciar sesión', 'href' => '/login'],
        ['label' => 'Volver al inicio', 'href' => '/'],
    ],
])
