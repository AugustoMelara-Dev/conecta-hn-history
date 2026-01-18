<?php

declare(strict_types=1);

use App\Application\Auth\Handlers\GenerateOtpHandler;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;
use Illuminate\Support\Facades\Log;

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

arch('Auth Commands must be final readonly')
    ->expect('App\Application\Auth\Commands')
    ->toBeFinal()
    ->toBeReadonly();

arch('Auth Layer must not use Laravel Facades')
    ->expect('App\Application\Auth')
    ->not->toUse('Illuminate\Support\Facades');

arch('Auth Handlers must not use OtpNotifier directly (Side Effect Isolation)')
    ->expect('App\Application\Auth\Handlers')
    ->not->toUse('App\Domain\Auth\Contracts\OtpNotifierInterface');
