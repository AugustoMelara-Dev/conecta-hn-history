<?php

declare(strict_types=1);

namespace App\Application\Tenant\Transformers;

use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\OpeningHour;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
final class TenantMicrositeTransformer
{
    public function __construct(
        private readonly PlanFeatureFlagService $planService
    ) {}

    /**
     * Transform complete tenant for microsite rendering.
     *
     * @param  Tenant  $tenant  Eager-loaded tenant model
     * @return array<string, mixed> Inertia-ready props
     */
    public function transform(Tenant $tenant): array
    {
        $cityName = $this->tenantCityName($tenant);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $this->tenantAttribute($tenant, 'description') ?? 'Bienvenido a nuestro negocio.',
            'about' => $this->tenantAttribute($tenant, 'about'), // Premium: Extended storytelling "Our Story"

            // Images with Storage URLs and fallbacks
            'logo' => $this->resolveImageUrl($this->tenantAttribute($tenant, 'logo_path'), 'logo'),
            'cover' => $this->resolveImageUrl($this->tenantAttribute($tenant, 'cover_path'), 'cover'),
            'banner_path' => $this->tenantAttribute($tenant, 'banner_path') ?? $this->tenantAttribute($tenant, 'cover_path'),
            'blur_hash' => $this->tenantAttribute($tenant, 'blur_hash'),
            'primary_color' => $this->tenantAttribute($tenant, 'brand_color') ?? $this->tenantAttribute($tenant, 'primary_color') ?? '#2563EB',

            // Brand customization (Pro feature check)
            'brand_color' => $this->tenantAttribute($tenant, 'brand_color') ?? '#2563EB',
            'hero_image' => $this->planService->canCustomizeHero($tenant) && $this->tenantAttribute($tenant, 'hero_image')
                ? $this->resolveImageUrl($this->tenantAttribute($tenant, 'hero_image'), 'hero')
                : null,
            'slogan' => $this->tenantAttribute($tenant, 'slogan'),

            // Contact info with formatted phone
            'whatsapp' => $this->planService->formatPhoneWithCountryCode($this->tenantAttribute($tenant, 'whatsapp_number')),
            'whatsapp_number' => $this->planService->formatPhoneWithCountryCode($this->tenantAttribute($tenant, 'whatsapp_number')),
            'phone' => $this->planService->formatPhoneWithCountryCode($this->tenantAttribute($tenant, 'phone')),
            'email' => $this->tenantAttribute($tenant, 'email'),
            'address' => $this->tenantAttribute($tenant, 'address') ?? 'Honduras',

            // Location (flat legacy + structured for Map component)
            'city' => $cityName,
            'city_id' => $this->tenantAttribute($tenant, 'city_id'),
            'latitude' => $this->formatLegacyCoordinate($this->tenantAttribute($tenant, 'latitude')),
            'longitude' => $this->formatLegacyCoordinate($this->tenantAttribute($tenant, 'longitude')),
            'location' => [ // Premium: Structured for Map components
                'lat' => $this->tenantAttribute($tenant, 'latitude') !== null ? (float) $this->tenantAttribute($tenant, 'latitude') : null,
                'lng' => $this->tenantAttribute($tenant, 'longitude') !== null ? (float) $this->tenantAttribute($tenant, 'longitude') : null,
                'address' => $this->tenantAttribute($tenant, 'address') ?? 'Honduras',
                'city' => $cityName,
            ],

            // Category
            'category' => $this->transformCategory($tenant),

            // Status flags safe for the public microsite
            'is_active' => (bool) $this->tenantAttribute($tenant, 'is_active'),
            'business_type' => $this->tenantStringAttribute($tenant, 'business_type', 'retail'),
            'commercial_mode' => $this->tenantStringAttribute($tenant, 'commercial_mode', 'products'),
            'conversion_profile' => $this->conversionProfile($tenant),

            // Social links (legacy flat + structured grouping)
            'facebook_url' => $this->planService->canShowSocialGraph($tenant) ? $this->tenantAttribute($tenant, 'facebook_url') : null,
            'instagram_url' => $this->planService->canShowSocialGraph($tenant) ? $this->tenantAttribute($tenant, 'instagram_url') : null,
            'tiktok_url' => $this->planService->canShowSocialGraph($tenant) ? $this->tenantAttribute($tenant, 'tiktok_url') : null,
            'website_url' => $this->tenantAttribute($tenant, 'website_url'),
            'social' => $this->planService->canShowSocialGraph($tenant) ? [ // Premium: Grouped for frontend
                'facebook' => $this->tenantAttribute($tenant, 'facebook_url'),
                'instagram' => $this->tenantAttribute($tenant, 'instagram_url'),
                'tiktok' => $this->tenantAttribute($tenant, 'tiktok_url'),
                'website' => $this->tenantAttribute($tenant, 'website_url'),
            ] : null,

            // Products embedded in tenant
            'products' => $this->transformProducts($tenant),
            'menuSections' => $tenant->relationLoaded('menuSections') ? $tenant->menuSections : collect(),

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
                'commercial_mode' => $this->tenantStringAttribute($tenant, 'commercial_mode', 'products'),
            ],
        ];
    }

    /**
     * Transform products collection with SaaS tier feature flagging.
     */
    public function transformProducts(Tenant $tenant): array
    {
        $products = $tenant->relationLoaded('products')
            ? $tenant->products
            : $tenant->products()
                ->withoutGlobalScopes()
                ->where('is_visible', true)
                ->orderBy('name')
                ->get();

        if (! $products || $products->isEmpty()) {
            return [];
        }

        $canShowModifiers = $this->planService->canShowModifiers($tenant)
            && Schema::hasTable('modifier_groups')
            && Schema::hasTable('modifier_options');

        return $products->map(function ($product) use ($tenant, $canShowModifiers) {
            $baseProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug ?? Str::slug($product->name),
                'description' => $product->description ?? '',
                'price' => (float) ($product->price ?? 0),
                'formatted_price' => 'L. '.number_format((float) ($product->price ?? 0), 2),
                'image' => $this->resolveImageUrl($product->image ?? null, 'product'),
                'is_visible' => (bool) ($product->is_visible ?? true),
                'is_featured' => (bool) ($product->is_featured ?? false),
                'category_id' => $this->modelAttribute($product, 'category_id'),
                'menu_section_id' => $this->modelAttribute($product, 'menu_section_id'),

                // Tenant context for WhatsApp generation
                'tenant_whatsapp' => $this->planService->formatPhoneWithCountryCode($this->tenantAttribute($tenant, 'whatsapp_number')),
                'tenant_name' => $tenant->name,
            ];

            // FEATURE FLAG: Modifiers only for Pro/Enterprise
            if (! $canShowModifiers || ! $product->relationLoaded('modifierGroups')) {
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
                    'options' => ($group->options ?? collect([]))->map(function ($opt): array {
                        $price = (float) ($opt->price_adjustment ?? $opt->price ?? 0);

                        return [
                            'id' => $opt->id,
                            'name' => $opt->name,
                            'price' => $price,
                            'formatted_price' => $price > 0 ? '+L. '.number_format($price, 2) : 'Gratis',
                            'is_default' => (bool) ($opt->is_default ?? false),
                        ];
                    })->toArray(),
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
        $currentTime = now(OpeningHour::BUSINESS_TIMEZONE);

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
                    'day_name' => OpeningHour::DAY_LABELS[$day] ?? 'Desconocido',
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
        $now = now(OpeningHour::BUSINESS_TIMEZONE);
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
                'description' => $this->tenantAttribute($tenant, 'description') ?? 'Negocio local en Honduras',
            'url' => url("/{$tenant->slug}"),
            'telephone' => $this->planService->formatPhoneWithCountryCode(
                $this->tenantAttribute($tenant, 'whatsapp_number') ?? $this->tenantAttribute($tenant, 'phone')
            ),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $this->tenantCityName($tenant),
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
        if (! Schema::hasColumn('tenants', 'category_id')) {
            return ['id' => 0, 'name' => 'General', 'slug' => 'general'];
        }

        if (! $tenant->category) {
            return ['id' => 0, 'name' => 'General', 'slug' => 'general'];
        }

        return [
            'id' => $tenant->category->id,
            'name' => $tenant->category->name,
            'slug' => $tenant->category->slug,
        ];
    }

    private function tenantCityName(Tenant $tenant): string
    {
        if (! Schema::hasColumn('tenants', 'city_id')) {
            return 'Honduras';
        }

        return $tenant->city?->name ?? 'Honduras';
    }

    private function tenantAttribute(Tenant $tenant, string $key): mixed
    {
        return $tenant->getAttributes()[$key] ?? null;
    }

    private function formatLegacyCoordinate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 8, '.', '');
    }

    private function tenantStringAttribute(Tenant $tenant, string $key, string $default): string
    {
        $value = $tenant->getAttribute($key);

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function modelAttribute(object $model, string $key): mixed
    {
        if (method_exists($model, 'getAttributes')) {
            return $model->getAttributes()[$key] ?? null;
        }

        return $model->{$key} ?? null;
    }

    /**
     * Public copy contract for microsites that are not catalog-first.
     *
     * @return array{label:string,headline:string,cta:string,empty:string}
     */
    private function conversionProfile(Tenant $tenant): array
    {
        $mode = $this->tenantStringAttribute($tenant, 'commercial_mode', 'products');

        return match ($mode) {
            'appointments' => [
                'label' => 'Reservas y servicios',
                'headline' => 'Agenda una atencion con el negocio.',
                'cta' => 'Consultar disponibilidad',
                'empty' => 'Este negocio aun esta preparando sus servicios reservables.',
            ],
            'consulting_leads' => [
                'label' => 'Consultas profesionales',
                'headline' => 'Conoce la propuesta y solicita una consulta.',
                'cta' => 'Solicitar consulta',
                'empty' => 'Este negocio aun esta preparando su oferta consultiva, pero puedes contactarlo directamente.',
            ],
            'hybrid' => [
                'label' => 'Productos y servicios',
                'headline' => 'Explora ofertas, servicios y canales de atencion.',
                'cta' => 'Consultar oferta',
                'empty' => 'Este negocio esta configurando su operacion mixta.',
            ],
            default => [
                'label' => 'Venta de productos',
                'headline' => 'Explora productos y confirma tu pedido.',
                'cta' => 'Ver catalogo',
                'empty' => 'Este negocio aun no ha publicado productos visibles.',
            ],
        };
    }

    /**
     * Resolve image path to full URL with placeholder fallback.
     */
    private function resolveImageUrl(?string $path, string $type): string
    {
        if (empty($path)) {
            return match ($type) {
                'cover' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&h=600&fit=crop',
                'logo' => 'https://ui-avatars.com/api/?name=Vito&background=0ea5e9&color=fff&size=200',
                'product' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&h=400&fit=crop',
                default => 'https://via.placeholder.com/400',
            };
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    }
}
