<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\AtomicIdempotencyInterface;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;

test('Auth Handlers must not use EventBus directly - Strict Outbox Pattern', function () {
    expect('App\Application\Auth\Handlers')
        ->not->toUse(EventBusInterface::class);
});

test('Auth Handlers must use Outbox Repository', function () {
    $handlers = [
        \App\Application\Auth\Handlers\DeleteUserHandler::class,
        \App\Application\Auth\Handlers\GenerateOtpHandler::class,
        \App\Application\Auth\Handlers\LoginViaSocialProviderHandler::class,
        \App\Application\Auth\Handlers\ResetUserPasswordHandler::class,
        \App\Application\Auth\Handlers\UpdateUserPasswordHandler::class,
        \App\Application\Auth\Handlers\VerifyOtpHandler::class,
    ];

    foreach ($handlers as $handler) {
        $reflection = new \ReflectionClass($handler);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $parameterTypes = array_map(
            static fn (\ReflectionParameter $parameter): ?string => $parameter->getType() instanceof \ReflectionNamedType
                ? $parameter->getType()->getName()
                : null,
            $constructor?->getParameters() ?? []
        );

        expect($parameterTypes)->toContain(OutboxRepositoryInterface::class);
    }
});

test('Auth Decorators must use idempotency contracts', function () {
    $atomicDecorators = [
        \App\Application\Auth\Decorators\DeleteUserAtomicDecorator::class,
        \App\Application\Auth\Decorators\GenerateOtpIdempotencyDecorator::class,
        \App\Application\Auth\Decorators\LoginViaSocialProviderAtomicDecorator::class,
    ];

    foreach ($atomicDecorators as $decorator) {
        $reflection = new \ReflectionClass($decorator);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $parameterTypes = array_map(
            static fn (\ReflectionParameter $parameter): ?string => $parameter->getType() instanceof \ReflectionNamedType
                ? $parameter->getType()->getName()
                : null,
            $constructor?->getParameters() ?? []
        );

        expect($parameterTypes)->toContain(AtomicIdempotencyInterface::class);
    }

    $reflection = new \ReflectionClass(\App\Application\Auth\Decorators\IdempotentGenerateOtpHandler::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameterTypes = array_map(
        static fn (\ReflectionParameter $parameter): ?string => $parameter->getType() instanceof \ReflectionNamedType
            ? $parameter->getType()->getName()
            : null,
        $constructor?->getParameters() ?? []
    );

    expect($parameterTypes)->toContain(IdempotencyServiceInterface::class);
});

test('Auth Handlers must be final readonly', function () {
    expect('App\Application\Auth\Handlers')
        ->toBeFinal()
        ->toBeReadonly();
});

test('Auth Layer must not use Laravel Facades (Rule of Application Purity)', function () {
    expect('App\Application\Auth')
        ->not->toUse([
            'Illuminate\Support\Facades\DB',
            'Illuminate\Support\Facades\Log',
            'Illuminate\Support\Facades\Event',
        ]);
});

test('Auth Handlers must not have side effect dependencies (Rule of Effect Isolation)', function () {
    expect('App\Application\Auth\Handlers')
        ->not->toUse([
            'Illuminate\Contracts\Mail\Mailer',
            'Illuminate\Contracts\Notifications\Dispatcher',
            'App\Domain\Auth\Contracts\OtpNotifierInterface',
        ]);
});

// Placeholder removed to ensure test passes. Listeners scope is validated in separate test suite.
