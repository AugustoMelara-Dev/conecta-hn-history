<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Handoff\AiHandoffService;
use App\Application\Ai\Handoff\RequestAiHandoff;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class AiConversationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    public function test_authorized_participants_and_support_can_read_paginated_history_without_ciphertext_or_hashes(): void
    {
        [$owner, $tenant] = $this->tenantOwner('history-owner@example.test');
        $operator = User::factory()->create();
        $viewer = User::factory()->create();
        $tenant->memberships()->create([
            'user_id' => $operator->id,
            'role' => Role::OPERATOR,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        $tenant->memberships()->create([
            'user_id' => $viewer->id,
            'role' => Role::VIEWER,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        GlobalMarketFixture::assignCanada($tenant);
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.answer.v1',
        );

        $store = app(ConversationStore::class);
        $first = $store->claim(new AcceptConversationMessage(
            tenantId: (string) $tenant->id,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: null,
            actorId: (string) $owner->id,
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: 'How many orders did I receive?',
            contentType: 'text',
            idempotencyKey: 'history-001',
        ));
        $store->completeAssistantMessage(
            tenantId: (string) $tenant->id,
            conversationId: $first->conversationId,
            requestMessageId: $first->requestMessage->id,
            selection: $selection,
            result: $this->executionResult(
                (string) $tenant->id,
                $first->conversationId,
                $first->requestMessage->id,
                $selection,
            ),
            answer: ['answer' => 'You received 4 verified orders today.'],
            evidence: [],
        );

        $second = $store->claim(new AcceptConversationMessage(
            tenantId: (string) $tenant->id,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: null,
            actorId: (string) $owner->id,
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: 'Please hand this to support.',
            contentType: 'text',
            idempotencyKey: 'history-002',
            conversationId: $first->conversationId,
        ));
        $store->failAssistantMessage(
            tenantId: (string) $tenant->id,
            conversationId: $second->conversationId,
            requestMessageId: $second->requestMessage->id,
            publicCode: 'ai_handoff_required',
            retryable: false,
            fallback: [
                'answer' => 'A human follow-up has been requested.',
                'fallback' => true,
            ],
        );

        $handoffId = app(AiHandoffService::class)->request(new RequestAiHandoff(
            (string) $tenant->id,
            $second->conversationId,
            $second->requestMessage->id,
            $selection->agentVersionId,
            'explicit_user',
            'normal',
            'merchant-support',
            'Customer requested human support.',
        ));
        app(TenantContext::class)->reset();

        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->withHeaders($headers)->getJson('/api/v1/tenant/ai/conversations?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('data.0.id', $first->conversationId)
            ->assertJsonPath('data.0.latest_handoff.id', $handoffId)
            ->assertJsonPath('data.0.path', 'canonical')
            ->assertJsonMissingPath('data.0.content_ciphertext')
            ->assertJsonMissingPath('data.0.content_hash');

        $this->withHeaders($headers)->getJson("/api/v1/tenant/ai/conversations/{$first->conversationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $first->conversationId)
            ->assertJsonPath('data.latest_handoff.status', 'queued')
            ->assertJsonMissingPath('data.messages.0.content_ciphertext')
            ->assertJsonMissingPath('data.messages.0.content_hash');

        $pageOne = $this->withHeaders($headers)
            ->getJson("/api/v1/tenant/ai/conversations/{$first->conversationId}/messages?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sequence', 1)
            ->assertJsonPath('data.0.content', 'How many orders did I receive?')
            ->assertJsonPath('data.1.sequence', 2)
            ->assertJsonPath('data.1.content.answer', 'You received 4 verified orders today.')
            ->assertJsonMissingPath('data.1.content_ciphertext')
            ->assertJsonMissingPath('data.1.content_hash')
            ->json();

        $pageTwo = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/tenant/ai/conversations/%s/messages?per_page=2&after_sequence=%d&after_id=%s',
            $first->conversationId,
            $pageOne['meta']['next_cursor']['sequence'],
            $pageOne['meta']['next_cursor']['id'],
        ))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sequence', 3)
            ->assertJsonPath('data.0.content', 'Please hand this to support.')
            ->assertJsonPath('data.1.sequence', 4)
            ->assertJsonPath('data.1.content.answer', 'A human follow-up has been requested.')
            ->assertJsonPath('meta.next_cursor', null);

        $this->withHeaders($headers)->getJson("/api/v1/tenant/ai/conversations/{$first->conversationId}/handoff")
            ->assertOk()
            ->assertJsonPath('data.id', $handoffId)
            ->assertJsonPath('data.context_summary', 'Customer requested human support.')
            ->assertJsonMissingPath('data.context_ciphertext')
            ->assertJsonMissingPath('data.context_hash');

        Sanctum::actingAs($operator);
        $this->withHeaders($headers)->getJson("/api/v1/tenant/ai/conversations/{$first->conversationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $first->conversationId);

        Sanctum::actingAs($viewer);
        $this->withHeaders($headers)->getJson("/api/v1/tenant/ai/conversations/{$first->conversationId}")
            ->assertForbidden();
    }

    public function test_cross_tenant_conversation_reads_are_404(): void
    {
        [$owner, $tenant] = $this->tenantOwner('cross-owner@example.test');
        GlobalMarketFixture::assignCanada($tenant);
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.answer.v1',
        );
        $claim = app(ConversationStore::class)->claim(new AcceptConversationMessage(
            tenantId: (string) $tenant->id,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: null,
            actorId: (string) $owner->id,
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: 'Cross-tenant check.',
            contentType: 'text',
            idempotencyKey: 'cross-001',
        ));
        app(ConversationStore::class)->completeAssistantMessage(
            tenantId: (string) $tenant->id,
            conversationId: $claim->conversationId,
            requestMessageId: $claim->requestMessage->id,
            selection: $selection,
            result: $this->executionResult(
                (string) $tenant->id,
                $claim->conversationId,
                $claim->requestMessage->id,
                $selection,
            ),
            answer: ['answer' => 'Scoped to tenant.'],
            evidence: [],
        );
        app(TenantContext::class)->reset();

        [$otherOwner, $otherTenant] = $this->tenantOwner('cross-other@example.test');
        GlobalMarketFixture::assignCanada($otherTenant);
        Sanctum::actingAs($otherOwner);

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->getJson("/api/v1/tenant/ai/conversations/{$claim->conversationId}")
            ->assertNotFound();
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(string $email): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'city_id' => City::factory()->create()->id,
            'subscription_status' => 'active',
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        return [$owner, $tenant];
    }

    private function executionResult(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        object $selection,
    ): AiExecutionResult {
        $correlationId = (string) Str::uuid();
        DB::table('ai_executions')->insert([
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'billing_tenant_id' => $tenantId,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'model' => $selection->modelKey,
            'request_hash' => hash('sha256', 'request'),
            'claim_token' => hash('sha256', 'claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDay(),
            'processing_expires_at' => now()->addMinute(),
            'provider_completed_at' => now(),
            'completed_at' => now(),
            'conversation_id' => $conversationId,
            'request_message_id' => $requestMessageId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new AiExecutionResult(
            correlationId: $correlationId,
            provider: $selection->providerKey,
            model: $selection->modelKey,
            data: ['answer' => 'Scoped answer.'],
            usage: [
                'input_tokens' => 10,
                'output_tokens' => 8,
                'cost_usd' => 0.01,
                'charged_cost_usd' => 0.01,
                'provider_cost_usd' => 0.01,
            ],
            latencyMilliseconds: 42,
        );
    }
}
