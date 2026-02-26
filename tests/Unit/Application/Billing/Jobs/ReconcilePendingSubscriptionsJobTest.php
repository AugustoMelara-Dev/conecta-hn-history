<?php

declare(strict_types=1);

it('is declared final', function (): void {
    $r = new \ReflectionClass(\App\Application\Billing\Jobs\ReconcilePendingSubscriptionsJob::class);
    expect($r->isFinal())->toBeTrue();
});

it('carries tenantId as a readonly constructor property', function (): void {
    $r = new \ReflectionClass(\App\Application\Billing\Jobs\ReconcilePendingSubscriptionsJob::class);
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
    $logger  = Mockery::mock(\Psr\Log\LoggerInterface::class);

    $job = new \App\Application\Billing\Jobs\ReconcilePendingSubscriptionsJob($tenantA);
    $job->handle($gateway, $logger, $repository);
});
