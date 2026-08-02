<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\KnowledgeRetriever;
use App\Infrastructure\AI\EloquentTenantAgentReader;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class SemanticPrivateAgentReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_catalog_rehydrates_only_semantic_candidates_inside_the_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenant);
        Product::factory()->count(10)->forTenant($tenant)->create();
        $selected = Product::factory()->forTenant($tenant)->create(['name' => 'Cafe energetico']);
        $foreign = Product::factory()->forTenant($other)->create(['name' => 'SECRETO AJENO']);

        $this->app->instance(KnowledgeRetriever::class, new TenantFixedKnowledgeRetriever([
            [
                'resource_type' => 'product',
                'resource_id' => (string) $foreign->getKey(),
                'similarity' => 0.99,
            ],
            [
                'resource_type' => 'product',
                'resource_id' => (string) $selected->getKey(),
                'similarity' => 0.93,
            ],
        ]));

        $response = app(EloquentTenantAgentReader::class)->answer(
            (string) $tenant->getKey(),
            'mostrame el catalogo de algo para tener energia',
        );

        self::assertSame('product:'.$selected->getKey(), $response['data']['items'][0]['id']);
        self::assertCount(1, $response['data']['items']);
        self::assertStringNotContainsString('SECRETO AJENO', json_encode($response, JSON_THROW_ON_ERROR));
    }
}

final readonly class TenantFixedKnowledgeRetriever implements KnowledgeRetriever
{
    /** @param list<array{resource_type:string,resource_id:string,similarity:float}> $results */
    public function __construct(private array $results) {}

    public function searchPublic(string $query, int $limit = 12): array
    {
        return [];
    }

    public function searchTenant(string $query, string $tenantId, int $limit = 12): array
    {
        return array_slice($this->results, 0, $limit);
    }
}
