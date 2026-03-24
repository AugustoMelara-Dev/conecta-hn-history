<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Transformers;

use App\Application\Shared\Contracts\FileUrlResolverInterface;
use App\Application\Shared\Contracts\SlugGeneratorInterface;
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
            'business_type' => $tenant->business_type?->value ?? BusinessType::RETAIL->value,
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
            'is_pro' => (bool) $tenant->is_pro,
            'is_active' => (bool) $tenant->is_active,
            'plan' => $plan,
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

        $baseProfile = match ($businessType) {
            BusinessType::FOOD => [
                'label' => 'Restaurante y comida',
                'hero_title' => 'Menu claro, pedidos directos y presencia lista para abrir apetito.',
                'hero_copy' => 'Ideal para restaurantes, cafeterias y dark kitchens que necesitan convertir visitas en pedidos con menos friccion.',
                'offer_title' => 'Lo mejor del menu',
                'offer_copy' => 'Explora categorias, platos destacados y canales rapidos para pedir.',
                'catalog_title' => 'Menu disponible',
                'catalog_copy' => 'Consulta platos, combos y bebidas visibles en este momento.',
                'primary_cta_label' => 'Pedir por WhatsApp',
                'secondary_cta_label' => 'Ver menu',
                'mobile_tab_label' => 'Menu',
                'footer_copy' => 'Micrositio gastronomico conectado a Conecta HN para mostrar menu, captar pedidos y crecer con mejor presencia digital.',
            ],
            BusinessType::SERVICE => [
                'label' => 'Servicios y citas',
                'hero_title' => 'Agenda, confianza y servicios claros desde la primera visita.',
                'hero_copy' => 'Pensado para consultorias, abogados, clinicas, belleza y negocios donde reservar o cotizar es tan importante como la presentacion.',
                'offer_title' => 'Servicios destacados',
                'offer_copy' => 'Conoce servicios, duracion y precio antes de reservar.',
                'catalog_title' => 'Servicios y complementos',
                'catalog_copy' => 'Si este negocio tambien vende productos o paquetes, aqui los encontraras.',
                'primary_cta_label' => 'Reservar ahora',
                'secondary_cta_label' => 'Hablar por WhatsApp',
                'mobile_tab_label' => 'Servicios',
                'footer_copy' => 'Micrositio profesional conectado a Conecta HN para mostrar servicios, captar consultas y convertir reservas con una experiencia mas clara.',
            ],
            BusinessType::HYBRID => [
                'label' => 'Negocio mixto',
                'hero_title' => 'Productos, servicios y atencion coordinados en un solo frente digital.',
                'hero_copy' => 'Creado para negocios que venden, asesoran, atienden citas o combinan varias lineas de operacion sin perder claridad.',
                'offer_title' => 'Oferta principal del negocio',
                'offer_copy' => 'Descubre el mix entre catalogo, servicios y atencion especializada.',
                'catalog_title' => 'Catalogo y soluciones',
                'catalog_copy' => 'Aqui conviven productos, paquetes y frentes comerciales complementarios.',
                'primary_cta_label' => 'Explorar negocio',
                'secondary_cta_label' => 'Reservar o consultar',
                'mobile_tab_label' => 'Ofertas',
                'footer_copy' => 'Micrositio hibrido conectado a Conecta HN para presentar productos y servicios sin fragmentar la experiencia del cliente.',
            ],
            default => [
                'label' => 'Tienda y catalogo',
                'hero_title' => 'Catalogo potente, contacto directo y una vitrina lista para vender.',
                'hero_copy' => 'Pensado para tiendas de celulares, ferreterias, moda, hogar y negocios que viven de su catalogo e inventario.',
                'offer_title' => 'Lo mas buscado de la vitrina',
                'offer_copy' => 'Explora productos, secciones y razones para comprar en este negocio.',
                'catalog_title' => 'Catalogo publico',
                'catalog_copy' => 'Revisa productos visibles, precios y opciones de contacto rapido.',
                'primary_cta_label' => 'Ver catalogo',
                'secondary_cta_label' => 'Hablar por WhatsApp',
                'mobile_tab_label' => 'Catalogo',
                'footer_copy' => 'Micrositio comercial conectado a Conecta HN con escaparate publico, contacto directo y una presencia mas solida para vender.',
            ],
        };

        $verticalKey = $this->resolveStorefrontVerticalKey($tenant, $businessType);
        $verticalProfile = $this->resolveVerticalProfile($verticalKey);
        $verticalPlaybook = $this->resolveVerticalPlaybook($verticalKey);

        $mergedProfile = array_merge($baseProfile, array_intersect_key($verticalProfile, [
            'hero_title' => true,
            'hero_copy' => true,
            'offer_title' => true,
            'offer_copy' => true,
            'catalog_title' => true,
            'catalog_copy' => true,
            'primary_cta_label' => true,
            'secondary_cta_label' => true,
            'mobile_tab_label' => true,
            'footer_copy' => true,
        ]));
        $mergedProfile = array_merge($mergedProfile, array_intersect_key($verticalPlaybook, [
            'journey_title' => true,
            'journey_copy' => true,
            'journey_steps' => true,
            'growth_title' => true,
            'growth_copy' => true,
            'growth_cards' => true,
            'catalog_panel_title' => true,
            'catalog_panel_copy' => true,
            'service_panel_title' => true,
            'service_panel_copy' => true,
        ]));

        $mergedProfile['vertical_key'] = $verticalKey;
        $mergedProfile['vertical_label'] = $verticalProfile['vertical_label'] ?? $mergedProfile['label'];
        $mergedProfile['trust_points'] = $verticalProfile['trust_points'] ?? [];
        $mergedProfile['specialized_blocks'] = $verticalProfile['specialized_blocks'] ?? [];
        $mergedProfile['focus_cards'] = $verticalProfile['focus_cards'] ?? [];

        return $mergedProfile;
    }

    private function resolveStorefrontVerticalKey(Tenant $tenant, BusinessType $businessType): string
    {
        return $this->segmentResolver->resolve(
            businessType: $businessType->value,
            name: $tenant->name,
            description: $tenant->description,
            about: $tenant->about,
            categorySlug: $tenant->category?->slug,
            categoryName: $tenant->category?->name,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveVerticalProfile(string $verticalKey): array
    {
        /** @var array<string, mixed> $segmentProfiles */
        $segmentProfiles = config('storefront-segments.profiles', []);

        if (isset($segmentProfiles[$verticalKey]) && is_array($segmentProfiles[$verticalKey])) {
            /** @var array<string, mixed> $segmentProfile */
            $segmentProfile = $segmentProfiles[$verticalKey];

            return $segmentProfile;
        }

        /** @var array<string, mixed> $profiles */
        $profiles = config('storefront-verticals.profiles', []);

        /** @var array<string, mixed> $profile */
        $profile = $profiles[$verticalKey] ?? [];

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveVerticalPlaybook(string $verticalKey): array
    {
        /** @var array<string, mixed> $segmentPlaybooks */
        $segmentPlaybooks = config('storefront-segments.playbooks', []);

        if (isset($segmentPlaybooks[$verticalKey]) && is_array($segmentPlaybooks[$verticalKey])) {
            /** @var array<string, mixed> $segmentPlaybook */
            $segmentPlaybook = $segmentPlaybooks[$verticalKey];

            return $segmentPlaybook;
        }

        /** @var array<string, mixed> $playbooks */
        $playbooks = config('storefront-playbooks', []);

        /** @var array<string, mixed> $playbook */
        $playbook = $playbooks[$verticalKey] ?? [];

        return $playbook;
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

