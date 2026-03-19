<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand;
use App\Application\Billing\Handlers\ReconcilePendingSubscriptionsHandler;
use App\Domain\Billing\DTOs\SubscriptionSwapResult;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class ReconcilePendingSubscriptionsTest extends TestCase
{
    private FakeSubscriptionGateway $gateway;

    private RecordingSubscriptionIntentRepository $repository;

    private FixedClock $clock;

    private ReconcilePendingSubscriptionsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeSubscriptionGateway;
        $this->repository = new RecordingSubscriptionIntentRepository;
        $this->clock = new FixedClock(new DateTimeImmutable('2026-03-01 12:00:00'));

        $this->handler = new ReconcilePendingSubscriptionsHandler(
            $this->gateway,
            new NullLogger,
            $this->repository,
            $this->clock,
        );
    }

    #[Test]
    public function it_ignores_recent_pending_subscriptions(): void
    {
        $tenantId = 'tenant-123';

        $this->handler->handle(new ReconcilePendingSubscriptionsCommand($tenantId));

        self::assertSame(
            ['tenantId' => $tenantId, 'olderThan' => '2026-03-01 11:00:00'],
            $this->repository->lastPendingQuery,
        );
        self::assertSame([], $this->gateway->verifiedReferences);
        self::assertSame([], $this->repository->updatedStatuses);
    }

    #[Test]
    public function it_reconciles_old_pending_subscriptions_to_paid(): void
    {
        $tenantId = 'tenant-123';

        $this->repository->setPendingIntents([
            $this->makeIntent('intent-1', 'key-1'),
        ]);
        $this->gateway->setStatus('key-1', 'PAID');

        $this->handler->handle(new ReconcilePendingSubscriptionsCommand($tenantId));

        self::assertSame(['key-1'], $this->gateway->verifiedReferences);
        self::assertArrayHasKey('intent-1', $this->repository->updatedStatuses);
        self::assertSame('COMPLETED', $this->repository->updatedStatuses['intent-1']['status']);
        self::assertSame(
            'Auto-reconciled by ReconcilePendingSubscriptionsHandler',
            $this->repository->updatedStatuses['intent-1']['notes'],
        );
    }

    #[Test]
    public function it_reconciles_old_pending_subscriptions_to_failed(): void
    {
        $tenantId = 'tenant-123';

        $this->repository->setPendingIntents([
            $this->makeIntent('intent-2', 'key-2'),
        ]);
        $this->gateway->setStatus('key-2', 'FAILED');

        $this->handler->handle(new ReconcilePendingSubscriptionsCommand($tenantId));

        self::assertSame(['key-2'], $this->gateway->verifiedReferences);
        self::assertArrayHasKey('intent-2', $this->repository->updatedStatuses);
        self::assertSame('FAILED', $this->repository->updatedStatuses['intent-2']['status']);
        self::assertSame(
            'Auto-reconciled by ReconcilePendingSubscriptionsHandler',
            $this->repository->updatedStatuses['intent-2']['notes'],
        );
    }

    /**
     * @return object{id: string, client_idempotency_key: string}
     */
    private function makeIntent(string $id, string $clientIdempotencyKey): object
    {
        return (object) [
            'id' => $id,
            'client_idempotency_key' => $clientIdempotencyKey,
        ];
    }
}

final class FakeSubscriptionGateway implements SubscriptionGatewayInterface
{
    /** @var array<string, string> */
    private array $statuses = [];

    /** @var list<string> */
    public array $verifiedReferences = [];

    public function setStatus(string $reference, string $status): void
    {
        $this->statuses[$reference] = $status;
    }

    public function swapPlan(string $subscriptionReference, string $newPlanExternalId): SubscriptionSwapResult
    {
        return SubscriptionSwapResult::failed('not implemented in test');
    }

    public function verifyStatus(string $reference): string
    {
        $this->verifiedReferences[] = $reference;

        return $this->statuses[$reference] ?? 'PENDING';
    }

    public function cancel(string $subscriptionReference, bool $immediately = false): bool
    {
        return true;
    }
}

final class RecordingSubscriptionIntentRepository implements SubscriptionIntentRepositoryInterface
{
    /**
     * @var list<object{id: string, client_idempotency_key: string}>
     */
    private array $pendingIntents = [];

    /**
     * @var array{tenantId: string, olderThan: string}|null
     */
    public ?array $lastPendingQuery = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $updatedStatuses = [];

    /**
     * @param  list<object{id: string, client_idempotency_key: string}>  $pendingIntents
     */
    public function setPendingIntents(array $pendingIntents): void
    {
        $this->pendingIntents = $pendingIntents;
    }

    public function insertOrIgnore(array $attributes): bool
    {
        return true;
    }

    public function findByTenantAndIdempotencyKey(string $tenantId, string $clientIdempotencyKey): ?object
    {
        return null;
    }

    public function updateStatus(string $intentId, array $attributes): void
    {
        $this->updatedStatuses[$intentId] = $attributes;
    }

    public function findPendingOlderThan(string $tenantId, string $olderThan): iterable
    {
        $this->lastPendingQuery = [
            'tenantId' => $tenantId,
            'olderThan' => $olderThan,
        ];

        return $this->pendingIntents;
    }
}

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
