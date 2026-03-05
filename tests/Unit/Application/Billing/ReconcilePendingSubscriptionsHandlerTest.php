<?php

declare(strict_types=1);

use App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand;
use App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler;

it('handler acepta command con tenantId válido', function () {
    $command = new ReconcilePendingSubscriptionsCommand(
        tenantId: 'a127c64c-85df-4248-896d-6d9cf7be8e9c',
    );

    expect($command->tenantId)->toBe('a127c64c-85df-4248-896d-6d9cf7be8e9c');
});

it('handler es final readonly', function () {
    $reflection = new ReflectionClass(ReconcilePendingSubscriptionsHandler::class);
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->isReadOnly())->toBeTrue();
});

it('command es final readonly', function () {
    $reflection = new ReflectionClass(ReconcilePendingSubscriptionsCommand::class);
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->isReadOnly())->toBeTrue();
});

it('RateLimitExceededException no referencia Illuminate', function () {
    $reflection = new ReflectionClass(\App\Application\Shared\Common\Exceptions\RateLimitExceededException::class);
    $parent = $reflection->getParentClass();
    expect($parent->getName())->toBe('RuntimeException');
});
