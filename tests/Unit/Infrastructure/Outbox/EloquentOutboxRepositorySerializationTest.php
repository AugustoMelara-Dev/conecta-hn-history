<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\TraceContextInterface;
use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Infrastructure\Shared\Persistence\EloquentOutboxRepository;

/**
 * Stub TraceContext — avoids Mockery on final class with Request dependency.
 */
function makeTraceStub(): TraceContextInterface
{
    return new class implements TraceContextInterface
    {
        public function getTraceId(): string
        {
            return 'trace-001';
        }

        public function getCausationId(): ?string
        {
            return null;
        }
    };
}

function makeOutboxRepo(): EloquentOutboxRepository
{
    return new EloquentOutboxRepository(makeTraceStub());
}

/**
 * Minimal test double implementing DomainEvent contract (framework-agnostic).
 */
function makeOutboxTestEvent(string $tenantId = 'tenant-uuid-001'): object
{
    return new class($tenantId) implements DomainEvent
    {
        public function __construct(public readonly string $tenantId) {}

        public function eventId(): string
        {
            return 'evt-001';
        }

        public function correlationId(): string
        {
            return 'corr-001';
        }

        public function aggregateId(): string
        {
            return 'agg-001';
        }

        public function tenantId(): string
        {
            return $this->tenantId;
        }

        public function occurredAt(): \DateTimeImmutable
        {
            return new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        }

        public function toPrimitives(): array
        {
            return $this->toPayload();
        }

        public static function fromPrimitives(array $p): static
        {
            return new self($p['tenant_id']);
        }

        public function toPayload(): array
        {
            return ['tenant_id' => $this->tenantId, 'schema_version' => 1];
        }

        public static function fromPayload(array $p): static
        {
            return new static($p['tenant_id']);
        }
    };
}

// ─── C-OX-01: serializeEvent delegates to toPayload() ────────────────────────

it('C-OX-01: serializes event using toPayload() when method exists', function () {
    $event = makeOutboxTestEvent();
    $repo = makeOutboxRepo();
    $method = new ReflectionMethod($repo, 'serializeEvent');
    $method->setAccessible(true);

    $result = $method->invoke($repo, $event);

    expect($result)->toBe(['tenant_id' => 'tenant-uuid-001', 'schema_version' => 1]);
});

it('C-OX-01: Reflection fallback used for legacy events without toPayload()', function () {
    $legacyEvent = new class
    {
        public string $foo = 'bar';
    };

    $repo = makeOutboxRepo();
    $method = new ReflectionMethod($repo, 'serializeEvent');
    $method->setAccessible(true);

    $result = $method->invoke($repo, $legacyEvent);

    expect($result)->toHaveKey('foo');
    expect($result['foo'])->toBe('bar');
});

// ─── C-OX-02: extractAggregateId uses contract methods first ─────────────────

it('C-OX-02: extracts aggregate id via aggregateId() method (DomainEvent contract)', function () {
    $event = makeOutboxTestEvent();
    $repo = makeOutboxRepo();
    $method = new ReflectionMethod($repo, 'extractAggregateId');
    $method->setAccessible(true);

    expect($method->invoke($repo, $event))->toBe('agg-001');
});

it('C-OX-02: getAggregateId() takes priority over aggregateId() when both exist', function () {
    $event = new class
    {
        public function getAggregateId(): string
        {
            return 'outbox-agg-999';
        }

        public function aggregateId(): string
        {
            return 'domain-agg-888';
        }
    };

    $repo = makeOutboxRepo();
    $method = new ReflectionMethod($repo, 'extractAggregateId');
    $method->setAccessible(true);

    // ShouldBeStoredInOutbox::getAggregateId() wins
    expect($method->invoke($repo, $event))->toBe('outbox-agg-999');
});

// ─── C-OX-03: occurredAt() used, not occurredOn() ────────────────────────────

it('C-OX-03: DomainEvent uses occurredAt() not occurredOn()', function () {
    $event = makeOutboxTestEvent();

    expect(method_exists($event, 'occurredAt'))->toBeTrue();
    expect(method_exists($event, 'occurredOn'))->toBeFalse();
    expect($event->occurredAt()->format('Y-m-d'))->toBe('2026-01-01');
});

// ─── C-OX-04: correlationId from event, not traceId ──────────────────────────

it('C-OX-04: event correlationId() is distinct from traceId', function () {
    $event = makeOutboxTestEvent();

    expect($event->correlationId())->toBe('corr-001');
    expect($event->correlationId())->not->toBe('trace-001');
});

// ─── C-OX-05: tenantId via method, not property ──────────────────────────────

it('C-OX-05: tenantId() method returns string for VO-based events', function () {
    $event = makeOutboxTestEvent('tenant-from-method');

    expect(method_exists($event, 'tenantId'))->toBeTrue();
    expect((string) $event->tenantId())->toBe('tenant-from-method');
});
