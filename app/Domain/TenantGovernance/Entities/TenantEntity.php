<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Entities;

use App\Domain\Common\Traits\EventSourcedAggregate;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\CommercialMode;
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

    private ?string $subscriptionStatus = null;

    private ?string $planType = null;

    private bool $isPro = false;

    private ?int $productLimit = null;

    private ?string $logoPath = null;

    private ?int $categoryId = null;

    private ?int $cityId = null;

    private ?string $whatsapp = null;

    private BusinessType $businessType = BusinessType::RETAIL;

    private CommercialMode $commercialMode = CommercialMode::PRODUCTS;

    private ?string $rubroKey = null;

    private ?string $ownerUserId = null;

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

    public function configureCommercialProfile(
        string $planType,
        bool $isPro,
        int $productLimit,
        ?string $logoPath,
        ?int $categoryId,
        ?int $cityId,
        ?string $whatsapp,
        BusinessType $businessType,
        CommercialMode $commercialMode,
        ?string $rubroKey,
        ?string $ownerUserId = null
    ): void {
        $this->planType = $planType;
        $this->isPro = $isPro;
        $this->productLimit = $productLimit;
        $this->logoPath = $logoPath;
        $this->categoryId = $categoryId;
        $this->cityId = $cityId;
        $this->whatsapp = $whatsapp;
        $this->businessType = $businessType;
        $this->commercialMode = $commercialMode;
        $this->rubroKey = $rubroKey;
        $this->ownerUserId = $ownerUserId;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function planType(): ?string
    {
        return $this->planType;
    }

    public function isPro(): bool
    {
        return $this->isPro;
    }

    public function productLimit(): ?int
    {
        return $this->productLimit;
    }

    public function logoPath(): ?string
    {
        return $this->logoPath;
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function cityId(): ?int
    {
        return $this->cityId;
    }

    public function whatsapp(): ?string
    {
        return $this->whatsapp;
    }

    public function businessType(): BusinessType
    {
        return $this->businessType;
    }

    public function commercialMode(): CommercialMode
    {
        return $this->commercialMode;
    }

    public function rubroKey(): ?string
    {
        return $this->rubroKey;
    }

    public function ownerUserId(): ?string
    {
        return $this->ownerUserId;
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

    public function subscriptionStatus(): ?string
    {
        return $this->subscriptionStatus;
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
            'subscription_status' => $this->subscriptionStatus,
            'plan_type' => $this->planType,
            'is_pro' => $this->isPro,
            'product_limit' => $this->productLimit,
            'logo_path' => $this->logoPath,
            'category_id' => $this->categoryId,
            'city_id' => $this->cityId,
            'whatsapp_number' => $this->whatsapp,
            'business_type' => $this->businessType->value,
            'commercial_mode' => $this->commercialMode->value,
            'rubro_key' => $this->rubroKey,
            'user_id' => $this->ownerUserId,
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
        $tenant->subscriptionEndsAt = isset($state['subscription_ends_at'])
            ? new DateTimeImmutable($state['subscription_ends_at'])
            : null;
        $tenant->subscriptionReference = isset($state['subscription_reference'])
            ? (string) $state['subscription_reference']
            : null;
        $tenant->subscriptionStatus = isset($state['subscription_status'])
            ? (string) $state['subscription_status']
            : null;
        $tenant->planType = isset($state['plan_type']) ? (string) $state['plan_type'] : null;
        $tenant->isPro = (bool) ($state['is_pro'] ?? false);
        $tenant->productLimit = isset($state['product_limit']) ? (int) $state['product_limit'] : null;
        $tenant->logoPath = isset($state['logo_path']) ? (string) $state['logo_path'] : null;
        $tenant->categoryId = isset($state['category_id']) ? (int) $state['category_id'] : null;
        $tenant->cityId = isset($state['city_id']) ? (int) $state['city_id'] : null;
        $tenant->whatsapp = isset($state['whatsapp_number']) ? (string) $state['whatsapp_number'] : null;
        $tenant->businessType = BusinessType::tryFrom((string) ($state['business_type'] ?? '')) ?? BusinessType::RETAIL;
        $tenant->commercialMode = CommercialMode::safeFrom(
            isset($state['commercial_mode']) ? (string) $state['commercial_mode'] : null,
            $tenant->businessType
        );
        $tenant->rubroKey = isset($state['rubro_key']) ? (string) $state['rubro_key'] : null;
        $tenant->ownerUserId = isset($state['user_id']) ? (string) $state['user_id'] : null;

        // Event Sourcing compatibility
        $tenant->version = (int) ($state['version'] ?? 0);

        return $tenant;
    }
}
