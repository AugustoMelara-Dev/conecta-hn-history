<?php

declare(strict_types=1);

use App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand;
use App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler;
use App\Domain\Billing\DTOs\SubscriptionSwapResult;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Psr\Log\NullLogger;

it('is declared final', function (): void {
    $r = new ReflectionClass(ReconcilePendingSubscriptionsHandler::class);
    expect($r->isFinal())->toBeTrue();
});

it('is declared readonly', function (): void {
    $r = new ReflectionClass(ReconcilePendingSubscriptionsHandler::class);
    expect($r->isReadOnly())->toBeTrue();
});

it('command carries tenantId as a readonly constructor property', function (): void {
    $r = new ReflectionClass(ReconcilePendingSubscriptionsCommand::class);
    $prop = $r->getProperty('tenantId');
    expect($prop->isReadOnly())->toBeTrue()
        ->and($prop->isPublic())->toBeTrue();
});

it('passes tenantId to findPendingOlderThan and stays tenant scoped', function (): void {
    $tenantA = 'tenant-a';

    $repository = new RecordingSubscriptionIntentRepository;
    $gateway = new NullSubscriptionGateway;
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01 12:00:00'));

    $handler = new ReconcilePendingSubscriptionsHandler(
        $gateway,
        new NullLogger,
        $repository,
        $clock,
    );

    $handler->handle(new ReconcilePendingSubscriptionsCommand($tenantA));

    expect($repository->findPendingCalls)->toBe([
        [
            'tenantId' => $tenantA,
            'olderThan' => '2026-01-01 11:00:00',
        ],
    ]);
});

final class RecordingSubscriptionIntentRepository implements SubscriptionIntentRepositoryInterface
{
    /** @var list<array{tenantId: string, olderThan: string}> */
    public array $findPendingCalls = [];

    public function insertOrIgnore(array $attributes): bool
    {
        return true;
    }

    public function findByTenantAndIdempotencyKey(string $tenantId, string $clientIdempotencyKey): ?object
    {
        return null;
    }

    public function updateStatus(string $intentId, array $attributes): void {}

    public function findPendingOlderThan(string $tenantId, string $olderThan): iterable
    {
        $this->findPendingCalls[] = [
            'tenantId' => $tenantId,
            'olderThan' => $olderThan,
        ];

        return [];
    }
}

final class NullSubscriptionGateway implements SubscriptionGatewayInterface
{
    public function swapPlan(string $subscriptionReference, string $newPlanExternalId): SubscriptionSwapResult
    {
        throw new LogicException('swapPlan is not used in this test');
    }

    public function verifyStatus(string $reference): string
    {
        return 'PENDING';
    }

    public function cancel(string $subscriptionReference, bool $immediately = false): bool
    {
        throw new LogicException('cancel is not used in this test');
    }
}

final class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
