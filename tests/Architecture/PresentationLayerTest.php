<?php

$presentationNamespace = [
    App\Http\Controllers\Api\V1\TenantSettingsController::class,
    App\Http\Controllers\Api\V1\NotificationController::class,
    App\Http\Controllers\Webhook\PaymentWebhookController::class,
    App\Http\Controllers\Web\RegistrationController::class,
    App\Http\Controllers\Tenant\ProductController::class,
    App\Http\Controllers\Tenant\OrderController::class,
];

test('presentation layer does not use request or fortify directly in domain layers', function () {
    expect(['App\Application', 'App\Domain'])
        ->not()->toUse([
            'Illuminate\Http\Request',
            'Laravel\Fortify',
        ]);
});

test('presentation layer does not perform blind trust eloquent queries', function () use ($presentationNamespace) {
    expect($presentationNamespace)
        ->not()->toUse('App\Models');
});

test('controllers should be final', function () {
    expect('App\Http\Controllers')
        ->classes()
        ->toBeFinal()
        ->ignoring('App\Http\Controllers\Controller');
});

test('form requests should be final', function () {
    expect('App\Http\Requests')
        ->classes()
        ->toBeFinal();
});
