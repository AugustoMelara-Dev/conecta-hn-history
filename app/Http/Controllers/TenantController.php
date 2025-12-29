<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TenantController - Microsite with SaaS Tier Feature Flagging
 *
 * Responsabilidad: Renderizar micrositios con datos transformados
 * según el plan del tenant (semilla vs pro vs enterprise).
 *
 * SECURITY: El backend actúa como guardián - no enviamos datos de features
 * por las que el tenant no ha pagado.
 */
class TenantController extends Controller
{
    /**
     * The tenant cache manager instance.
     */
    protected TenantCacheManager $cacheManager;

    /**
     * Plan tiers mapping
     */
    private const PLAN_SEMILLA = 'semilla';

    private const PLAN_PRO = 'pro';

    private const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Display the tenant microsite / digital storefront.
     */
    public function show(Tenant $tenant): Response
    {
        // ═══════════════════════════════════════════════════════════════
        // GATEKEEPER: Multi-layer validation for tenant visibility
        // A tenant must pass ALL checks to be publicly visible
        // ═══════════════════════════════════════════════════════════════

        // Layer 1: Administrative approval
        if (! $tenant->is_active) {
            abort(404, 'Este negocio no está disponible.');
        }

        // Layer 2: Subscription status (no morosos in public directory)
        if (! in_array($tenant->subscription_status, ['active', 'trial'])) {
            abort(404, 'Este negocio no está disponible.');
        }

        // Determine tenant plan based on is_pro flag
        $tenantPlan = $this->determineTenantPlan($tenant);

        // ═══════════════════════════════════════════════════════════════
        // DATA AWARENESS: Count total products BEFORE applying limit
        // This enables the Soft Gate UX pattern for upselling
        // ═══════════════════════════════════════════════════════════════
        $totalProductsCount = $tenant->products()
            ->where('is_visible', true)
            ->count();

        // Eager load relationships - conditionally load modifiers for Pro
        $this->loadTenantRelations($tenant, $tenantPlan);

        // Transform tenant for frontend
        $tenantData = $this->transformTenant($tenant, $tenantPlan);

        // Transform products with feature-flagged modifiers
        $products = $this->transformProducts($tenant->products, $tenantPlan, $tenant);

        // Calculate hidden products for Soft Gate UX
        $visibleProductsCount = count($products);
        $hasHiddenProducts = ($tenantPlan === self::PLAN_SEMILLA)
            && ($totalProductsCount > self::SEMILLA_PRODUCT_LIMIT);
        $hiddenProductsCount = max(0, $totalProductsCount - $visibleProductsCount);

        // ═══════════════════════════════════════════════════════════════
        // ROLE AWARENESS: Detect if viewer is the tenant owner
        // This enables context-aware UpsellCard messaging
        // SECURITY: This check MUST happen server-side, never trust client
        // ═══════════════════════════════════════════════════════════════
        $isOwner = false;
        if (auth()->check()) {
            $isOwner = auth()->id() === $tenant->user_id;
        }

        return Inertia::render('Tenant/Show', [
            // SaaS Tier - CRITICAL: Frontend uses this for conditional rendering
            'tenantPlan' => $tenantPlan,

            // Main tenant object
            'tenant' => $tenantData,

            // Products as separate prop (component expects both tenant.products AND products)
            'products' => $products,

            // ═══════════════════════════════════════════════════════════════
            // SOFT GATE: Data awareness for UpsellCard
            // SECURITY: Only send counts, never actual hidden product data
            // ═══════════════════════════════════════════════════════════════
            'hasHiddenProducts' => $hasHiddenProducts,
            'totalProductsCount' => $totalProductsCount,
            'hiddenProductsCount' => $hiddenProductsCount,

            // ═══════════════════════════════════════════════════════════════
            // ROLE AWARENESS: Is the viewer the owner of this tenant?
            // Enables role-specific messaging in UpsellCard
            // ═══════════════════════════════════════════════════════════════
            'isOwner' => $isOwner,

            // Social features (mock for now since tables may not exist)
            'isFollowing' => false,
            'followersCount' => rand(50, 500), // Mock

            // Business hours (hardcoded since table doesn't exist)
            'isOpen' => true, // Always open to prevent cart blocking
            'openingHours' => $this->getMockedOpeningHours(),

            // SEO Structured Data for Google
            'seoStructuredData' => $this->buildStructuredData($tenant),
        ]);
    }

    /**
     * Determine the tenant's subscription plan.
     *
     * Business Logic:
     * - is_pro = false -> 'semilla' (Free tier, stateless WhatsApp)
     * - is_pro = true  -> 'pro' (Paid tier, stateful cart)
     * - Future: could add 'enterprise' based on additional flags
     */
    private function determineTenantPlan(Tenant $tenant): string
    {
        // TODO: In future, check for enterprise flag or subscription level
        // For now, simple binary: is_pro determines pro vs semilla
        return $tenant->is_pro ? self::PLAN_PRO : self::PLAN_SEMILLA;
    }

    /**
     * Load tenant relations based on plan tier.
     *
     * SECURITY: Only Pro/Enterprise load modifier data.
     * Semilla tenants get products without modifiers to prevent data leakage.
     */
    /**
     * Maximum products for Semilla (free) tier
     * This is a hard limit enforced at database level
     */
    private const SEMILLA_PRODUCT_LIMIT = 12;

    private function loadTenantRelations(Tenant $tenant, string $plan): void
    {
        // Base product query - limit for Semilla plan
        $productQuery = function ($query) use ($plan) {
            $query->where('is_visible', true)->orderBy('name');

            // HARD LIMIT: Semilla only gets 12 products from DB
            if ($plan === self::PLAN_SEMILLA) {
                $query->limit(self::SEMILLA_PRODUCT_LIMIT);
            }
        };

        $relations = [
            'city:id,name,slug',
            'category:id,name,slug,icon',
            'products' => $productQuery,
            'menuSections' => function ($query) {
                $query->where('is_active', true)->orderBy('name');
            },
        ];

        // Pro/Enterprise: Eager load modifier groups and options
        if ($plan !== self::PLAN_SEMILLA) {
            $relations['products.modifierGroups'] = function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            };
            $relations['products.modifierGroups.options'] = function ($query) {
                $query->where('is_available', true)->orderBy('sort_order');
            };
        }

        $tenant->load($relations);
    }

    /**
     * Transform tenant model to frontend contract.
     */
    private function transformTenant(Tenant $tenant, string $plan): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $tenant->description ?? 'Bienvenido a nuestro negocio.',

            // Images with Storage URLs and fallbacks
            'logo' => $this->resolveImageUrl($tenant->logo_path, 'logo'),
            'cover' => $this->resolveImageUrl($tenant->cover_path, 'cover'),
            'banner_path' => $tenant->banner_path ?? $tenant->cover_path,
            'blur_hash' => $tenant->blur_hash,
            'primary_color' => $tenant->brand_color ?? $tenant->primary_color ?? '#2563EB',

            // ═══════════════════════════════════════════════════════════════
            // BRAND CUSTOMIZATION: White-label fields for CSS variable injection
            // These MUST be included for frontend theming to work
            // ═══════════════════════════════════════════════════════════════
            'brand_color' => $tenant->brand_color ?? '#2563EB',
            'hero_image' => $tenant->hero_image ? $this->resolveImageUrl($tenant->hero_image, 'hero') : null,
            'slogan' => $tenant->slogan,

            // Contact info
            'whatsapp' => $tenant->whatsapp_number,
            'whatsapp_number' => $tenant->whatsapp_number,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'address' => $tenant->address ?? 'Honduras',

            // Location
            'city' => $tenant->city?->name ?? 'Honduras',
            'city_id' => $tenant->city_id,
            'latitude' => $tenant->latitude,
            'longitude' => $tenant->longitude,

            // Category
            'category' => $tenant->category ? [
                'id' => $tenant->category->id,
                'name' => $tenant->category->name,
                'slug' => $tenant->category->slug,
            ] : ['id' => 0, 'name' => 'General', 'slug' => 'general'],

            // Status flags
            'is_pro' => (bool) $tenant->is_pro,
            'is_active' => (bool) $tenant->is_active,

            // Social links
            'facebook_url' => $tenant->facebook_url,
            'instagram_url' => $tenant->instagram_url,
            'tiktok_url' => $tenant->tiktok_url,
            'website_url' => $tenant->website_url,

            // Products embedded in tenant (some templates expect this)
            'products' => $this->transformProducts($tenant->products, $plan, $tenant),
            'menuSections' => $tenant->menuSections,

            // Mocked fields
            'rating' => 4.8,
            'reviews_count' => rand(10, 100),
        ];
    }

    /**
     * Transform products collection with SaaS tier feature flagging.
     *
     * SECURITY CRITICAL:
     * - Semilla: modifiers = [] (even if data exists in DB)
     * - Pro/Enterprise: Full modifier data with nested options
     */
    private function transformProducts($products, string $plan, Tenant $tenant): array
    {
        if (! $products) {
            return [];
        }

        return $products->map(function ($product) use ($plan, $tenant) {
            $baseProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug ?? \Illuminate\Support\Str::slug($product->name),
                'description' => $product->description ?? '',
                'price' => (float) ($product->price ?? 0),
                'formatted_price' => 'L. '.number_format((float) ($product->price ?? 0), 2),
                'image' => $this->resolveImageUrl($product->image ?? null, 'product'),
                'is_visible' => (bool) ($product->is_visible ?? true),
                'is_featured' => (bool) ($product->is_featured ?? false),
                'category_id' => $product->category_id,
                'menu_section_id' => $product->menu_section_id,

                // Tenant context for WhatsApp generation
                'tenant_whatsapp' => $tenant->whatsapp_number,
                'tenant_name' => $tenant->name,
            ];

            // FEATURE FLAG: Modifiers only for Pro/Enterprise
            if ($plan === self::PLAN_SEMILLA) {
                // Semilla: Explicitly null modifiers (security: don't leak data)
                $baseProduct['modifiers'] = [];
                $baseProduct['modifier_groups'] = [];
                $baseProduct['has_modifiers'] = false;
            } else {
                // Pro/Enterprise: Full modifier data
                $modifierGroups = $product->modifierGroups ?? collect([]);
                $baseProduct['modifier_groups'] = $modifierGroups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'type' => $group->type ?? 'single', // single, multiple
                        'is_required' => (bool) ($group->is_required ?? false),
                        'min_selections' => $group->min_selections ?? 0,
                        'max_selections' => $group->max_selections ?? 1,
                        'options' => ($group->options ?? collect([]))->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'name' => $option->name,
                                'price' => (float) ($option->price ?? 0),
                                'formatted_price' => $option->price > 0
                                    ? '+L. '.number_format((float) $option->price, 2)
                                    : 'Gratis',
                                'is_default' => (bool) ($option->is_default ?? false),
                            ];
                        })->toArray(),
                    ];
                })->toArray();

                $baseProduct['modifiers'] = $baseProduct['modifier_groups']; // Alias
                $baseProduct['has_modifiers'] = count($baseProduct['modifier_groups']) > 0;
            }

            return $baseProduct;
        })->toArray();
    }

    /**
     * Get mocked opening hours (since table doesn't exist).
     */
    private function getMockedOpeningHours(): array
    {
        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

        return collect($days)->map(fn ($day) => [
            'day' => $day,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'is_closed' => ($day === 'Domingo'),
        ])->toArray();
    }

    /**
     * Build SEO structured data for Google.
     */
    private function buildStructuredData(Tenant $tenant): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $tenant->name,
            'description' => $tenant->description ?? 'Negocio local en Honduras',
            'url' => url("/{$tenant->slug}"),
            'telephone' => $tenant->whatsapp_number ?? $tenant->phone,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $tenant->city?->name ?? 'Honduras',
                'addressCountry' => 'HN',
            ],
        ];
    }

    /**
     * Resolve image path to full URL with placeholder fallback.
     */
    private function resolveImageUrl(?string $path, string $type): string
    {
        if (empty($path)) {
            return match ($type) {
                'cover' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&h=600&fit=crop',
                'logo' => 'https://ui-avatars.com/api/?name=ConectaHN&background=0ea5e9&color=fff&size=200',
                'product' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&h=400&fit=crop',
                default => 'https://via.placeholder.com/400',
            };
        }

        // If already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Use Storage URL for local files
        return Storage::url($path);
    }
}
