<?php

$boundaryNamespace = [
    App\Http\Controllers\Api\V1\TenantSettingsController::class,
    App\Http\Controllers\Api\V1\NotificationController::class,
    App\Http\Controllers\Webhook\PaymentWebhookController::class,
    App\Http\Controllers\Web\RegistrationController::class,
    App\Http\Controllers\Tenant\ProductController::class,
    App\Http\Controllers\Tenant\OrderController::class,
];

test('boundary classes are final and keep state encapsulated', function () use ($boundaryNamespace) {
    foreach ($boundaryNamespace as $className) {
        $reflection = new \ReflectionClass($className);

        expect($reflection->isFinal())
            ->toBeTrue("Boundary class {$className} must be final.");

        $declaredProperties = array_filter(
            $reflection->getProperties(),
            fn (\ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $className
                && ! $property->isStatic()
        );

        foreach ($declaredProperties as $property) {
            expect($property->isPublic())
                ->toBeFalse("Boundary state {$className}::\${$property->getName()} must not be public.");
        }
    }
});

test('boundary depends strictly inward', function () use ($boundaryNamespace) {
    expect($boundaryNamespace)
        ->not()->toUse([
            'Illuminate\Support\Facades',
            'App\Models',
            'App\Application\*\Handlers',
        ]);
});
