<?php

declare(strict_types=1);

it('is declared final', function (): void {
    $r = new \ReflectionClass(\App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler::class);
    expect($r->isFinal())->toBeTrue();
});

it('is declared readonly', function (): void {
    $r = new \ReflectionClass(\App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler::class);
    expect($r->isReadOnly())->toBeTrue();
});

it('command carries tenantId as a readonly constructor property', function (): void {
    $r    = new \ReflectionClass(\App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand::class);
    $prop = $r->getProperty('tenantId');
    expect($prop->isReadOnly())->toBeTrue()
        ->and($prop->isPublic())->toBeTrue();
});

it('passes tenantId to findPendingOlderThan and does not return cross-tenant intents', function (): void {
    $tenantA = 'tenant-a';
    $tenantB = 'tenant-b';

    $repository = Mockery::mock(\App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface::class);
    $repository->shouldReceive('findPendingOlderThan')
        ->once()
        ->with($tenantA, Mockery::type('string'))
        ->andReturn([]);
    $repository->shouldNotReceive('findPendingOlderThan')->with($tenantB, Mockery::any());

    $gateway = Mockery::mock(\App\Domain\Billing\Gateways\SubscriptionGatewayInterface::class);
    $logger  = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();

    $clock = Mockery::mock(\App\Domain\Shared\Contracts\ClockInterface::class);
    $clock->shouldReceive('now')->andReturn(new \DateTimeImmutable());

    $handler = new \App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler(
        $gateway,
        $logger,
        $repository,
        $clock,
    );

    $handler->handle(new \App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand($tenantA));

    Mockery::close();
});
