<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Transformers;

use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Application\Shared\Contracts\FileUrlResolverInterface;
use App\Application\Shared\Contracts\SlugGeneratorInterface;
use App\Models\Tenant;

/**
 * TenantMicrositeTransformer - Application Layer Data Transformer
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE RESPONSIBILITY: Transform Tenant → Inertia-ready JSON           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  DEPENDENCY INJECTION: Uses PlanFeatureFlagService for plan logic       ║
 * ║                                                                           ║
 * ║  This transformer:                                                        ║
 * ║  - Formats phone numbers with +504 prefix                                 ║
 * ║  - Resolves image URLs with placeholders                                  ║
 * ║  - Conditionally includes Pro features based on plan                      ║
 * ║  - Pre-processes ALL display logic so React receives clean JSON          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see \App\Http\Controllers\Public\TenantMicrositeController - Uses this transformer
 */
final readonly class TenantMicrositeTransformer
{
    public function __construct(
        private readonly PlanFeatureFlagService $planService,
        private readonly FileUrlResolverInterface $filesystem,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {}

    /**
     * Transform complete tenant for microsite rendering.
     *
     * @param  Tenant  $tenant  Eager-loaded tenant model
     * @return array<string, mixed> Inertia-ready props
     */
    public function transform(Tenant $tenant): array
    {
        $plan = $this->planService->determinePlan($tenant);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $tenant->description ?? 'Bienvenido a nuestro negocio.',
            'about' => $tenant->about, // Premium: Extended storytelling "Our Story"

            // Images with Storage URLs and fallbacks
            'logo' => $this->resolveImageUrl($tenant->logo_path, 'logo'),
            'cover' => $this->resolveImageUrl($tenant->cover_path, 'cover'),
            'banner_path' => $tenant->banner_path ?? $tenant->cover_path,
            'blur_hash' => $tenant->blur_hash,
            'primary_color' => $tenant->brand_color ?? $tenant->primary_color ?? '#2563EB',

            // Brand customization (Pro feature check)
            'brand_color' => $tenant->brand_color ?? '#2563EB',
            'hero_image' => $this->planService->canCustomizeHero($tenant) && $tenant->hero_image
                ? $this->resolveImageUrl($tenant->hero_image, 'hero')
                : null,
            'slogan' => $tenant->slogan,

            // Contact info with formatted phone
            'whatsapp' => $this->planService->formatPhoneWithCountryCode($tenant->whatsapp_number),
            'whatsapp_number' => $this->planService->formatPhoneWithCountryCode($tenant->whatsapp_number),
            'phone' => $this->planService->formatPhoneWithCountryCode($tenant->phone),
            'email' => $tenant->email,
            'address' => $tenant->address ?? 'Honduras',

            // Location (flat legacy + structured for Map component)
            'city' => $tenant->city?->name ?? 'Honduras',
            'city_id' => $tenant->city_id,
            'latitude' => $tenant->latitude,
            'longitude' => $tenant->longitude,
            'location' => [ // Premium: Structured for Map components
                'lat' => $tenant->latitude !== null ? (float) $tenant->latitude : null,
                'lng' => $tenant->longitude !== null ? (float) $tenant->longitude : null,
                'address' => $tenant->address ?? 'Honduras',
                'city' => $tenant->city?->name ?? 'Honduras',
            ],

            // Category
            'category' => $this->transformCategory($tenant),

            // Status flags
            'is_pro' => (bool) $tenant->is_pro,
            'is_active' => (bool) $tenant->is_active,
            'plan' => $plan,

            // Social links (legacy flat + structured grouping)
            'facebook_url' => $this->planService->canShowSocialGraph($tenant) ? $tenant->facebook_url : null,
            'instagram_url' => $this->planService->canShowSocialGraph($tenant) ? $tenant->instagram_url : null,
            'tiktok_url' => $this->planService->canShowSocialGraph($tenant) ? $tenant->tiktok_url : null,
            'website_url' => $tenant->website_url,
            'social' => $this->planService->canShowSocialGraph($tenant) ? [ // Premium: Grouped for frontend
                'facebook' => $tenant->facebook_url,
                'instagram' => $tenant->instagram_url,
                'tiktok' => $tenant->tiktok_url,
                'website' => $tenant->website_url,
            ] : null,

            // Products embedded in tenant
            'products' => $this->transformProducts($tenant),
            'menuSections' => $tenant->menuSections,

            // Reviews (Pro feature)
            'rating' => $this->planService->canShowReviews($tenant) ? 4.8 : null,
            'reviews_count' => $this->planService->canShowReviews($tenant) ? rand(10, 100) : null,

            // Feature flags for frontend conditional rendering
            'features' => [
                'can_show_modifiers' => $this->planService->canShowModifiers($tenant),
                'can_show_reviews' => $this->planService->canShowReviews($tenant),
                'can_show_social' => $this->planService->canShowSocialGraph($tenant),
                'can_use_cart' => $this->planService->canUseCart($tenant),
                'can_use_coupons' => $this->planService->canUseCoupons($tenant),
            ],
        ];
    }

    /**
     * Transform products collection with SaaS tier feature flagging.
     */
    public function transformProducts(Tenant $tenant): array
    {
        $products = $tenant->products;

        if (! $products || $products->isEmpty()) {
            return [];
        }

        $canShowModifiers = $this->planService->canShowModifiers($tenant);

        return $products->map(function ($product) use ($tenant, $canShowModifiers) {
            $baseProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug ?? $this->slugGenerator->generate($product->name),
                'description' => $product->description ?? '',
                'price' => (float) ($product->price ?? 0),
                'formatted_price' => 'L. '.number_format((float) ($product->price ?? 0), 2),
                'image' => $this->resolveImageUrl($product->image ?? null, 'product'),
                'is_visible' => (bool) ($product->is_visible ?? true),
                'is_featured' => (bool) ($product->is_featured ?? false),
                'category_id' => $product->category_id,
                'menu_section_id' => $product->menu_section_id,

                // Tenant context for WhatsApp generation
                'tenant_whatsapp' => $this->planService->formatPhoneWithCountryCode($tenant->whatsapp_number),
                'tenant_name' => $tenant->name,
            ];

            // FEATURE FLAG: Modifiers only for Pro/Enterprise
            if (! $canShowModifiers) {
                $baseProduct['modifiers'] = [];
                $baseProduct['modifier_groups'] = [];
                $baseProduct['has_modifiers'] = false;
            } else {
                $modifierGroups = $product->modifierGroups ?? collect([]);
                $baseProduct['modifier_groups'] = $modifierGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => $group->type ?? 'single',
                    'is_required' => (bool) ($group->is_required ?? false),
                    'min_selections' => $group->min_selections ?? 0,
                    'max_selections' => $group->max_selections ?? 1,
                    'options' => ($group->options ?? collect([]))->map(fn ($opt) => [
                        'id' => $opt->id,
                        'name' => $opt->name,
                        'price' => (float) ($opt->price ?? 0),
                        'formatted_price' => $opt->price > 0 ? '+L. '.number_format((float) $opt->price, 2) : 'Gratis',
                        'is_default' => (bool) ($opt->is_default ?? false),
                    ])->toArray(),
                ])->toArray();

                $baseProduct['modifiers'] = $baseProduct['modifier_groups'];
                $baseProduct['has_modifiers'] = count($baseProduct['modifier_groups']) > 0;
            }

            return $baseProduct;
        })->toArray();
    }

    /**
     * Transform opening hours collection to frontend contract.
     *
     * CONTRACT: Frontend ALWAYS receives exactly 7 elements in Lunes→Domingo order.
     */
    public function transformOpeningHours($hours): array
    {
        $culturalDayOrder = [1, 2, 3, 4, 5, 6, 0];

        $hoursMap = [];
        if ($hours !== null && ! $hours->isEmpty()) {
            foreach ($hours as $hour) {
                $hoursMap[$hour->day_of_week] = $hour;
            }
        }

        $result = [];
        $currentTime = now(\App\Models\OpeningHour::BUSINESS_TIMEZONE);

        foreach ($culturalDayOrder as $day) {
            if (isset($hoursMap[$day])) {
                $hour = $hoursMap[$day];
                $result[] = [
                    'day_name' => $hour->day_name,
                    'formatted' => $hour->formatted_hours,
                    'is_open_now' => $hour->isOpenAt($currentTime),
                    'day_of_week' => $day,
                    'is_closed' => false,
                ];
            } else {
                $result[] = [
                    'day_name' => \App\Models\OpeningHour::DAY_LABELS[$day] ?? 'Desconocido',
                    'formatted' => 'Cerrado',
                    'is_open_now' => false,
                    'day_of_week' => $day,
                    'is_closed' => true,
                ];
            }
        }

        return $result;
    }

    /**
     * Determine if the business is currently open.
     */
    public function isCurrentlyOpen(Tenant $tenant): bool
    {
        $now = now(\App\Models\OpeningHour::BUSINESS_TIMEZONE);
        $today = $now->dayOfWeek;

        $todaySchedule = $tenant->openingHours->firstWhere('day_of_week', $today);

        if ($todaySchedule === null) {
            return false;
        }

        return $todaySchedule->isOpenAt($now);
    }

    /**
     * Build SEO structured data for Google.
     */
    public function buildStructuredData(Tenant $tenant): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $tenant->name,
            'description' => $tenant->description ?? 'Negocio local en Honduras',
            'url' => url("/{$tenant->slug}"),
            'telephone' => $this->planService->formatPhoneWithCountryCode(
                $tenant->whatsapp_number ?? $tenant->phone
            ),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $tenant->city?->name ?? 'Honduras',
                'addressCountry' => 'HN',
            ],
        ];
    }

    /**
     * Get product limit for tenant's plan.
     */
    public function getProductLimit(Tenant $tenant): int
    {
        return $this->planService->getMaxProductLimit($tenant);
    }

    /**
     * Transform category data.
     */
    private function transformCategory(Tenant $tenant): array
    {
        if (! $tenant->category) {
            return ['id' => 0, 'name' => 'General', 'slug' => 'general'];
        }

        return [
            'id' => $tenant->category->id,
            'name' => $tenant->category->name,
            'slug' => $tenant->category->slug,
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

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->filesystem->url($path);
    }
}
