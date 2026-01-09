<?php

declare(strict_types=1);

namespace App\Contracts\Tenancy;

/**
 * CurrentTenant Contract - Single Source of Truth for Tenant Identity
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Interface Segregation for Tenant Context      ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THE PROBLEM WITH DIRECT MODEL USAGE:                                    ║
 * ║  ┌──────────────────┐                                                   ║
 * ║  │ ProductController│──────────────► App\Models\Tenant                  ║
 * ║  └──────────────────┘                     │                             ║
 * ║                                           ├── save()     ❌ Leaked      ║
 * ║                                           ├── delete()   ❌ Leaked      ║
 * ║                                           ├── products() ❌ Leaked      ║
 * ║                                           └── refresh()  ❌ Leaked      ║
 * ║                                                                          ║
 * ║  Controllers gain access to persistence methods they shouldn't use.     ║
 * ║  A developer might accidentally call $tenant->delete() from a view!     ║
 * ║                                                                          ║
 * ║  ─────────────────────────────────────────────────────────────────────── ║
 * ║                                                                          ║
 * ║  THE SOLUTION WITH THIS CONTRACT:                                        ║
 * ║  ┌──────────────────┐       ┌──────────────────────┐                    ║
 * ║  │ ProductController│──────►│  CurrentTenant       │ (Read-Only)        ║
 * ║  └──────────────────┘       └──────────────────────┘                    ║
 * ║                                     ▲                                    ║
 * ║                                     │ implements                         ║
 * ║                              ┌──────┴───────┐                           ║
 * ║                              │ Tenant Model │ (hidden behind interface) ║
 * ║                              └──────────────┘                           ║
 * ║                                                                          ║
 * ║  Controllers can ONLY read tenant data, never mutate it accidentally.   ║
 * ║                                                                          ║
 * ║  BINDING (in TenancyServiceProvider):                                   ║
 * ║    $this->app->bind(CurrentTenant::class, fn($app) =>                  ║
 * ║        $app->make('currentTenant') ?? Tenant::findOrFail(request()...)  ║
 * ║    );                                                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE in Controllers:
 *   public function __construct(private CurrentTenant $tenant) {}
 *   public function index()
 *   {
 *       if ($this->tenant->isPro()) { ... }
 *       $config = $this->tenant->getFeatureConfig('whatsapp');
 *   }
 */
interface CurrentTenant
{
    /**
     * Get the tenant's unique identifier.
     */
    public function getId(): int;

    /**
     * Get the tenant's URL slug.
     */
    public function getSlug(): string;

    /**
     * Get the tenant's display name.
     */
    public function getName(): string;

    /**
     * Check if the tenant has an active PRO subscription.
     */
    public function isPro(): bool;

    /**
     * Check if a specific feature is enabled for this tenant.
     *
     * @param  string  $feature  Feature key (e.g., 'whatsapp', 'inventory', 'coupons')
     */
    public function hasFeature(string $feature): bool;

    /**
     * Get a specific configuration value for this tenant.
     *
     * @param  string  $key  Config key (e.g., 'brand_color', 'invoice_prefix')
     * @param  mixed  $default  Default value if key doesn't exist
     */
    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * Get the tenant's configured payment provider.
     *
     * @return string Provider identifier ('stripe', 'pixelpay', 'paypal')
     */
    public function getPaymentProvider(): string;

    /**
     * Get the tenant's configured currency.
     *
     * @return string ISO 4217 currency code ('HNL', 'USD')
     */
    public function getCurrency(): string;

    /**
     * Get the tenant's product limit.
     */
    public function getProductLimit(): int;

    /**
     * Get remaining product slots available.
     */
    public function getRemainingProductSlots(): int;

    /**
     * Check if the tenant can create more products.
     */
    public function canCreateProducts(): bool;

    /**
     * Get the tenant's timezone.
     */
    public function getTimezone(): string;

    /**
     * Get the tenant's locale for formatting.
     */
    public function getLocale(): string;

    /**
     * Get the tenant's logo URL.
     */
    public function getLogoUrl(): ?string;

    /**
     * Get the tenant's brand color.
     */
    public function getBrandColor(): string;

    /**
     * Check if tenant is currently active (not suspended).
     */
    public function isActive(): bool;

    /**
     * Get the base URL for this tenant's microsite.
     */
    public function getMicrositeUrl(): string;
}
