<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Identity\Enums\UserRole;
use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DirectoryGuideApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_search_returns_only_published_tenants(): void
    {
        $city = City::factory()->create([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
        ]);
        $category = Category::factory()->create([
            'name' => 'Restaurantes',
            'slug' => 'restaurantes',
            'is_active' => true,
        ]);

        $published = Tenant::factory()->create([
            'name' => 'Casa Brasa',
            'slug' => 'casa-brasa',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Tenant::factory()->create([
            'name' => 'Tenant Inactivo',
            'slug' => 'tenant-inactivo',
            'is_active' => false,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $response = $this->getJson('/api/v1/directory/search?city=tegucigalpa&category=restaurantes&limit=20');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.ranking_mode', 'sql')
            ->assertJsonPath('meta.reasoning_basis', 'sql_directory_lookup')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $published->slug)
            ->assertJsonPath('data.0.microsite_path', '/t/'.$published->slug);
    }

    public function test_directory_search_excludes_provisional_tenants_until_claim_is_verified(): void
    {
        $city = City::factory()->create([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
        ]);
        $category = Category::factory()->create([
            'name' => 'Restaurantes',
            'slug' => 'restaurantes',
            'is_active' => true,
        ]);

        Tenant::factory()->create([
            'name' => 'Provisional Bistro',
            'slug' => 'provisional-bistro',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'verified_at' => null,
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $response = $this->getJson('/api/v1/directory/search?city=tegucigalpa&category=restaurantes&limit=20');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_directory_search_excludes_verified_tenants_when_publication_is_paused(): void
    {
        $city = City::factory()->create([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
        ]);
        $category = Category::factory()->create([
            'name' => 'Restaurantes',
            'slug' => 'restaurantes',
            'is_active' => true,
        ]);

        Tenant::factory()->create([
            'name' => 'Bistro En Disputa',
            'slug' => 'bistro-en-disputa',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => now(),
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $response = $this->getJson('/api/v1/directory/search?city=tegucigalpa&category=restaurantes&limit=20');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_directory_guide_recommends_only_available_products_within_budget(): void
    {
        $this->actingAsCustomer();

        $city = City::factory()->create([
            'name' => 'San Pedro Sula',
            'slug' => 'san-pedro-sula',
        ]);
        $category = Category::factory()->create([
            'name' => 'Tecnologia',
            'slug' => 'tecnologia',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Tecno Norte',
            'slug' => 'tecno-norte',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Mouse Gamer',
            'slug' => 'mouse-gamer-'.Str::lower(Str::random(6)),
            'price' => 80,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Teclado Premium',
            'slug' => 'teclado-premium-'.Str::lower(Str::random(6)),
            'price' => 120,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Auriculares Agotados',
            'slug' => 'auriculares-agotados-'.Str::lower(Str::random(6)),
            'price' => 60,
            'stock_status' => StockStatus::OutOfStock,
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/directory/guide?budget=100&city=san-pedro-sula&category=tecnologia');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.ranking_mode', 'sql')
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter')
            ->assertJsonPath('meta.quota_limit', 5)
            ->assertJsonPath('meta.quota_used', 1)
            ->assertJsonPath('meta.quota_remaining', 4);

        $data = collect((array) $response->json('data'));
        $products = $data->flatMap(static fn (array $entry): array => (array) ($entry['products'] ?? []));

        $this->assertNotEmpty($products->all());
        $this->assertTrue($products->every(static fn (array $product): bool => (float) $product['price'] <= 100));
        $this->assertTrue($products->every(static fn (array $product): bool => $product['stock_status'] !== StockStatus::OutOfStock->value));
    }

    public function test_directory_guide_falls_back_to_search_when_budget_is_missing(): void
    {
        $this->actingAsCustomer();

        $city = City::factory()->create([
            'name' => 'Comayagua',
            'slug' => 'comayagua',
        ]);
        $category = Category::factory()->create([
            'name' => 'Servicios',
            'slug' => 'servicios',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Consultoria Centro',
            'slug' => 'consultoria-centro',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $response = $this->getJson('/api/v1/directory/guide?city=comayagua&category=servicios');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', true)
            ->assertJsonPath('meta.degraded_reason', 'missing_budget')
            ->assertJsonPath('meta.ranking_mode', 'fallback')
            ->assertJsonPath('meta.reasoning_basis', 'directory_search_fallback')
            ->assertJsonPath('data.0.slug', $tenant->slug);
    }

    public function test_directory_guide_fallback_excludes_paused_tenants_from_public_results(): void
    {
        $this->actingAsCustomer();

        $city = City::factory()->create([
            'name' => 'Comayagua',
            'slug' => 'comayagua',
        ]);
        $category = Category::factory()->create([
            'name' => 'Servicios',
            'slug' => 'servicios',
            'is_active' => true,
        ]);

        Tenant::factory()->create([
            'name' => 'Consultoria En Disputa',
            'slug' => 'consultoria-en-disputa',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => now(),
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $response = $this->getJson('/api/v1/directory/guide?city=comayagua&category=servicios');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', true)
            ->assertJsonPath('meta.degraded_reason', 'missing_budget')
            ->assertJsonCount(0, 'data');
    }

    public function test_directory_guide_uses_hybrid_ranking_mode_when_feature_flag_is_enabled(): void
    {
        $this->actingAsCustomer();

        config([
            'directory.guide.hybrid_rerank_enabled' => true,
            'services.openai.api_key' => 'test-openai-key',
        ]);

        Http::fake([
            'https://api.openai.com/v1/embeddings' => static function (HttpRequest $request) {
                $payload = $request->data();
                $inputs = (array) ($payload['input'] ?? []);
                $data = [];

                foreach (array_values($inputs) as $index => $_input) {
                    $data[] = [
                        'index' => $index,
                        'embedding' => [0.8, 0.1, 0.1],
                    ];
                }

                return Http::response(['data' => $data], 200);
            },
        ]);

        $city = City::factory()->create([
            'name' => 'La Ceiba',
            'slug' => 'la-ceiba',
        ]);
        $category = Category::factory()->create([
            'name' => 'Comida',
            'slug' => 'comida',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Pizzeria Centro',
            'slug' => 'pizzeria-centro',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Pizza Margarita',
            'slug' => 'pizza-margarita-'.Str::lower(Str::random(6)),
            'price' => 95,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/directory/guide?budget=120&city=la-ceiba&category=comida&search=pizzeria');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.ranking_mode', 'hybrid')
            ->assertJsonPath('meta.reasoning_basis', 'sql_filter_plus_embedding_rerank')
            ->assertHeader('x-request-id')
            ->assertJsonStructure([
                'meta' => ['request_id'],
            ]);
    }

    public function test_directory_guide_keeps_sql_results_when_openai_auth_fails(): void
    {
        $this->actingAsCustomer();

        config([
            'directory.guide.hybrid_rerank_enabled' => true,
            'services.openai.api_key' => null,
        ]);

        $city = City::factory()->create([
            'name' => 'Choluteca',
            'slug' => 'choluteca',
        ]);
        $category = Category::factory()->create([
            'name' => 'Salud',
            'slug' => 'salud',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Clinica Sur',
            'slug' => 'clinica-sur',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Consulta General',
            'slug' => 'consulta-general-'.Str::lower(Str::random(6)),
            'price' => 90,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/directory/guide?budget=120&city=choluteca&category=salud&search=clinica');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.degraded_reason', 'openai_auth_failed')
            ->assertJsonPath('meta.ranking_mode', 'sql')
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter');
    }

    public function test_directory_guide_keeps_sql_results_when_openai_times_out(): void
    {
        $this->actingAsCustomer();

        config([
            'directory.guide.hybrid_rerank_enabled' => true,
            'services.openai.api_key' => 'test-openai-key',
            'directory.guide.openai_max_retries' => 0,
        ]);

        Http::fake([
            'https://api.openai.com/v1/embeddings' => static function () {
                throw new \RuntimeException('simulated_timeout');
            },
        ]);

        $city = City::factory()->create([
            'name' => 'Danli',
            'slug' => 'danli',
        ]);
        $category = Category::factory()->create([
            'name' => 'Cafe',
            'slug' => 'cafe',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Danli',
            'slug' => 'cafe-danli',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Capuccino',
            'slug' => 'capuccino-'.Str::lower(Str::random(6)),
            'price' => 80,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/directory/guide?budget=120&city=danli&category=cafe&search=capuccino');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.degraded_reason', 'openai_timeout')
            ->assertJsonPath('meta.ranking_mode', 'sql')
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter');
    }

    public function test_directory_guide_falls_back_when_budget_has_no_matches(): void
    {
        $this->actingAsCustomer();

        $city = City::factory()->create([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
        ]);
        $category = Category::factory()->create([
            'name' => 'Restaurantes',
            'slug' => 'restaurantes',
            'is_active' => true,
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'Casa Brasa Bistro',
            'slug' => 'casa-brasa-bistro',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Parrillada Premium',
            'slug' => 'parrillada-premium-'.Str::lower(Str::random(6)),
            'price' => 600,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/directory/guide?budget=50&city=tegucigalpa&category=restaurantes');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', true)
            ->assertJsonPath('meta.degraded_reason', 'no_budget_matches')
            ->assertJsonPath('meta.ranking_mode', 'fallback')
            ->assertJsonPath('meta.reasoning_basis', 'directory_search_fallback')
            ->assertJsonPath('data.0.slug', $tenant->slug);
    }

    public function test_directory_guide_requires_authentication(): void
    {
        $this->getJson('/api/v1/directory/guide?budget=100')
            ->assertUnauthorized();
    }

    public function test_directory_guide_forbids_non_customer_roles(): void
    {
        foreach ([UserRole::TENANT_OWNER, UserRole::STAFF, UserRole::SUPER_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/directory/guide?budget=100')
                ->assertForbidden()
                ->assertJsonPath('error_code', 'DIRECTORY_GUIDE_CUSTOMER_ONLY');
        }
    }

    public function test_directory_guide_enforces_daily_quota_for_customer(): void
    {
        $this->actingAsCustomer();

        $city = City::factory()->create([
            'name' => 'Siguatepeque',
            'slug' => 'siguatepeque',
        ]);
        $category = Category::factory()->create([
            'name' => 'Panaderias',
            'slug' => 'panaderias',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Pan Centro',
            'slug' => 'pan-centro',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Pan dulce',
            'slug' => 'pan-dulce-'.Str::lower(Str::random(6)),
            'price' => 45,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->getJson('/api/v1/directory/guide?budget=100&city=siguatepeque&category=panaderias')
                ->assertOk();
        }

        $this->getJson('/api/v1/directory/guide?budget=100&city=siguatepeque&category=panaderias')
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'DIRECTORY_GUIDE_DAILY_LIMIT_EXCEEDED')
            ->assertJsonPath('quota_limit', 5)
            ->assertJsonPath('quota_remaining', 0)
            ->assertJsonStructure(['retry_after_seconds', 'reset_at']);
    }

    private function actingAsCustomer(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);

        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
