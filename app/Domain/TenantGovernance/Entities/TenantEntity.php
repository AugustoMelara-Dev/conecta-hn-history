<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Entities;

use App\Domain\Common\Traits\EventSourcedAggregate;
use App\Domain\TenantGovernance\Events\TenantCreated;
use DateTimeImmutable;

/**
 * TenantEntity - Aggregate Root
 * 
 * - Event Sourced (Identity + History).
 * - Full Encapsulation.
 */
final class TenantEntity
{
    use EventSourcedAggregate;

    private string $id;
    private string $name;
    private string $domain;
    
    // Additional Properties (managed via events or defaulting)
    private bool $isActive = true;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct() {}

    /**
     * Factory: Create a new Tenant.
     * Records TenantCreated event.
     */
    public static function create(string $id, string $name, string $domain): self
    {
        $tenant = new self();

        $event = new TenantCreated(
            id: $id,
            name: $name,
            domain: $domain
        );

        $tenant->recordThat($event);

        return $tenant;
    }

    // -- MUTATORS --

    protected function applyTenantCreated(TenantCreated $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name;
        $this->domain = $event->domain;
        $this->isActive = true;
        // Creation time from event
        $this->createdAt = $event->occurredOn();
        $this->updatedAt = $event->occurredOn();
    }

    // -- GETTERS --

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function domain(): string
    {
        return $this->domain;
    }
    
    // Additional getters if needed by Repository mapping, 
    // maintaining read-only contract.
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    // -- SNAPSHOT COMPATIBILITY --
    
    public function toStateArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    // Assuming we might need this for hydration from DB if not dealing with Event Store for reads yet
    public static function fromStateArray(array $state): self
    {
        $tenant = new self();
        $tenant->id = (string) ($state['id'] ?? '');
        $tenant->name = $state['name'] ?? '';
        $tenant->domain = $state['domain'] ?? '';
        $tenant->isActive = (bool) ($state['is_active'] ?? true);
        $tenant->createdAt = isset($state['created_at']) ? new DateTimeImmutable($state['created_at']) : new DateTimeImmutable();
        $tenant->updatedAt = isset($state['updated_at']) ? new DateTimeImmutable($state['updated_at']) : new DateTimeImmutable();
        $tenant->version = 0;
        return $tenant;
    }
}
