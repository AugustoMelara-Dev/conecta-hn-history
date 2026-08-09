<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\EmbeddingResult;
use App\Application\Ai\Knowledge\EmbeddingUsageMeter;
use App\Application\Ai\Knowledge\KnowledgeQueryIngressGuard;
use App\Application\Ai\Knowledge\SemanticKnowledgeRetriever;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\SensitivePayloadRedactor;
use App\Infrastructure\AI\Knowledge\EloquentKnowledgeIndexRepository;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
use App\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SemanticKnowledgeRetrieverIngressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'ai.knowledge.enabled' => true,
            'ai.knowledge.embedding_dimensions' => 3,
            'ai.knowledge.minimum_similarity' => -1,
            'ai.knowledge.metering_enabled' => false,
        ]);
    }

    public function test_public_prohibited_query_has_zero_embedding_budget_and_index_sinks(): void
    {
        $billing = Tenant::factory()->create(['ai_balance' => 1]);
        config()->set([
            'ai.public.billing_tenant_id' => (string) $billing->id,
            'ai.knowledge.metering_enabled' => true,
            'ai.knowledge.max_cost_usd' => 0.001,
            'ai.knowledge.input_usd_per_million' => 0.02,
        ]);
        $provider = new QueryRecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);
        $knowledgeQueries = $this->recordKnowledgeQueries();

        $results = app(SemanticKnowledgeRetriever::class)->searchPublic(
            '  patient   diagnosis with card_number 4111 1111 1111 1111  ',
        );

        self::assertSame([], $results);
        self::assertSame([], $provider->contents);
        self::assertSame([], $knowledgeQueries());
        self::assertSame(0, DB::table('ai_credit_reservations')->count());
    }

    public function test_private_missing_business_context_returns_empty_before_all_sinks(): void
    {
        $tenant = Tenant::factory()->create(['business_type' => 'retail', 'ai_balance' => 1]);
        DB::table('tenants')->where('id', $tenant->id)->update(['business_type' => 'invalid-authority']);
        $provider = new QueryRecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);
        $knowledgeQueries = $this->recordKnowledgeQueries();

        $results = app(SemanticKnowledgeRetriever::class)->searchTenant(
            'consulta privada segura',
            (string) $tenant->id,
        );

        self::assertSame([], $results);
        self::assertSame([], $provider->contents);
        self::assertSame([], $knowledgeQueries());
        self::assertSame(0, DB::table('ai_credit_reservations')->count());
    }

    public function test_guard_exception_and_malformed_redaction_return_empty_without_sinks(): void
    {
        $billing = Tenant::factory()->create(['ai_balance' => 1]);
        config()->set('ai.public.billing_tenant_id', (string) $billing->id);
        $provider = new QueryRecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);

        foreach ([
            new QueryIngressRedactorStub(throwOnScrub: true),
            new QueryIngressRedactorStub(static fn (array $payload): array => $payload + ['unexpected' => 'RAW_QUERY_EXTRA']),
            new QueryIngressRedactorStub(static fn (): array => ['query' => str_repeat('x', 501)]),
        ] as $redactor) {
            $guard = new KnowledgeQueryIngressGuard(
                new DovraDataIngressGuard(
                    new DataClassificationPolicy($redactor),
                    new DovraIntegrationSeamRegistry,
                ),
                new DovraTenantBusinessContextResolver,
            );
            $retriever = new SemanticKnowledgeRetriever(
                app(EmbeddingUsageMeter::class),
                app(EloquentKnowledgeIndexRepository::class),
                $guard,
            );
            $knowledgeQueries = $this->recordKnowledgeQueries();

            self::assertSame([], $retriever->searchPublic('consulta segura'));
            self::assertSame([], $provider->contents);
            self::assertSame([], $knowledgeQueries());
            self::assertSame(0, DB::table('ai_credit_reservations')->count());
        }
    }

    public function test_allowed_public_search_embeds_only_normalized_redacted_query(): void
    {
        $billing = Tenant::factory()->create();
        $source = Tenant::factory()->create();
        config()->set('ai.public.billing_tenant_id', (string) $billing->id);
        $provider = new QueryRecordingEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $provider);
        app(EloquentKnowledgeIndexRepository::class)->upsert(
            (string) $source->id,
            'product',
            'redacted-result',
            'public',
            'Resultado publico seguro.',
            [1.0, 0.0, 0.0],
            'test-embedding',
        );
        $guard = new KnowledgeQueryIngressGuard(
            new DovraDataIngressGuard(
                new DataClassificationPolicy(new QueryIngressRedactorStub(
                    static function (array $payload): array {
                        self::assertSame('consulta RAW original', $payload['query']);

                        return ['query' => 'consulta redactada'];
                    },
                )),
                new DovraIntegrationSeamRegistry,
            ),
            new DovraTenantBusinessContextResolver,
        );
        $retriever = new SemanticKnowledgeRetriever(
            app(EmbeddingUsageMeter::class),
            app(EloquentKnowledgeIndexRepository::class),
            $guard,
        );

        $results = $retriever->searchPublic('  consulta   RAW original  ');

        self::assertSame(['consulta redactada'], $provider->contents);
        self::assertSame(['redacted-result'], array_column($results, 'resource_id'));
        self::assertNotContains('RAW original', $provider->contents);
    }

    /** @return \Closure(): list<string> */
    private function recordKnowledgeQueries(): \Closure
    {
        $queries = new \ArrayObject;
        DB::listen(static function (QueryExecuted $query) use ($queries): void {
            if (str_contains(strtolower($query->sql), 'ai_knowledge_chunks')) {
                $queries->append($query->sql);
            }
        });

        return static fn (): array => array_values((array) $queries);
    }
}

final class QueryRecordingEmbeddingProvider implements EmbeddingProvider
{
    /** @var list<string> */
    public array $contents = [];

    public function embed(string $content): EmbeddingResult
    {
        $this->contents[] = $content;

        return new EmbeddingResult([1.0, 0.0, 0.0], 'test-embedding', 2);
    }
}

final class QueryIngressRedactorStub implements SensitivePayloadRedactor
{
    public function __construct(
        private readonly mixed $replacement = null,
        private readonly bool $throwOnScrub = false,
    ) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function scrub(array $payload): array
    {
        if ($this->throwOnScrub) {
            throw new \RuntimeException('RAW_QUERY_GUARD_FAILURE');
        }

        return is_callable($this->replacement) ? ($this->replacement)($payload) : $payload;
    }
}
