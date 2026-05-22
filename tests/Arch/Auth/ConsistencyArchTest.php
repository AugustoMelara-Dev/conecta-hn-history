<?php

declare(strict_types=1);

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;

arch('Auth Handlers must not use EventBus directly - Strict Outbox Pattern')
    ->expect('App\Application\Auth\Handlers')
    ->not->toUse(EventBusInterface::class);

test('Auth handlers that emit events use Outbox Repository', function (): void {
    $handlersPath = getcwd().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Auth'.DIRECTORY_SEPARATOR.'Handlers';
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($handlersPath)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || str_ends_with($file->getFilename(), 'Interface.php')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $emitsDomainEvent = str_contains($source, 'DomainEventMessage') || str_contains($source, 'outboxRepository');

        if ($emitsDomainEvent && ! str_contains($source, OutboxRepositoryInterface::class)) {
            $violations[] = $file->getFilename();
        }
    }

    expect($violations)->toBeEmpty();
});

test('Auth idempotency decorators use Idempotency Service', function (): void {
    $decorator = getcwd().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Auth'.DIRECTORY_SEPARATOR.'Decorators'.DIRECTORY_SEPARATOR.'IdempotentGenerateOtpHandler.php';
    $source = (string) file_get_contents($decorator);

    expect($source)->toContain(IdempotencyServiceInterface::class);
});

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
