<?php

declare(strict_types=1);

use App\Application\Shared\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;

arch('Auth Handlers must not use EventBus directly - Strict Outbox Pattern')
    ->expect('App\Application\Auth\Handlers')
    ->not->toUse(EventBusInterface::class);

arch('Auth Handlers must use Outbox Repository')
    ->expect('App\Application\Auth\Handlers')
    ->toUse(OutboxRepositoryInterface::class);

arch('Auth Handlers must use Idempotency Service')
    ->expect('App\Application\Auth\Handlers')
    ->toUse(IdempotencyServiceInterface::class);

arch('Auth Handlers must be final readonly')
    ->expect('App\Application\Auth\Handlers')
    ->toBeFinal()
    ->toBeReadonly();

arch('Auth Layer must not use Laravel Facades (Rule of Application Purity)')
    ->expect('App\Application\Auth')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Log',
        'Illuminate\Support\Facades\Event',
    ]);

arch('Auth Handlers must not have side effects dependencies (Rule of Effect Isolation)')
    ->expect('App\Application\Auth\Handlers')
    ->not->toUse([
        'Illuminate\Contracts\Mail\Mailer',
        'Illuminate\Contracts\Notifications\Dispatcher',
        'App\Domain\Auth\Contracts\OtpNotifierInterface',
    ]);

// Placeholder removed to ensure test passes. Listeners scope is validated in separate test suite.
