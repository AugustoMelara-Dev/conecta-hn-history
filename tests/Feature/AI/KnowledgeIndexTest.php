<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\EmbeddingResult;
use App\Application\Ai\Knowledge\KnowledgeRetriever;
use App\Infrastructure\AI\Knowledge\EloquentKnowledgeIndexRepository;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KnowledgeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exposes_a_tenant_aware_vector_knowledge_index(): void
    {
        self::assertTrue(Schema::hasTable('ai_knowledge_chunks'));

        foreach ([
            'id',
            'tenant_id',
            'resource_type',
            'resource_id',
            'visibility',
            'content',
            'content_hash',
            'embedding',
            'embedding_model',
            'metadata',
            'indexed_at',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('ai_knowledge_chunks', $column), "Missing {$column}.");
        }
    }

    public function test_search_returns_public_and_same_tenant_private_evidence_without_cross_tenant_leakage(): void
    {
        config()->set('ai.knowledge.embedding_dimensions', 3);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $repository = app(EloquentKnowledgeIndexRepository::class);

        $repository->upsert(
            (string) $tenantA->getKey(),
            'product',
            'public-a',
            'public',
            'Cafe tostado publico',
            [1.0, 0.0, 0.0],
            'test-embedding',
        );
        $repository->upsert(
            (string) $tenantA->getKey(),
            'service',
            'private-a',
            'private',
            'Servicio privado A',
            [0.9, 0.1, 0.0],
            'test-embedding',
        );
        $repository->upsert(
            (string) $tenantB->getKey(),
            'service',
            'private-b',
            'private',
            'Servicio privado B',
            [1.0, 0.0, 0.0],
            'test-embedding',
        );

        $tenantResults = $repository->search([1.0, 0.0, 0.0], (string) $tenantA->getKey(), true, 10);
        self::assertSame(['public-a', 'private-a'], array_column($tenantResults, 'resource_id'));

        $publicResults = $repository->search([1.0, 0.0, 0.0], null, true, 10);
        self::assertSame(['public-a'], array_column($publicResults, 'resource_id'));
        self::assertNotContains('private-b', array_column($tenantResults, 'resource_id'));
    }

    public function test_upsert_replaces_stale_resource_content_instead_of_accumulating_duplicates(): void
    {
        config()->set('ai.knowledge.embedding_dimensions', 3);

        $tenant = Tenant::factory()->create();
        $repository = app(EloquentKnowledgeIndexRepository::class);
        $tenantId = (string) $tenant->getKey();

        $repository->upsert($tenantId, 'product', 'same-id', 'public', 'Old content', [1.0, 0.0, 0.0], 'test');
        $repository->upsert($tenantId, 'product', 'same-id', 'public', 'Current content', [0.0, 1.0, 0.0], 'test');

        self::assertSame(1, DB::table('ai_knowledge_chunks')
            ->where('tenant_id', $tenantId)
            ->where('resource_type', 'product')
            ->where('resource_id', 'same-id')
            ->count());
        self::assertSame('Current content', DB::table('ai_knowledge_chunks')->value('content'));
    }

    public function test_private_retrieval_ranks_only_public_and_private_chunks_owned_by_the_tenant(): void
    {
        config()->set('ai.knowledge.enabled', true);
        config()->set('ai.knowledge.metering_enabled', false);
        config()->set('ai.knowledge.embedding_dimensions', 3);
        config()->set('ai.knowledge.minimum_similarity', 0.0);

        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $repository = app(EloquentKnowledgeIndexRepository::class);
        $repository->upsert(
            (string) $foreign->getKey(),
            'product',
            'foreign-public-best',
            'public',
            'Foreign public result must not enter tenant ranking.',
            [1.0, 0.0, 0.0],
            'test-embedding',
        );
        $repository->upsert(
            (string) $tenant->getKey(),
            'product',
            'tenant-public',
            'public',
            'Tenant public result.',
            [0.9, 0.1, 0.0],
            'test-embedding',
        );
        $repository->upsert(
            (string) $tenant->getKey(),
            'service',
            'tenant-private',
            'private',
            'Tenant private result.',
            [0.8, 0.2, 0.0],
            'test-embedding',
        );
        $this->app->instance(
            EmbeddingProvider::class,
            new ScopedTenantEmbeddingProvider([1.0, 0.0, 0.0]),
        );

        $results = app(KnowledgeRetriever::class)->searchTenant(
            'tenant catalog',
            (string) $tenant->getKey(),
            2,
        );

        self::assertSame(
            ['tenant-public', 'tenant-private'],
            array_column($results, 'resource_id'),
        );
        self::assertSame(
            [(string) $tenant->getKey(), (string) $tenant->getKey()],
            array_column($results, 'source_tenant_id'),
        );
        self::assertNotContains('foreign-public-best', array_column($results, 'resource_id'));
    }
}

final readonly class ScopedTenantEmbeddingProvider implements EmbeddingProvider
{
    /** @param list<float> $embedding */
    public function __construct(private array $embedding) {}

    public function embed(string $content): EmbeddingResult
    {
        return new EmbeddingResult($this->embedding, 'test-embedding', 1);
    }
}
