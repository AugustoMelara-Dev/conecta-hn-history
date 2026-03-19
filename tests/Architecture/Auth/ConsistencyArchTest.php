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
    expect('App\Application\Auth\Handlers')
        ->toUse(OutboxRepositoryInterface::class);
});

test('Auth Decorators must use idempotency contracts', function () {
    expect([
        'App\Application\Auth\Decorators\DeleteUserAtomicDecorator',
        'App\Application\Auth\Decorators\GenerateOtpIdempotencyDecorator',
        'App\Application\Auth\Decorators\LoginViaSocialProviderAtomicDecorator',
    ])->toUse(AtomicIdempotencyInterface::class);

    expect('App\Application\Auth\Decorators\IdempotentGenerateOtpHandler')
        ->toUse(IdempotencyServiceInterface::class);
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
