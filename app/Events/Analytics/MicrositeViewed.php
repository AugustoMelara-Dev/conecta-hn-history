<?php

declare(strict_types=1);

namespace App\Events\Analytics;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class MicrositeViewed implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;

    private string $eventId;

    private DateTimeImmutable $occurredAt;

    public function __construct(
        mixed $tenant,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $tenantKey = (string) ($tenant->id ?? $tenant->tenantId ?? $tenant);

        $this->tenantKey = $tenantKey;
        $this->visitorHash = $ip ? hash('sha256', $tenantKey.'|'.$ip) : null;
        $this->agentHash = $userAgent ? hash('sha256', $userAgent) : null;
        $this->eventId = $eventId ?? (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    public string $tenantKey;

    public ?string $visitorHash;

    public ?string $agentHash;

    public static function fromTenant(mixed $tenant, ?string $ip, ?string $userAgent): self
    {
        $tenantKey = (string) ($tenant->id ?? $tenant->tenantId ?? $tenant);

        return new self($tenantKey, $ip, $userAgent);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantKey);
    }

    public function aggregateId(): string
    {
        return $this->tenantKey;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenantKey,
            'visitor_hash' => $this->visitorHash,
            'agent_hash' => $this->agentHash,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            tenant: (string) $primitives['tenant_id'],
            ip: isset($primitives['visitor_hash']) ? (string) $primitives['visitor_hash'] : null,
            userAgent: isset($primitives['agent_hash']) ? (string) $primitives['agent_hash'] : null,
            eventId: isset($primitives['event_id']) ? (string) $primitives['event_id'] : null,
            occurredAt: isset($primitives['occurred_at']) ? new DateTimeImmutable((string) $primitives['occurred_at']) : null,
        );
    }
}
