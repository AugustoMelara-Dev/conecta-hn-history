<?php

declare(strict_types=1);

use App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand;
use App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->gateway    = Mockery::mock(SubscriptionGatewayInterface::class);
    $this->logger     = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    $this->repository = Mockery::mock(SubscriptionIntentRepositoryInterface::class);
    $this->clock      = Mockery::mock(ClockInterface::class);
});

afterEach(function () {
    Mockery::close();
});

it('ignores recent pending subscriptions', function () {
    $tenantId = 'tenant-123';

    $date      = new \DateTimeImmutable('2026-03-01 12:00:00');
    $threshold = $date->modify('-1 hour')->format('Y-m-d H:i:s');

    $this->clock->shouldReceive('now')->once()->andReturn($date);

    $this->repository
        ->shouldReceive('findPendingOlderThan')
        ->once()
        ->with($tenantId, $threshold)
        ->andReturn([]);

    $this->gateway->shouldReceive('verifyStatus')->never();
    $this->repository->shouldReceive('updateStatus')->never();

    $handler = new ReconcilePendingSubscriptionsHandler(
        $this->gateway,
        $this->logger,
        $this->repository,
        $this->clock,
    );

    $handler->handle(new ReconcilePendingSubscriptionsCommand($tenantId));
});

it('reconciles old pending subscriptions to paid', function () {
    $tenantId = 'tenant-123';

    $date      = new \DateTimeImmutable('2026-03-01 12:00:00');
    $threshold = $date->modify('-1 hour')->format('Y-m-d H:i:s');

    $intent = (object) [
        'id'                     => 'intent-1',
        'client_idempotency_key' => 'key-1',
    ];

    $this->clock->shouldReceive('now')->once()->andReturn($date);

    $this->repository
        ->shouldReceive('findPendingOlderThan')
        ->once()
        ->with($tenantId, $threshold)
        ->andReturn([$intent]);

    $this->gateway
        ->shouldReceive('verifyStatus')
        ->once()
        ->with('key-1')
        ->andReturn('PAID');

    $this->repository
        ->shouldReceive('updateStatus')
        ->once()
        ->with('intent-1', Mockery::type('array'));

    $handler = new ReconcilePendingSubscriptionsHandler(
        $this->gateway,
        $this->logger,
        $this->repository,
        $this->clock,
    );

    $handler->handle(new ReconcilePendingSubscriptionsCommand($tenantId));
});

it('reconciles old pending subscriptions to failed', function () {
    $tenantId = 'tenant-123';

    $date      = new \DateTimeImmutable('2026-03-01 12:00:00');
    $threshold = $date->modify('-1 hour')->format('Y-m-d H:i:s');

    $intent = (object) [
        'id'                     => 'intent-2',
        'client_idempotency_key' => 'key-2',
    ];

    $this->clock->shouldReceive('now')->once()->andReturn($date);

    $this->repository
        ->shouldReceive('findPendingOlderThan')
        ->once()
        ->with($tenantId, $threshold)
        ->andReturn([$intent]);

    $this->gateway
        ->shouldReceive('verifyStatus')
        ->once()
        ->with('key-2')
        ->andReturn('FAILED');

    $this->repository
        ->shouldReceive('updateStatus')
        ->once()
        ->with('intent-2', Mockery::type('array'));

    $handler = new ReconcilePendingSubscriptionsHandler(
        $this->gateway,
        $this->logger,
        $this->repository,
        $this->clock,
    );

    $handler->handle(new ReconcilePendingSubscriptionsCommand($tenantId));
});
