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

    // Subscription Properties (Billing Bounded Context)
    private ?string $planId = null;

    private ?\DateTimeImmutable $subscriptionEndsAt = null;

    private ?string $subscriptionReference = null;

    private function __construct() {}

    /**
     * Factory: Create a new Tenant.
     * Records TenantCreated event.
     */
    public static function create(string $id, string $name, string $domain): self
    {
        $tenant = new self;

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

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // -- DOMAIN BEHAVIOR --

    /**
     * Upgrade tenant to a new subscription plan.
     *
     * Encapsulates the business logic of plan transition.
     * Handler should "tell" the entity what to do, not "ask" and mutate.
     */
    public function upgradeToPlan(
        string $newPlanId,
        ?\DateTimeImmutable $subscriptionEndsAt = null,
        ?string $subscriptionReference = null
    ): void {
        // Note: In a full Event Sourced model, this would record a PlanUpgraded event.
        // For now, we mutate internal state directly for CRUD compatibility.
        $this->planId = $newPlanId;
        $this->subscriptionEndsAt = $subscriptionEndsAt;
        $this->subscriptionReference = $subscriptionReference;
        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * Get current plan identifier.
     */
    public function planId(): ?string
    {
        return $this->planId ?? null;
    }

    /**
     * Get subscription end date.
     */
    public function subscriptionEndsAt(): ?\DateTimeImmutable
    {
        return $this->subscriptionEndsAt ?? null;
    }

    /**
     * Get subscription reference (external gateway ID).
     */
    public function subscriptionReference(): ?string
    {
        return $this->subscriptionReference ?? null;
    }

    // -- SNAPSHOT COMPATIBILITY (Memento Pattern) --

    /**
     * Serialize entity state for snapshot storage.
     *
     * CRITICAL: Every property MUST be included to prevent data loss.
     * Temporal values use ISO-8601 (ATOM) format for consistency.
     *
     * @return array<string, mixed>
     */
    public function toStateArray(): array
    {
        return [
            // Core Identity
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'is_active' => $this->isActive,

            // Temporal Markers (ISO-8601)
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),

            // Subscription State (Billing Context) - CRITICAL FOR PERSISTENCE
            'plan_id' => $this->planId,
            'subscription_ends_at' => $this->subscriptionEndsAt?->format(\DateTimeInterface::ATOM),
            'subscription_reference' => $this->subscriptionReference,
        ];
    }

    /**
     * Rehydrate entity from snapshot array.
     *
     * CRITICAL: Must restore ALL properties including subscription state.
     * Failure to do so causes "Premium Amnesia" - users silently downgraded.
     *
     * @param  array<string, mixed>  $state
     */
    public static function fromStateArray(array $state): self
    {
        $tenant = new self;

        // Core Identity
        $tenant->id = (string) ($state['id'] ?? '');
        $tenant->name = (string) ($state['name'] ?? '');
        $tenant->domain = (string) ($state['domain'] ?? '');
        $tenant->isActive = (bool) ($state['is_active'] ?? true);

        // Temporal Markers (robust parsing)
        $tenant->createdAt = isset($state['created_at'])
            ? new DateTimeImmutable($state['created_at'])
            : new DateTimeImmutable;
        $tenant->updatedAt = isset($state['updated_at'])
            ? new DateTimeImmutable($state['updated_at'])
            : new DateTimeImmutable;

        // Subscription State (CRITICAL - prevents Premium Amnesia)
        $tenant->planId = isset($state['plan_id']) ? (string) $state['plan_id'] : null;
        $tenant->subscriptionEndsAt = isset($state['subscription_ends_at']) && $state['subscription_ends_at'] !== null
            ? new DateTimeImmutable($state['subscription_ends_at'])
            : null;
        $tenant->subscriptionReference = isset($state['subscription_reference'])
            ? (string) $state['subscription_reference']
            : null;

        // Event Sourcing compatibility
        $tenant->version = (int) ($state['version'] ?? 0);

        return $tenant;
    }
}
