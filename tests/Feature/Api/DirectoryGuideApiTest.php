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
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Tenant::factory()->create([
            'name' => 'Tenant Inactivo',
            'slug' => 'tenant-inactivo',
            'status' => 'approved',
            'is_active' => false,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Tenant::factory()->create([
            'name' => 'Tenant Provisional',
            'slug' => 'tenant-provisional',
            'status' => 'pending',
            'is_active' => true,
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

    public function test_directory_search_excludes_rejected_tenants_after_claim_review(): void
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
            'name' => 'Rejected Bistro',
            'slug' => 'rejected-bistro',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_REJECTED,
            'verification_rejection_reason' => 'Evidencia no valida',
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
            'status' => 'approved',
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

        $response = $this->getJson('/api/v1/directory/guide?budget=100&currency=HNL&city=san-pedro-sula&category=tecnologia');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.ranking_mode', 'sql')
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter')
            ->assertJsonPath('meta.budget_currency', 'HNL')
            ->assertJsonPath('meta.budget_typed.currency', 'HNL')
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'sql_budget_filter')
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
            'status' => 'approved',
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
            ->assertJsonPath('meta.budget_currency', 'HNL')
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'directory_search_fallback')
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
            'status' => 'approved',
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

        $response = $this->getJson('/api/v1/directory/guide?budget=120&currency=USD&city=la-ceiba&category=comida&search=pizzeria');

        $response
            ->assertOk()
            ->assertJsonPath('meta.degraded_to_search', false)
            ->assertJsonPath('meta.ranking_mode', 'hybrid')
            ->assertJsonPath('meta.reasoning_basis', 'sql_filter_plus_embedding_rerank')
            ->assertJsonPath('meta.budget_currency', 'USD')
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'sql_filter_plus_embedding_rerank')
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
            'status' => 'approved',
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
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter')
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'sql_budget_filter');
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
            'status' => 'approved',
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
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter')
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'sql_budget_filter');
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
            'status' => 'approved',
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
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'directory_search_fallback')
            ->assertJsonPath('data.0.slug', $tenant->slug);
    }

    public function test_directory_guide_chat_returns_typed_contract_with_trace_id_and_qdrant_metadata(): void
    {
        $this->actingAsCustomer();

        config([
            'directory.guide.qdrant.enabled' => true,
            'directory.guide.qdrant.url' => 'https://qdrant.example.test',
            'directory.guide.qdrant.collection' => 'directory_products',
            'services.openai.api_key' => 'test-openai-key',
        ]);

        $city = City::factory()->create([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
        ]);
        $category = Category::factory()->create([
            'name' => 'Cafe',
            'slug' => 'cafe',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Centro',
            'slug' => 'cafe-centro',
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Latte Clasico',
            'slug' => 'latte-clasico-'.Str::lower(Str::random(6)),
            'price' => 95,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                ],
            ], 200),
            'https://qdrant.example.test/collections/directory_products/points/search' => Http::response([
                'result' => [
                    [
                        'id' => $product->getKey(),
                        'score' => 0.92,
                        'payload' => [
                            'product_id' => $product->getKey(),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson(
            '/api/v1/directory/guide/chat',
            [
                'query' => 'Quiero un cafe bueno y barato',
                'budget_amount' => 120,
                'currency' => 'HNL',
                'city' => 'tegucigalpa',
                'category' => 'cafe',
                'limit' => 5,
            ],
            ['x-trace-id' => 'trace-test-001']
        );

        $response
            ->assertOk()
            ->assertHeader('x-request-id')
            ->assertHeader('x-trace-id', 'trace-test-001')
            ->assertJsonPath('meta.trace_id', 'trace-test-001')
            ->assertJsonPath('meta.query', 'Quiero un cafe bueno y barato')
            ->assertJsonPath('meta.budget_typed.amount', 120)
            ->assertJsonPath('meta.budget_typed.currency', 'HNL')
            ->assertJsonPath('meta.qdrant_applied', true)
            ->assertJsonPath('meta.ranking_mode', 'hybrid')
            ->assertJsonPath('meta.reasoning_basis', 'qdrant_semantic_retrieval_plus_sql_budget_filter')
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'qdrant_semantic_retrieval_plus_sql_budget_filter')
            ->assertJsonStructure([
                'meta' => ['chat_answer'],
            ]);
    }

    public function test_directory_guide_chat_keeps_sql_mode_when_qdrant_is_unavailable(): void
    {
        $this->actingAsCustomer();

        config([
            'directory.guide.qdrant.enabled' => true,
            'directory.guide.qdrant.url' => 'https://qdrant.example.test',
            'directory.guide.qdrant.collection' => 'directory_products',
            'services.openai.api_key' => 'test-openai-key',
            'directory.guide.hybrid_rerank_enabled' => false,
        ]);

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                ],
            ], 200),
            'https://qdrant.example.test/collections/directory_products/points/search' => Http::response([
                'status' => 'error',
            ], 503),
        ]);

        $city = City::factory()->create([
            'name' => 'Choluteca',
            'slug' => 'choluteca',
        ]);
        $category = Category::factory()->create([
            'name' => 'Panaderias',
            'slug' => 'panaderias',
            'is_active' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'name' => 'Pan del Sur',
            'slug' => 'pan-del-sur',
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Pan de chocolate',
            'slug' => 'pan-chocolate-'.Str::lower(Str::random(6)),
            'price' => 40,
            'stock_status' => StockStatus::InStock,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'query' => 'Pan',
            'budget_amount' => 120,
            'currency' => 'HNL',
            'city' => 'choluteca',
            'category' => 'panaderias',
            'limit' => 5,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.ranking_mode', 'sql')
            ->assertJsonPath('meta.reasoning_basis', 'sql_budget_filter')
            ->assertJsonPath('meta.degraded_reason', 'qdrant_unavailable')
            ->assertJsonPath('meta.qdrant_applied', false)
            ->assertJsonPath('meta.ranking_explanation.reasoning_basis', 'sql_budget_filter');
    }

    public function test_directory_guide_requires_authentication(): void
    {
        $this->getJson('/api/v1/directory/guide?budget=100')
            ->assertUnauthorized();

        $this->postJson('/api/v1/directory/guide/chat', [
            'query' => 'quiero comida',
            'budget_amount' => 100,
            'currency' => 'HNL',
        ])->assertUnauthorized();
    }

    public function test_directory_guide_forbids_non_customer_roles(): void
    {
        foreach ([UserRole::TENANT_OWNER, UserRole::STAFF, UserRole::SUPER_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/directory/guide?budget=100')
                ->assertForbidden()
                ->assertJsonPath('error_code', 'DIRECTORY_GUIDE_CUSTOMER_ONLY');

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/directory/guide/chat', [
                    'query' => 'quiero opciones',
                    'budget_amount' => 100,
                    'currency' => 'HNL',
                ])
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
            'status' => 'approved',
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
