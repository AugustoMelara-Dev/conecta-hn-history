<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\TestCase;

final class AiFoundationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_requires_an_authenticated_super_administrator(): void
    {
        $platform = Tenant::factory()->create();
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);

        $this->getJson('/api/v1/platform/ai/health')->assertUnauthorized();

        $tenantUser = User::factory()->tenantOwner()->create(['tenant_id' => $platform->id]);
        Sanctum::actingAs($tenantUser);

        $this->getJson('/api/v1/platform/ai/health')->assertForbidden();
    }

    public function test_health_endpoint_returns_only_stable_counts_status_and_timestamps(): void
    {
        $platform = Tenant::factory()->create();
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $platform->id,
            capability: 'public_discovery',
            audience: 'public',
            outputSchema: 'public_discovery.answer.v1',
        );

        DB::table('ai_conversations')->insert([
            'id' => $conversationId = (string) Str::uuid(),
            'tenant_id' => (string) $platform->id,
            'visibility' => 'platform_public',
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => null,
            'agent_version_id' => $selection->agentVersionId,
            'locale' => 'es-HN',
            'market_country_code' => 'HN',
            'currency' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'status' => 'active',
            'retention_category' => 'standard',
            'retention_expires_at' => now()->subHour(),
            'last_message_sequence' => 1,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('ai_messages')->insert([
            'id' => $messageId = (string) Str::uuid(),
            'tenant_id' => (string) $platform->id,
            'conversation_id' => $conversationId,
            'sequence' => 1,
            'direction' => 'inbound',
            'role' => 'user',
            'content_ciphertext' => 'cipher:prompt',
            'content_hash' => hash('sha256', 'prompt text'),
            'encryption_key_version' => 'v1',
            'content_type' => 'text',
            'idempotency_key' => 'health-001',
            'idempotency_hash' => hash('sha256', 'health-001'),
            'status' => 'accepted',
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'execution_id' => null,
            'accepted_at' => now()->subHour(),
            'completed_at' => null,
            'failed_at' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        DB::table('ai_executions')->insert([
            'correlation_id' => (string) Str::uuid(),
            'tenant_id' => (string) $platform->id,
            'billing_tenant_id' => (string) $platform->id,
            'actor_id' => null,
            'audience' => 'public',
            'capability' => 'public_discovery',
            'operation' => 'public_discovery.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', 'health-execution'),
            'claim_token' => hash('sha256', 'health-claim'),
            'claim_version' => 1,
            'status' => 'accounting_pending',
            'reservation_id' => null,
            'result_ciphertext' => 'cipher:provider',
            'result_hash' => hash('sha256', 'provider payload'),
            'model' => $selection->modelKey,
            'usage' => json_encode(['input_tokens' => 1, 'output_tokens' => 1], JSON_THROW_ON_ERROR),
            'latency_milliseconds' => 42,
            'provider_cost' => 0.01,
            'charged_cost' => 0.01,
            'charged_credits' => 0.01,
            'credit_pricing_version' => 'credits-v1',
            'credits_per_provider_usd' => 1.0,
            'credit_minimum_charge' => 0.01,
            'failure_code' => null,
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDay(),
            'processing_expires_at' => now()->addMinute(),
            'provider_completed_at' => now()->subMinute(),
            'completed_at' => null,
            'failed_at' => null,
            'payload_pruned_at' => null,
            'conversation_id' => $conversationId,
            'request_message_id' => $messageId,
            'response_message_id' => null,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $admin = User::factory()->superAdmin()->create([
            'email' => 'ops@example.test',
        ]);
        config()->set('auth.super_admins', [$admin->email]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/platform/ai/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('registry', 'ready')
            ->assertJsonPath('eligible_routes', 1)
            ->assertJsonPath('accounting_pending', 1)
            ->assertJsonPath('retention_backlog', 1)
            ->assertJsonStructure([
                'status',
                'registry',
                'eligible_routes',
                'accounting_pending',
                'retention_backlog',
                'last_successful_evaluation_at',
                'degraded_components',
            ]);

        $serialized = json_encode($response->json(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('cipher:prompt', $serialized);
        self::assertStringNotContainsString('cipher:provider', $serialized);
        self::assertStringNotContainsString($selection->secretReference, $serialized);
        self::assertStringNotContainsString($selection->systemPrompt, $serialized);
    }
}
