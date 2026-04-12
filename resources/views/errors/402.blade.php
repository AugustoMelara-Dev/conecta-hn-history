@include('errors.partials.friendly', [
    'status' => '402',
    'title' => 'Pago requerido',
    'message' => 'Tu plan o pago actual no permite completar esta accion.',
    'hint' => 'Revisa tu suscripcion o intenta con otro metodo de pago.',
    'actions' => [
        ['label' => 'Ir al inicio', 'href' => '/'],
        ['label' => 'Explorar negocios', 'href' => '/explore'],
    ],
])
