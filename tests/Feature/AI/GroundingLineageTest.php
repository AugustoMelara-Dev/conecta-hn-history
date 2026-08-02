<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Grounding\EvidenceSnapshot;
use App\Application\Ai\Grounding\GroundingEvidenceProjector;
use App\Application\Ai\Grounding\GroundingPolicy;
use App\Application\Ai\Grounding\GroundingValidator;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Models\Product;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class GroundingLineageTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_selects_only_exact_fresh_authorized_evidence(): void
    {
        $owner = '00000000-0000-4000-8000-000000000001';
        $source = '00000000-0000-4000-8000-000000000002';
        $snapshot = new EvidenceSnapshot(
            evidenceId: 'product:42',
            sourceType: 'product',
            sourceId: '42',
            sourceVersion: str_repeat('a', 64),
            tenantId: $owner,
            sourceTenantId: $source,
            visibility: 'public',
            citationLabel: 'Producto publicado',
            freshAt: CarbonImmutable::now(),
            retrievalRank: 1,
            retrievalScore: 0.91,
            currency: 'HNL',
            priceMinor: 12500,
            availability: 'available',
            excerpt: new EncryptedAiContent('ciphertext', str_repeat('b', 64), 'v1'),
        );
        $policy = new GroundingPolicy(
            tenantId: $owner,
            visibility: 'public',
            currency: 'HNL',
            freshnessCutoff: CarbonImmutable::now()->subHour(),
            allowedClaimTypes: ['product'],
        );

        $answer = app(GroundingValidator::class)->validate([
            'answer' => 'provider wording is not authority',
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:42',
                'source_version' => str_repeat('a', 64),
                'currency' => 'HNL',
                'price_minor' => 12500,
                'availability' => 'available',
            ]],
        ], [$snapshot], $policy);

        self::assertSame([$snapshot], $answer->selectedEvidence);
        self::assertSame('provider wording is not authority', $answer->payload['answer']);
    }

    public function test_validator_rejects_duplicate_wrong_type_version_commerce_scope_and_staleness(): void
    {
        $owner = '00000000-0000-4000-8000-000000000001';
        $snapshot = new EvidenceSnapshot(
            evidenceId: 'product:42',
            sourceType: 'product',
            sourceId: '42',
            sourceVersion: str_repeat('a', 64),
            tenantId: $owner,
            sourceTenantId: $owner,
            visibility: 'private',
            citationLabel: 'Producto autorizado',
            freshAt: CarbonImmutable::now()->subMinutes(10),
            retrievalRank: 1,
            retrievalScore: 0.5,
            currency: 'HNL',
            priceMinor: 1000,
            availability: 'available',
            excerpt: new EncryptedAiContent('ciphertext', str_repeat('b', 64), 'v1'),
        );
        $policy = new GroundingPolicy(
            tenantId: $owner,
            visibility: 'private',
            currency: 'HNL',
            freshnessCutoff: CarbonImmutable::now()->subHour(),
            allowedClaimTypes: ['product'],
        );
        $valid = [
            'type' => 'product',
            'id' => 'product:42',
            'source_version' => str_repeat('a', 64),
            'currency' => 'HNL',
            'price_minor' => 1000,
            'availability' => 'available',
        ];
        $invalidOutputs = [
            ['evidence' => [$valid, $valid]],
            ['evidence' => [[...$valid, 'type' => 'service']]],
            ['evidence' => [[...$valid, 'source_version' => str_repeat('c', 64)]]],
            ['evidence' => [[...$valid, 'currency' => 'USD']]],
            ['evidence' => [[...$valid, 'price_minor' => 999]]],
            ['evidence' => [[...$valid, 'availability' => 'unavailable']]],
            ['evidence' => [['type' => 'product', 'id' => 'product:missing']]],
            ['evidence' => []],
        ];

        foreach ($invalidOutputs as $output) {
            try {
                app(GroundingValidator::class)->validate($output, [$snapshot], $policy);
                self::fail('Invalid grounding must fail closed.');
            } catch (AiMalformedResponseException $exception) {
                self::assertSame('Ungrounded AI claim.', $exception->getMessage());
            }
        }

        $stalePolicy = new GroundingPolicy(
            tenantId: $owner,
            visibility: 'private',
            currency: 'HNL',
            freshnessCutoff: CarbonImmutable::now()->subMinute(),
            allowedClaimTypes: ['product'],
        );
        $this->expectException(AiMalformedResponseException::class);
        app(GroundingValidator::class)->validate(['evidence' => [$valid]], [$snapshot], $stalePolicy);
    }

    public function test_public_source_mutation_aborts_response_and_all_evidence_atomically(): void
    {
        $platform = Tenant::factory()->create();
        $merchant = Tenant::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        GlobalMarketFixture::assignCanada($platform);
        GlobalMarketFixture::assignCanada($merchant);
        $product = Product::factory()->forTenant($merchant)->create([
            'name' => 'Mutable product',
            'price' => 25,
            'price_minor' => 2_500,
            'currency' => 'CAD',
            'is_active' => true,
            'is_visible' => true,
        ]);
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
        app(TenantContext::class)->setTenant((string) $platform->id);

        try {
            $store = app(ConversationStore::class);
            $claim = $store->claim(new AcceptConversationMessage(
                tenantId: (string) $platform->id,
                visibility: 'platform_public',
                originType: 'web',
                externalThreadReference: null,
                actorId: 'public-test-actor',
                locale: 'en-CA',
                marketCountryCode: 'CA',
                currency: 'CAD',
                timezone: 'America/Toronto',
                content: 'Show the product',
                contentType: 'text',
                idempotencyKey: 'grounding-mutation-1',
            ));
            $snapshots = app(GroundingEvidenceProjector::class)->project(
                (string) $platform->id,
                $claim->requestMessage->id,
                'public',
                [['evidence_id' => 'product:'.$product->id]],
            );
            self::assertSame(2_500, $snapshots[0]->priceMinor);
            $selection = AiRuntimeSelectionFixture::create((string) $platform->id);
            $result = $this->execution(
                (string) $platform->id,
                $claim->conversationId,
                $claim->requestMessage->id,
                $selection,
            );
            DB::table('products')->where('id', $product->id)->update([
                'price_minor' => 9_900,
            ]);

            try {
                $store->completeAssistantMessage(
                    (string) $platform->id,
                    $claim->conversationId,
                    $claim->requestMessage->id,
                    $selection,
                    $result,
                    ['answer' => 'Must not persist'],
                    $snapshots,
                );
                self::fail('A changed public source must invalidate its frozen snapshot.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('AI conversation evidence is invalid.', $exception->getMessage());
            }

            self::assertSame(0, DB::table('ai_messages')->where('role', 'assistant')->count());
            self::assertSame(0, DB::table('ai_message_evidence')->count());
            self::assertSame(1, (int) DB::table('ai_conversations')->value('last_message_sequence'));
            self::assertNull(DB::table('ai_executions')->value('response_message_id'));
        } finally {
            app(TenantContext::class)->reset();
        }
    }

    public function test_projector_prefers_canonical_price_minor_over_legacy_decimal_price(): void
    {
        $platform = Tenant::factory()->create();
        $merchant = Tenant::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        GlobalMarketFixture::assignCanada($platform);
        GlobalMarketFixture::assignCanada($merchant);
        $product = Product::factory()->forTenant($merchant)->create([
            'name' => 'Canonical price product',
            'price' => 25,
            'price_minor' => 1_234,
            'currency' => 'CAD',
            'is_active' => true,
            'is_visible' => true,
        ]);
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
        app(TenantContext::class)->setTenant((string) $platform->id);

        try {
            $claim = app(ConversationStore::class)->claim(new AcceptConversationMessage(
                tenantId: (string) $platform->id,
                visibility: 'platform_public',
                originType: 'web',
                externalThreadReference: null,
                actorId: 'public-price-test-actor',
                locale: 'en-CA',
                marketCountryCode: 'CA',
                currency: 'CAD',
                timezone: 'America/Toronto',
                content: 'Show the canonical price',
                contentType: 'text',
                idempotencyKey: 'grounding-canonical-price-1',
            ));
            $snapshots = app(GroundingEvidenceProjector::class)->project(
                (string) $platform->id,
                $claim->requestMessage->id,
                'public',
                [['evidence_id' => 'product:'.$product->id]],
            );

            self::assertCount(1, $snapshots);
            self::assertSame(1_234, $snapshots[0]->priceMinor);
        } finally {
            app(TenantContext::class)->reset();
        }
    }

    private function execution(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        AiRuntimeSelection $selection,
    ): AiExecutionResult {
        $correlationId = $requestMessageId;
        DB::table('ai_executions')->insert([
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'billing_tenant_id' => $tenantId,
            'actor_id' => 'public-test-actor',
            'audience' => 'public',
            'capability' => 'public_discovery',
            'operation' => 'public_discovery.answer',
            'provider' => $selection->providerKey,
            'model' => $selection->modelKey,
            'request_hash' => hash('sha256', 'grounding-mutation'),
            'claim_token' => hash('sha256', 'grounding-mutation-token'),
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
            data: [],
            usage: [],
            latencyMilliseconds: 1,
        );
    }
}
