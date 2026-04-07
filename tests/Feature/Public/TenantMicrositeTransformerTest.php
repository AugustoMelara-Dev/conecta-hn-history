<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Infrastructure\Tenant\Transformers\TenantMicrositeTransformer;
use App\Models\Category;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantMicrositeTransformerTest extends TestCase
{
    use DatabaseTransactions;

    private const LEGACY_PAID_PLAN_FLAG = 'is_pro';

    #[Test]
    public function it_exposes_the_about_field_for_storytelling(): void
    {
        $tenant = Tenant::factory()->create([
            'about' => 'Our story began in 2020...',
            'description' => 'Test business',
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertArrayHasKey('about', $result);
        self::assertSame('Our story began in 2020...', $result['about']);
        self::assertArrayHasKey('plan_type', $result);
        self::assertArrayHasKey('is_paid_plan', $result);
        self::assertArrayNotHasKey(self::LEGACY_PAID_PLAN_FLAG, $result);
    }

    #[Test]
    public function it_exposes_a_structured_location_object_with_typed_coordinates(): void
    {
        $tenant = Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
            'address' => 'Barrio El Centro, Tocoa',
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertArrayHasKey('location', $result);
        self::assertIsArray($result['location']);
        self::assertSame(15.4994, $result['location']['lat']);
        self::assertSame(-88.025, $result['location']['lng']);
        self::assertSame('Barrio El Centro, Tocoa', $result['location']['address']);
    }

    #[Test]
    public function it_preserves_legacy_flat_latitude_and_longitude_fields(): void
    {
        $tenant = Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertArrayHasKey('latitude', $result);
        self::assertArrayHasKey('longitude', $result);
        self::assertSame(15.4994, $result['latitude']);
        self::assertSame(-88.025, $result['longitude']);
    }

    #[Test]
    public function it_handles_null_coordinates_gracefully(): void
    {
        $tenant = Tenant::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertNull($result['location']['lat']);
        self::assertNull($result['location']['lng']);
    }

    #[Test]
    public function it_builds_service_storefront_payloads_with_services_and_booking_copy(): void
    {
        $tenant = Tenant::factory()->pro()->create([
            'business_type' => 'service',
            'name' => 'Legal Studio HN',
            'description' => 'Asesoria profesional con enfoque practico.',
        ]);

        Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta inicial',
            'duration_minutes' => 60,
            'price' => 750,
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Lopez',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $tenant->load(['services', 'serviceProviders']);

        $result = app(TenantMicrositeTransformer::class)->transform($tenant);

        self::assertSame('service', $result['business_type']);
        self::assertSame('Servicios y citas', $result['storefront_profile']['label']);
        self::assertSame('Agendar consulta', $result['storefront_profile']['primary_cta_label']);
        self::assertCount(1, $result['services']);
        self::assertSame(1, $result['service_providers_count']);
        self::assertSame('Consulta inicial', $result['services'][0]['name']);
    }

    #[Test]
    public function it_builds_food_storefront_payloads_with_menu_overview(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => 'food',
            'name' => 'Sabor Caribe',
        ]);

        $section = MenuSection::unguarded(fn () => MenuSection::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bebidas frias',
            'is_active' => true,
            'sort_order' => 1,
        ]));

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Limonada natural',
            'menu_section_id' => $section->id,
            'is_visible' => true,
        ]);

        $this->actingAsTenant((string) $tenant->id);

        $tenant->load([
            'menuSections' => fn ($query) => $query
                ->where('is_active', true)
                ->withCount(['products' => fn ($productQuery) => $productQuery->where('is_visible', true)])
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);

        $result = app(TenantMicrositeTransformer::class)->transform($tenant);

        self::assertSame('food', $result['business_type']);
        self::assertStringContainsString('platos disponibles', $result['storefront_profile']['catalog_title']);
        self::assertCount(1, $result['menu_sections_overview']);
        self::assertSame('Bebidas frias', $result['menu_sections_overview'][0]['name']);
        self::assertSame(1, $result['menu_sections_overview'][0]['products_count']);
    }

    #[Test]
    public function it_prioritizes_specific_legal_keywords_over_the_generic_services_category(): void
    {
        $category = Category::factory()->create([
            'name' => 'Servicios',
            'slug' => 'servicios',
        ]);

        $tenant = Tenant::factory()->pro()->create([
            'business_type' => 'service',
            'category_id' => $category->id,
            'name' => 'Bufete Lex HN',
            'description' => 'Firma legal para consultas empresariales y familiares.',
            'about' => 'Despacho legal con enfoque en consultas serias y mejor filtradas.',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $tenant->loadMissing('category');

        $result = app(TenantMicrositeTransformer::class)->transform($tenant);

        self::assertSame('law_firm', $result['storefront_profile']['vertical_key']);
        self::assertSame('Firma legal', $result['storefront_profile']['vertical_label']);
        self::assertSame('Agendar consulta', $result['storefront_profile']['primary_cta_label']);
    }

    #[Test]
    public function it_exposes_vertical_playbook_fields_for_consultancy_storefronts(): void
    {
        $category = Category::factory()->create([
            'name' => 'Servicios',
            'slug' => 'servicios',
        ]);

        $tenant = Tenant::factory()->pro()->create([
            'business_type' => 'service',
            'category_id' => $category->id,
            'name' => 'Faro Growth Advisory',
            'description' => 'Consultoria comercial para pymes en crecimiento.',
            'about' => 'Acompana discovery calls, ejecucion y seguimiento comercial.',
        ]);

        $this->actingAsTenant((string) $tenant->id);
        $tenant->loadMissing('category');

        $result = app(TenantMicrositeTransformer::class)->transform($tenant);

        self::assertSame('consultancy', $result['storefront_profile']['vertical_key']);
        self::assertSame('Diagnostica, propone y acompana', $result['storefront_profile']['journey_title']);
        self::assertSame('Discovery calls, sesiones y paquetes de acompanamiento', $result['storefront_profile']['service_panel_title']);
        self::assertCount(3, $result['storefront_profile']['journey_steps']);
        self::assertCount(3, $result['storefront_profile']['growth_cards']);
    }

    #[Test]
    public function it_detects_deep_subverticals_for_specialized_storefronts(): void
    {
        $categories = [
            'restaurantes' => Category::factory()->create(['name' => 'Restaurantes', 'slug' => 'restaurantes']),
            'tecnologia' => Category::factory()->create(['name' => 'Tecnologia', 'slug' => 'tecnologia']),
            'hogar' => Category::factory()->create(['name' => 'Hogar', 'slug' => 'hogar']),
            'salud' => Category::factory()->create(['name' => 'Salud', 'slug' => 'salud']),
            'servicios' => Category::factory()->create(['name' => 'Servicios', 'slug' => 'servicios']),
            'belleza' => Category::factory()->create(['name' => 'Belleza', 'slug' => 'belleza']),
            'automotriz' => Category::factory()->create(['name' => 'Automotriz', 'slug' => 'automotriz']),
        ];

        $cases = [
            ['business_type' => 'food', 'category' => 'restaurantes', 'name' => 'Patio Casual Bistro', 'description' => 'Restaurante casual con burgers y combos de alto ticket.', 'expected_key' => 'casual_restaurant', 'expected_label' => 'Restaurante casual'],
            ['business_type' => 'food', 'category' => 'restaurantes', 'name' => 'Alto Brew Cafeteria', 'description' => 'Cafeteria de especialidad con espresso y brunch.', 'expected_key' => 'coffee_shop', 'expected_label' => 'Cafeteria'],
            ['business_type' => 'retail', 'category' => 'tecnologia', 'name' => 'Titan Flagship Mobile', 'description' => 'Tienda premium con flagships y soporte posventa.', 'expected_key' => 'premium_phone_store', 'expected_label' => 'Tienda de celulares premium'],
            ['business_type' => 'retail', 'category' => 'hogar', 'name' => 'Ferreteria FerreMax HN', 'description' => 'Ferreteria con materiales, pintura y herramientas.', 'expected_key' => 'hardware_store', 'expected_label' => 'Ferreteria'],
            ['business_type' => 'service', 'category' => 'salud', 'name' => 'Clinica Dental Sonrisa', 'description' => 'Clinica dental con ortodoncia y valoracion inicial.', 'expected_key' => 'dental_clinic', 'expected_label' => 'Clinica dental'],
            ['business_type' => 'service', 'category' => 'servicios', 'name' => 'Lex Corporativo HN', 'description' => 'Bufete corporativo con compliance y contratos empresariales.', 'expected_key' => 'corporate_law_firm', 'expected_label' => 'Bufete corporativo'],
            ['business_type' => 'service', 'category' => 'servicios', 'name' => 'Faro Finanzas Advisory', 'description' => 'Consultoria financiera para flujo de caja y rentabilidad.', 'expected_key' => 'financial_consultancy', 'expected_label' => 'Consultoria financiera'],
            ['business_type' => 'service', 'category' => 'belleza', 'name' => 'District Barber Lounge', 'description' => 'Barber lounge premium con grooming premium y slots exclusivos.', 'expected_key' => 'premium_barbershop', 'expected_label' => 'Barber premium'],
            ['business_type' => 'service', 'category' => 'belleza', 'name' => 'Aura Spa Studio', 'description' => 'Spa studio con masajes, faciales y bienestar premium.', 'expected_key' => 'spa_studio', 'expected_label' => 'Spa y bienestar'],
            ['business_type' => 'hybrid', 'category' => 'automotriz', 'name' => 'Repuestos Turbo Center', 'description' => 'Autopartes, baterias y repuestos con diagnostico.', 'expected_key' => 'auto_parts_store', 'expected_label' => 'Tienda de repuestos'],
        ];

        $transformer = app(TenantMicrositeTransformer::class);

        foreach ($cases as $case) {
            $tenant = Tenant::factory()->pro()->create([
                'business_type' => $case['business_type'],
                'category_id' => $categories[$case['category']]->id,
                'name' => $case['name'],
                'description' => $case['description'],
                'about' => $case['description'],
            ]);

            $this->actingAsTenant((string) $tenant->id);
            $tenant->loadMissing('category');

            $result = $transformer->transform($tenant);

            self::assertSame($case['expected_key'], $result['storefront_profile']['vertical_key']);
            self::assertSame($case['expected_label'], $result['storefront_profile']['vertical_label']);
            self::assertNotEmpty($result['storefront_profile']['specialized_blocks']);
            self::assertNotEmpty($result['storefront_profile']['focus_cards']);
        }
    }
}
