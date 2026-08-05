<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\CatalogKnowledgeIndexer;
use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\EmbeddingResult;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CatalogKnowledgeIndexerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_indexes_public_and_private_catalog_evidence_and_prunes_deleted_resources(): void
    {
        config()->set('ai.knowledge.enabled', false);
        config()->set('ai.knowledge.embedding_dimensions', 3);
        $provider = new RecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);

        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Seguro',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        $public = Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe <script>alert(1)</script>',
            'description' => 'Tostado local',
            'is_active' => true,
            'is_visible' => true,
        ]);
        $private = Product::factory()->forTenant($tenant)->create([
            'name' => 'Producto borrador',
            'is_active' => true,
            'is_visible' => false,
        ]);
        Service::factory()->for($tenant)->create([
            'name' => 'Cata guiada',
            'is_active' => true,
        ]);
        config()->set('ai.knowledge.enabled', true);

        $indexed = app(CatalogKnowledgeIndexer::class)->indexTenant((string) $tenant->getKey());

        self::assertSame(3, $indexed);
        $this->assertDatabaseHas('ai_knowledge_chunks', [
            'tenant_id' => (string) $tenant->getKey(),
            'resource_type' => 'product',
            'resource_id' => (string) $public->getKey(),
            'visibility' => 'public',
        ]);
        $this->assertDatabaseHas('ai_knowledge_chunks', [
            'tenant_id' => (string) $tenant->getKey(),
            'resource_type' => 'product',
            'resource_id' => (string) $private->getKey(),
            'visibility' => 'private',
        ]);
        self::assertStringNotContainsString('<script>', (string) DB::table('ai_knowledge_chunks')
            ->where('resource_id', (string) $public->getKey())
            ->value('content'));

        config()->set('ai.knowledge.enabled', false);
        $private->delete();
        config()->set('ai.knowledge.enabled', true);
        app(CatalogKnowledgeIndexer::class)->indexTenant((string) $tenant->getKey());

        $this->assertDatabaseMissing('ai_knowledge_chunks', [
            'tenant_id' => (string) $tenant->getKey(),
            'resource_type' => 'product',
            'resource_id' => (string) $private->getKey(),
        ]);
        self::assertCount(5, $provider->contents);
    }
}

final class RecordingEmbeddingProvider implements EmbeddingProvider
{
    /** @var list<string> */
    public array $contents = [];

    public function embed(string $content): EmbeddingResult
    {
        $this->contents[] = $content;

        return new EmbeddingResult([0.1, 0.2, 0.3], 'test-embedding', 3);
    }
}
