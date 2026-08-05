<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\EmbeddingResult;
use App\Application\Ai\Knowledge\EmbeddingUsageMeter;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmbeddingUsageMeterTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_embedding_is_reserved_settled_and_audited(): void
    {
        config()->set([
            'ai.knowledge.metering_enabled' => true,
            'ai.knowledge.max_cost_usd' => 0.001,
            'ai.knowledge.input_usd_per_million' => 0.02,
            'ai.commercial_credits.credits_per_provider_usd' => 20,
            'ai.commercial_credits.minimum_charge' => 0.01,
        ]);
        $tenant = Tenant::factory()->create([
            'ai_balance' => 1,
            'ai_reserved_balance' => 0,
        ]);
        $this->app->instance(EmbeddingProvider::class, new MeteredFakeEmbeddingProvider(false));

        $result = app(EmbeddingUsageMeter::class)->embed(
            (string) $tenant->getKey(),
            'knowledge.query.private',
            'consulta segura',
        );

        self::assertSame(3, $result->inputTokens);
        self::assertEqualsWithDelta(0.99, (float) $tenant->fresh()->ai_balance, 0.000001);
        $this->assertDatabaseHas('ai_credit_reservations', [
            'tenant_id' => (string) $tenant->getKey(),
            'operation' => 'knowledge.query.private',
            'status' => 'settled',
            'actual_cost' => 0.01,
        ]);
    }

    public function test_failed_embedding_refunds_the_reservation(): void
    {
        config()->set([
            'ai.knowledge.metering_enabled' => true,
            'ai.knowledge.max_cost_usd' => 0.001,
            'ai.knowledge.input_usd_per_million' => 0.02,
        ]);
        $tenant = Tenant::factory()->create([
            'ai_balance' => 1,
            'ai_reserved_balance' => 0,
        ]);
        $this->app->instance(EmbeddingProvider::class, new MeteredFakeEmbeddingProvider(true));

        try {
            app(EmbeddingUsageMeter::class)->embed(
                (string) $tenant->getKey(),
                'knowledge.index',
                'consulta fallida',
            );
            self::fail('Expected provider failure.');
        } catch (AiProviderUnavailableException) {
            self::assertEqualsWithDelta(1.0, (float) $tenant->fresh()->ai_balance, 0.000001);
            self::assertEqualsWithDelta(0.0, (float) $tenant->fresh()->ai_reserved_balance, 0.000001);
            $this->assertDatabaseHas('ai_credit_reservations', [
                'tenant_id' => (string) $tenant->getKey(),
                'operation' => 'knowledge.index',
                'status' => 'refunded',
            ]);
        }
    }
}

final readonly class MeteredFakeEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private bool $fail) {}

    public function embed(string $content): EmbeddingResult
    {
        if ($this->fail) {
            throw new AiProviderUnavailableException('test');
        }

        return new EmbeddingResult([0.1, 0.2, 0.3], 'test-embedding', 3);
    }
}
