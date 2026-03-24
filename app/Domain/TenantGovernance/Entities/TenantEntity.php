<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Entities;

use App\Domain\Common\Traits\EventSourcedAggregate;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Events\TenantCreated;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
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

    private string $subscriptionStatus = 'inactive';

    private ?\DateTimeImmutable $subscriptionEndsAt = null;

    private ?string $subscriptionReference = null;

    // Profile & Configuration
    private ?string $ownerId = null;

    private ?int $categoryId = null;

    private ?int $cityId = null;

    private BusinessType $businessType = BusinessType::RETAIL;

    private ?string $logoPath = null;

    private ?string $coverPath = null;

    private ?string $whatsapp = null;

    private ?string $phone = null;

    private ?string $address = null;

    private ?string $latitude = null;

    private ?string $longitude = null;

    private bool $isPro = false;

    private int $productLimit = 10;

    private ?string $description = null;

    private ?string $about = null;

    private ?string $slogan = null;

    private ?string $primaryColor = null;

    private ?string $websiteUrl = null;

    private ?string $instagramUrl = null;

    private ?string $facebookUrl = null;

    private ?string $tiktokUrl = null;

    /** @var array<string, mixed>|null */
    private ?array $landingPageData = null;

    /** @var array<string, mixed>|null */
    private ?array $simpleSchedule = null;

    private bool $isAiEnhanced = false;

    private function __construct() {}

    /**
     * Factory: Create a new Tenant.
     * Records TenantCreated event.
     */
    public static function create(string $id, string $name, string $domain): self
    {
        $tenant = new self;

        $event = TenantCreated::now(
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function about(): ?string
    {
        return $this->about;
    }

    public function slug(): string
    {
        return $this->domain;
    }

    public function subscriptionStatus(): string
    {
        return $this->subscriptionStatus;
    }

    public function whatsappNumber(): ?string
    {
        return $this->whatsapp;
    }

    public function logoPath(): ?string
    {
        return $this->logoPath;
    }

    public function coverPath(): ?string
    {
        return $this->coverPath;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function latitude(): ?string
    {
        return $this->latitude;
    }

    public function longitude(): ?string
    {
        return $this->longitude;
    }

    public function primaryColor(): ?string
    {
        return $this->primaryColor;
    }

    public function websiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function instagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function facebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function tiktokUrl(): ?string
    {
        return $this->tiktokUrl;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function landingPageData(): ?array
    {
        return $this->landingPageData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function simpleSchedule(): ?array
    {
        return $this->simpleSchedule;
    }

    public function isPro(): bool
    {
        return $this->isPro;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->isActive && in_array($this->subscriptionStatus, ['active', 'trial'], true);
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

    public function applyAiEnhancement(array $enhancedData, bool $allowReEnhancement = false): void
    {
        if ($this->isAiEnhanced && ! $allowReEnhancement) {
            throw TenantDomainException::alreadyEnhanced();
        }

        if (($this->name === '') && isset($enhancedData['business_name_clean']) && $enhancedData['business_name_clean'] !== '') {
            $this->name = (string) $enhancedData['business_name_clean'];
        }

        if (isset($enhancedData['tagline'])) {
            $this->slogan = (string) $enhancedData['tagline'];
        }

        if (isset($enhancedData['short_description'])) {
            $this->description = (string) $enhancedData['short_description'];
        }

        $this->isAiEnhanced = true;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function setupProfile(
        string $ownerId,
        ?int $categoryId = null,
        ?int $cityId = null,
        ?BusinessType $businessType = null,
        ?string $logoPath = null,
        ?string $whatsapp = null,
        bool $isPro = false,
        int $productLimit = 10
    ): void {
        $this->ownerId = $ownerId;
        $this->categoryId = $categoryId;
        $this->cityId = $cityId;
        $this->businessType = $businessType ?? BusinessType::RETAIL;
        $this->logoPath = $logoPath;
        $this->whatsapp = $whatsapp;
        $this->isPro = $isPro;
        $this->productLimit = $productLimit;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function replaceLogoPath(string $logoPath): void
    {
        $this->logoPath = $logoPath;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function replaceCoverPath(string $coverPath): void
    {
        $this->coverPath = $coverPath;
        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function updateBranding(array $attributes): void
    {
        if (array_key_exists('name', $attributes)) {
            $this->name = $attributes['name'];
        }

        if (array_key_exists('description', $attributes)) {
            $this->description = $attributes['description'];
        }

        if (array_key_exists('about', $attributes)) {
            $this->about = $attributes['about'];
        }

        if (array_key_exists('phone', $attributes)) {
            $this->phone = $attributes['phone'];
        }

        if (array_key_exists('whatsapp', $attributes)) {
            $this->whatsapp = $attributes['whatsapp'];
        }

        if (array_key_exists('address', $attributes)) {
            $this->address = $attributes['address'];
        }

        if (array_key_exists('website_url', $attributes)) {
            $this->websiteUrl = $attributes['website_url'];
        }

        if (array_key_exists('instagram_url', $attributes)) {
            $this->instagramUrl = $attributes['instagram_url'];
        }

        if (array_key_exists('facebook_url', $attributes)) {
            $this->facebookUrl = $attributes['facebook_url'];
        }

        if (array_key_exists('tiktok_url', $attributes)) {
            $this->tiktokUrl = $attributes['tiktok_url'];
        }

        if (array_key_exists('primary_color', $attributes)) {
            $this->primaryColor = $attributes['primary_color'];
        }

        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * @param  array<string, mixed>  $landingPageData
     */
    public function updateLandingPage(array $landingPageData): void
    {
        $this->landingPageData = $landingPageData;
        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function updateSchedule(array $schedule): void
    {
        $this->simpleSchedule = $schedule;
        $this->updatedAt = new DateTimeImmutable;
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
            'subscription_status' => $this->subscriptionStatus,
            'subscription_ends_at' => $this->subscriptionEndsAt?->format(\DateTimeInterface::ATOM),
            'subscription_reference' => $this->subscriptionReference,

            // Profile & Settings
            'owner_id' => $this->ownerId,
            'category_id' => $this->categoryId,
            'city_id' => $this->cityId,
            'business_type' => $this->businessType->value,
            'logo_path' => $this->logoPath,
            'cover_path' => $this->coverPath,
            'whatsapp' => $this->whatsapp,
            'phone' => $this->phone,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_pro' => $this->isPro,
            'product_limit' => $this->productLimit,
            'description' => $this->description,
            'about' => $this->about,
            'slogan' => $this->slogan,
            'primary_color' => $this->primaryColor,
            'website_url' => $this->websiteUrl,
            'instagram_url' => $this->instagramUrl,
            'facebook_url' => $this->facebookUrl,
            'tiktok_url' => $this->tiktokUrl,
            'landing_page_data' => $this->landingPageData,
            'simple_schedule' => $this->simpleSchedule,
            'is_ai_enhanced' => $this->isAiEnhanced,
            'version' => $this->version,
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
        $tenant->subscriptionStatus = isset($state['subscription_status'])
            ? (string) $state['subscription_status']
            : 'inactive';
        $tenant->subscriptionEndsAt = array_key_exists('subscription_ends_at', $state) && $state['subscription_ends_at'] !== null
            ? new DateTimeImmutable($state['subscription_ends_at'])
            : null;
        $tenant->subscriptionReference = isset($state['subscription_reference'])
            ? (string) $state['subscription_reference']
            : null;

        // Profile & Settings
        $tenant->ownerId = isset($state['owner_id']) ? (string) $state['owner_id'] : null;
        $tenant->categoryId = isset($state['category_id']) ? (int) $state['category_id'] : null;
        $tenant->cityId = isset($state['city_id']) ? (int) $state['city_id'] : null;
        $tenant->businessType = isset($state['business_type'])
            ? BusinessType::from((string) $state['business_type'])
            : BusinessType::RETAIL;
        $tenant->logoPath = isset($state['logo_path']) ? (string) $state['logo_path'] : null;
        $tenant->coverPath = isset($state['cover_path']) ? (string) $state['cover_path'] : null;
        $tenant->whatsapp = isset($state['whatsapp']) ? (string) $state['whatsapp'] : null;
        $tenant->phone = isset($state['phone']) ? (string) $state['phone'] : null;
        $tenant->address = isset($state['address']) ? (string) $state['address'] : null;
        $tenant->latitude = isset($state['latitude']) ? (string) $state['latitude'] : null;
        $tenant->longitude = isset($state['longitude']) ? (string) $state['longitude'] : null;
        $tenant->isPro = isset($state['is_pro']) ? (bool) $state['is_pro'] : false;
        $tenant->productLimit = isset($state['product_limit']) ? (int) $state['product_limit'] : 10;
        $tenant->description = isset($state['description']) ? (string) $state['description'] : null;
        $tenant->about = isset($state['about']) ? (string) $state['about'] : null;
        $tenant->slogan = isset($state['slogan']) ? (string) $state['slogan'] : null;
        $tenant->primaryColor = isset($state['primary_color']) ? (string) $state['primary_color'] : null;
        $tenant->websiteUrl = isset($state['website_url']) ? (string) $state['website_url'] : null;
        $tenant->instagramUrl = isset($state['instagram_url']) ? (string) $state['instagram_url'] : null;
        $tenant->facebookUrl = isset($state['facebook_url']) ? (string) $state['facebook_url'] : null;
        $tenant->tiktokUrl = isset($state['tiktok_url']) ? (string) $state['tiktok_url'] : null;
        $tenant->landingPageData = isset($state['landing_page_data']) && is_array($state['landing_page_data'])
            ? $state['landing_page_data']
            : null;
        $tenant->simpleSchedule = isset($state['simple_schedule']) && is_array($state['simple_schedule'])
            ? $state['simple_schedule']
            : null;
        $tenant->isAiEnhanced = isset($state['is_ai_enhanced']) ? (bool) $state['is_ai_enhanced'] : false;

        // Event Sourcing compatibility
        $tenant->version = (int) ($state['version'] ?? 0);

        return $tenant;
    }
}
