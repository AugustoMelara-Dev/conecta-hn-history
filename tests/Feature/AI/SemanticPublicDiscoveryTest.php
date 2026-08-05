<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\KnowledgeRetriever;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class SemanticPublicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_semantic_candidates_are_rehydrated_through_public_catalog_rules(): void
    {
        config()->set('ai.enabled', false);

        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);

        $tenant = Tenant::factory()->create([
            'name' => 'Energia Local',
            'slug' => 'energia-local',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        GlobalMarketFixture::assignCanada($tenant);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe molido',
            'description' => 'Tueste intenso',
            'price' => 120,
            'price_minor' => 12000,
            'currency' => 'CAD',
            'is_active' => true,
            'is_visible' => true,
        ]);

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $this->app->instance(KnowledgeRetriever::class, new FixedKnowledgeRetriever([[
            'resource_type' => 'product',
            'resource_id' => (string) $product->getKey(),
            'similarity' => 0.92,
        ]]));

        $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'algo estimulante para iniciar el dia',
        ])
            ->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonFragment([
                'evidence_id' => 'product:'.$product->getKey(),
                'name' => 'Cafe molido',
            ]);
    }
}

final readonly class FixedKnowledgeRetriever implements KnowledgeRetriever
{
    /** @param list<array{resource_type:string,resource_id:string,similarity:float}> $results */
    public function __construct(private array $results) {}

    public function searchPublic(string $query, int $limit = 12): array
    {
        return array_slice($this->results, 0, $limit);
    }

    public function searchTenant(string $query, string $tenantId, int $limit = 12): array
    {
        return array_slice($this->results, 0, $limit);
    }
}
