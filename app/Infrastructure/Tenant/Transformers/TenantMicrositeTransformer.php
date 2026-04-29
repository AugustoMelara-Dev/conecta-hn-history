<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Transformers;

use App\Application\Shared\Contracts\FileUrlResolverInterface;
use App\Application\Shared\Contracts\SlugGeneratorInterface;
use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\Category;
use App\Models\City;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OpeningHour;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class TenantMicrositeTransformer
{
    public function __construct(
        private PlanFeatureFlagService $planService,
        private FileUrlResolverInterface $filesystem,
        private SlugGeneratorInterface $slugGenerator,
        private BusinessSegmentResolver $segmentResolver,
        private RubroManifestReaderInterface $rubroManifest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function transform(Tenant $tenant): array
    {
        $plan = $this->planService->determinePlan($tenant);
        /** @var City|null $city */
        $city = $tenant->city;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'business_type' => $tenant->business_type->value,
            'description' => $tenant->description ?? 'Bienvenido a nuestro negocio.',
            'about' => $tenant->about,
            'logo' => $this->resolveImageUrl($tenant->logo_path, 'logo'),
            'cover' => $this->resolveImageUrl($tenant->cover_path, 'cover'),
            'banner_path' => $tenant->banner_path ?? $tenant->cover_path,
            'blur_hash' => $tenant->blur_hash,
            'primary_color' => $tenant->brand_color ?? $tenant->primary_color ?? '#2563EB',
            'brand_color' => $tenant->brand_color ?? '#2563EB',
            'hero_image' => $this->planService->canCustomizeHero($tenant) && $tenant->hero_image
                ? $this->resolveImageUrl($tenant->hero_image, 'hero')
                : null,
            'slogan' => $tenant->slogan,
            'whatsapp' => $this->planService->formatPhoneWithCountryCode($tenant->whatsapp_number),
            'whatsapp_number' => $this->planService->formatPhoneWithCountryCode($tenant->whatsapp_number),
            'phone' => $this->planService->formatPhoneWithCountryCode($tenant->phone),
            'email' => $tenant->email,
            'address' => $tenant->address,
            'city' => $city !== null ? $city->name : 'Honduras',
            'city_id' => $tenant->city_id,
            'latitude' => $tenant->latitude,
            'longitude' => $tenant->longitude,
            'location' => [
                'lat' => $tenant->latitude !== null ? (float) $tenant->latitude : null,
                'lng' => $tenant->longitude !== null ? (float) $tenant->longitude : null,
                'address' => $tenant->address,
                'city' => $city !== null ? $city->name : 'Honduras',
            ],
            'category' => $this->transformCategory($tenant),
            'is_active' => (bool) $tenant->is_active,
            'plan' => $plan,
            'plan_type' => $plan,
            'is_paid_plan' => $plan !== Tenant::PLAN_SEMILLA,
            'storefront_profile' => $this->buildStorefrontProfile($tenant),
            'facebook_url' => $this->planService->canShowSocialGraph($tenant) ? $tenant->facebook_url : null,
            'instagram_url' => $this->planService->canShowSocialGraph($tenant) ? $tenant->instagram_url : null,
            'tiktok_url' => $this->planService->canShowSocialGraph($tenant) ? $tenant->tiktok_url : null,
            'website_url' => $tenant->website_url,
            'social' => $this->planService->canShowSocialGraph($tenant) ? [
                'facebook' => $tenant->facebook_url,
                'instagram' => $tenant->instagram_url,
                'tiktok' => $tenant->tiktok_url,
                'website' => $tenant->website_url,
            ] : null,
            'products' => $this->transformProducts($tenant),
            'menuSections' => $tenant->menuSections,
            'menu_sections_overview' => $this->transformMenuSections($tenant),
            'services' => $this->transformServices($tenant),
            'service_providers_count' => $tenant->serviceProviders->count(),
            'rating' => $this->planService->canShowReviews($tenant) ? 4.8 : null,
            'reviews_count' => $this->planService->canShowReviews($tenant) ? rand(10, 100) : null,
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
     * @return array<int, array<string, mixed>>
     */
    public function transformProducts(Tenant $tenant): array
    {
        /** @var EloquentCollection<int, Product> $products */
        $products = $tenant->products;

        if ($products->isEmpty()) {
            return [];
        }

        $canShowModifiers = $this->planService->canShowModifiers($tenant);

        return $products->map(function (Product $product) use ($tenant, $canShowModifiers): array {
            $baseProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug ?? $this->slugGenerator->generate($product->name),
                'description' => $product->description ?? '',
                'price' => (float) ($product->price ?? 0),
                'formatted_price' => 'L. '.number_format((float) ($product->price ?? 0), 2),
                'image' => $this->resolveImageUrl($product->image_path, 'product'),
                'is_visible' => (bool) ($product->is_visible ?? true),
                'is_featured' => (bool) ($product->is_featured ?? false),
                'category_id' => $product->category_id,
                'menu_section_id' => $product->menu_section_id,
                'tenant_whatsapp' => $this->planService->formatPhoneWithCountryCode($tenant->whatsapp_number),
                'tenant_name' => $tenant->name,
            ];

            if (! $canShowModifiers) {
                $baseProduct['modifiers'] = [];
                $baseProduct['modifier_groups'] = [];
                $baseProduct['has_modifiers'] = false;

                return $baseProduct;
            }

            /** @var EloquentCollection<int, ModifierGroup> $modifierGroups */
            $modifierGroups = $product->modifierGroups;

            $baseProduct['modifier_groups'] = $this->transformModifierGroups($modifierGroups);

            $baseProduct['modifiers'] = $baseProduct['modifier_groups'];
            $baseProduct['has_modifiers'] = count($baseProduct['modifier_groups']) > 0;

            return $baseProduct;
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function transformServices(Tenant $tenant): array
    {
        /** @var EloquentCollection<int, Service> $services */
        $services = $tenant->services;

        if ($services->isEmpty()) {
            return [];
        }

        return $services
            ->map(static function (Service $service): array {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description ?? '',
                    'price' => (float) $service->price,
                    'formatted_price' => $service->formatted_price,
                    'duration_minutes' => $service->duration_minutes,
                    'duration_formatted' => $service->formatted_duration,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  EloquentCollection<int, OpeningHour>|null  $hours
     * @return array<int, array<string, mixed>>
     */
    public function transformOpeningHours(?EloquentCollection $hours): array
    {
        $culturalDayOrder = [1, 2, 3, 4, 5, 6, 0];

        /** @var array<int, OpeningHour> $hoursMap */
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

                continue;
            }

            $result[] = [
                'day_name' => OpeningHour::DAY_LABELS[$day],
                'formatted' => 'Cerrado',
                'is_open_now' => false,
                'day_of_week' => $day,
                'is_closed' => true,
            ];
        }

        return $result;
    }

    public function isCurrentlyOpen(Tenant $tenant): bool
    {
        $now = now(OpeningHour::BUSINESS_TIMEZONE);
        $today = $now->dayOfWeek;

        /** @var OpeningHour|null $todaySchedule */
        $todaySchedule = $tenant->openingHours->firstWhere('day_of_week', $today);

        if ($todaySchedule === null) {
            return false;
        }

        return $todaySchedule->isOpenAt($now);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStructuredData(Tenant $tenant): array
    {
        /** @var City|null $city */
        $city = $tenant->city;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $tenant->name,
            'description' => $tenant->description ?? 'Negocio local en Honduras',
            'url' => route('tenant.microsite.show', ['tenant' => $tenant->slug]),
            'telephone' => $this->planService->formatPhoneWithCountryCode(
                $tenant->whatsapp_number ?? $tenant->phone
            ),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $city !== null ? $city->name : 'Honduras',
                'addressCountry' => 'HN',
            ],
        ];
    }

    public function getProductLimit(Tenant $tenant): int
    {
        return $this->planService->getMaxProductLimit($tenant);
    }

    /**
     * @return array{id:int|string, name:string, slug:string}
     */
    private function transformCategory(Tenant $tenant): array
    {
        /** @var Category|null $category */
        $category = $tenant->category;

        if ($category === null) {
            return ['id' => 0, 'name' => 'General', 'slug' => 'general'];
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStorefrontProfile(Tenant $tenant): array
    {
        $businessType = $tenant->business_type ?? BusinessType::RETAIL;
        $verticalKey = $this->resolveStorefrontVerticalKey($tenant, $businessType);

        return $this->rubroManifest->storefrontProfileForRubroKey($verticalKey, $tenant);
    }

    private function resolveStorefrontVerticalKey(Tenant $tenant, BusinessType $businessType): string
    {
        /** @var Category|null $category */
        $category = $tenant->category;

        return $this->segmentResolver->resolve(
            businessType: $businessType->value,
            name: $tenant->name,
            description: $tenant->description,
            about: $tenant->about,
            slug: $tenant->slug,
            categorySlug: $category?->slug,
            categoryName: $category?->name,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transformMenuSections(Tenant $tenant): array
    {
        /** @var EloquentCollection<int, MenuSection> $sections */
        $sections = $tenant->menuSections;

        if ($sections->isEmpty()) {
            return [];
        }

        return $sections
            ->map(static function (MenuSection $section): array {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'products_count' => (int) ($section->products_count ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveImageUrl(?string $path, string $type): string
    {
        if (empty($path)) {
            return match ($type) {
                'cover' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&h=600&fit=crop',
                'logo' => 'https://ui-avatars.com/api/?name=Vito Business OS&background=0ea5e9&color=fff&size=200',
                'product' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&h=400&fit=crop',
                default => 'https://via.placeholder.com/400',
            };
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->filesystem->url($path);
    }

    /**
     * @param  EloquentCollection<int, ModifierGroup>  $modifierGroups
     * @return array<int, array<string, mixed>>
     */
    private function transformModifierGroups(EloquentCollection $modifierGroups): array
    {
        $result = [];

        foreach ($modifierGroups as $group) {
            /** @var EloquentCollection<int, ModifierOption> $options */
            $options = $group->options;

            $maxSelections = (int) $group->getAttribute('max_selections');

            $result[] = [
                'id' => (int) $group->getKey(),
                'name' => (string) $group->getAttribute('name'),
                'type' => $maxSelections === 1 ? 'single' : 'multiple',
                'is_required' => (bool) $group->getAttribute('is_required'),
                'min_selections' => (int) $group->getAttribute('min_selections'),
                'max_selections' => $maxSelections,
                'options' => $this->transformModifierOptions($options),
            ];
        }

        return $result;
    }

    /**
     * @param  EloquentCollection<int, ModifierOption>  $options
     * @return array<int, array<string, mixed>>
     */
    private function transformModifierOptions(EloquentCollection $options): array
    {
        $result = [];

        foreach ($options as $option) {
            $priceAdjustment = (float) $option->getAttribute('price_adjustment');

            $result[] = [
                'id' => (int) $option->getKey(),
                'name' => (string) $option->getAttribute('name'),
                'price' => $priceAdjustment,
                'formatted_price' => $priceAdjustment > 0
                    ? '+L. '.number_format($priceAdjustment, 2)
                    : 'Gratis',
                'is_default' => (bool) $option->getAttribute('is_default'),
            ];
        }

        return $result;
    }
}
