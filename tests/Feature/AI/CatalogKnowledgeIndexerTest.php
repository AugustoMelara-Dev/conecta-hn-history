<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\CatalogKnowledgeIndexer;
use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\EmbeddingResult;
use App\Application\Ai\Knowledge\EmbeddingUsageMeter;
use App\Application\Ai\Knowledge\KnowledgeIngressGuard;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\SensitivePayloadRedactor;
use App\Infrastructure\AI\Knowledge\EloquentKnowledgeIndexRepository;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
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

    public function test_prohibited_resource_is_not_retained_and_has_zero_embedding_budget_or_upsert_sinks(): void
    {
        config()->set([
            'ai.knowledge.enabled' => false,
            'ai.knowledge.embedding_dimensions' => 3,
            'ai.knowledge.metering_enabled' => false,
        ]);
        $provider = new RecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);
        $tenant = Tenant::factory()->create([
            'name' => 'Catalogo Seguro',
            'business_type' => 'retail',
            'ai_balance' => 1,
        ]);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Cafe seguro']);
        config()->set('ai.knowledge.enabled', true);

        self::assertSame(1, app(CatalogKnowledgeIndexer::class)->indexTenant((string) $tenant->id));
        $this->assertDatabaseHas('ai_knowledge_chunks', ['resource_id' => (string) $product->id]);
        $provider->contents = [];
        $reservations = DB::table('ai_credit_reservations')->count();
        $product->forceFill(['name' => 'sk-private-knowledge-secret'])->save();
        config()->set([
            'ai.knowledge.metering_enabled' => true,
            'ai.knowledge.max_cost_usd' => 0.001,
            'ai.knowledge.input_usd_per_million' => 0.02,
        ]);

        self::assertSame(0, app(CatalogKnowledgeIndexer::class)->indexTenant((string) $tenant->id));

        self::assertSame([], $provider->contents);
        self::assertSame($reservations, DB::table('ai_credit_reservations')->count());
        $this->assertDatabaseMissing('ai_knowledge_chunks', [
            'tenant_id' => (string) $tenant->id,
            'resource_type' => 'product',
            'resource_id' => (string) $product->id,
        ]);
    }

    public function test_guard_failure_malformed_redaction_and_authority_spoof_fail_generically_before_sinks(): void
    {
        config()->set([
            'ai.knowledge.enabled' => false,
            'ai.knowledge.embedding_dimensions' => 3,
        ]);
        $provider = new RecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);
        $tenant = Tenant::factory()->create(['name' => 'Autoridad Canonica', 'business_type' => 'retail']);
        Product::factory()->forTenant($tenant)->create(['name' => 'Contenido original']);
        config()->set('ai.knowledge.enabled', true);

        foreach ([
            new KnowledgeIngressRedactorStub(throwOnScrub: true),
            new KnowledgeIngressRedactorStub(static fn (array $payload): array => $payload + ['unexpected' => 'RAW_EXTRA']),
            new KnowledgeIngressRedactorStub(static fn (array $payload): array => array_replace($payload, [
                'resource_id' => 'spoofed-resource',
            ])),
        ] as $redactor) {
            $guard = new KnowledgeIngressGuard(
                new DovraDataIngressGuard(
                    new DataClassificationPolicy($redactor),
                    new DovraIntegrationSeamRegistry,
                ),
                new DovraTenantBusinessContextResolver,
            );
            $indexer = new CatalogKnowledgeIndexer(
                app(EmbeddingUsageMeter::class),
                app(EloquentKnowledgeIndexRepository::class),
                $guard,
            );

            try {
                $indexer->indexTenant((string) $tenant->id);
                self::fail('Unavailable knowledge ingress unexpectedly reached a sink.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Knowledge ingress validation is unavailable.', $exception->getMessage());
            }

            self::assertSame([], $provider->contents);
            self::assertSame(0, DB::table('ai_credit_reservations')->count());
            self::assertSame(0, DB::table('ai_knowledge_chunks')->count());
        }
    }

    public function test_allowed_resource_embeds_only_redacted_content_and_metadata_business_name(): void
    {
        config()->set([
            'ai.knowledge.enabled' => false,
            'ai.knowledge.embedding_dimensions' => 3,
        ]);
        $provider = new RecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);
        $tenant = Tenant::factory()->create(['name' => 'Nombre original', 'business_type' => 'retail']);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Contenido original',
            'is_visible' => false,
        ]);
        config()->set('ai.knowledge.enabled', true);
        $redactor = new KnowledgeIngressRedactorStub(static fn (array $payload): array => array_replace($payload, [
            'content' => 'Contenido redactado',
            'business_name' => 'Negocio redactado',
        ]));
        $guard = new KnowledgeIngressGuard(
            new DovraDataIngressGuard(new DataClassificationPolicy($redactor), new DovraIntegrationSeamRegistry),
            new DovraTenantBusinessContextResolver,
        );
        $indexer = new CatalogKnowledgeIndexer(
            app(EmbeddingUsageMeter::class),
            app(EloquentKnowledgeIndexRepository::class),
            $guard,
        );

        self::assertSame(1, $indexer->indexTenant((string) $tenant->id));

        self::assertSame(['Contenido redactado'], $provider->contents);
        $chunk = DB::table('ai_knowledge_chunks')->where('resource_id', (string) $product->id)->first();
        self::assertNotNull($chunk);
        self::assertSame('product', $chunk->resource_type);
        self::assertSame('private', $chunk->visibility);
        self::assertSame('Contenido redactado', $chunk->content);
        self::assertSame(hash('sha256', 'Contenido redactado'), $chunk->content_hash);
        self::assertSame('Negocio redactado', json_decode((string) $chunk->metadata, true, 512, JSON_THROW_ON_ERROR)['business_name']);
        self::assertStringNotContainsString('Contenido original', (string) $chunk->content);
        self::assertStringNotContainsString('Nombre original', (string) $chunk->metadata);
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

final class KnowledgeIngressRedactorStub implements SensitivePayloadRedactor
{
    public function __construct(
        private readonly mixed $replacement = null,
        private readonly bool $throwOnScrub = false,
    ) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function scrub(array $payload): array
    {
        if ($this->throwOnScrub) {
            throw new \RuntimeException('RAW_KNOWLEDGE_GUARD_FAILURE');
        }

        return is_callable($this->replacement) ? ($this->replacement)($payload) : $payload;
    }
}
