<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionKnowledgeConfigGuardTest extends TestCase
{
    public function test_production_guard_rejects_unmetered_or_incompatible_semantic_search(): void
    {
        config()->set([
            'ai.enabled' => true,
            'ai.allowed_environments' => ['production'],
            'ai.providers.openai.enabled' => true,
            'ai.public.billing_tenant_id' => null,
            'ai.knowledge.enabled' => true,
            'ai.knowledge.provider' => 'openai',
            'ai.knowledge.embedding_dimensions' => 3,
            'ai.knowledge.metering_enabled' => false,
            'ai.knowledge.max_cost_usd' => 0,
            'ai.knowledge.input_usd_per_million' => 0,
            'services.openai.api_key' => 'test-secret',
            'services.openai.embeddings_endpoint' => 'http://api.example.test/embeddings',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_column($payload['violations'], 'key');

        self::assertSame(1, $exitCode);
        self::assertContains('AI_EMBEDDING_DIMENSIONS', $keys);
        self::assertContains('AI_EMBEDDING_METERING_ENABLED', $keys);
        self::assertContains('AI_EMBEDDING_MAX_COST_USD', $keys);
        self::assertContains('AI_EMBEDDING_INPUT_USD_PER_MILLION', $keys);
        self::assertContains('OPENAI_EMBEDDINGS_ENDPOINT', $keys);
        self::assertContains('AI_PUBLIC_BILLING_TENANT_ID', $keys);
    }
}
