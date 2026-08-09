<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Application\Markets\HondurasMarketBackfill;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\TestCase;

final class GoldenDatasetEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_program05_release_fixture_covers_global_governance_blockers(): void
    {
        $path = base_path('tests/Fixtures/ai/program05-launch-v1.json');
        self::assertFileExists($path);
        $dataset = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('program05-launch-v1', $dataset['dataset_version'] ?? null);
        self::assertSame(hash_file('sha256', $path), hash('sha256', (string) file_get_contents($path)));

        $cases = collect($dataset['cases'] ?? []);
        self::assertNotEmpty($cases);
        self::assertSame($cases->count(), $cases->pluck('id')->unique()->count());
        self::assertEqualsCanonicalizing(['public_discovery', 'tenant_private'], $cases->pluck('surface')->unique()->all());
        self::assertEqualsCanonicalizing(['CA', 'HN'], $cases->pluck('market.country')->unique()->all());
        foreach (['no-evidence', 'stale-evidence', 'prompt-injection', 'cross-tenant', 'wrong-tool', 'confirmation', 'cost-latency', 'provider-regression'] as $requiredIdFragment) {
            self::assertTrue($cases->contains(
                static fn (array $case): bool => str_contains((string) $case['id'], $requiredIdFragment),
            ), "Missing deterministic fixture coverage for [{$requiredIdFragment}].");
        }
    }

    public function test_versioned_golden_dataset_meets_launch_thresholds(): void
    {
        $datasetPath = base_path('tests/Fixtures/ai/golden-launch-v1.json');

        self::assertFileExists($datasetPath);
        $dataset = json_decode((string) file_get_contents($datasetPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('1.0.0', $dataset['version'] ?? null);
        foreach ($dataset['cases'] ?? [] as $case) {
            self::assertArrayHasKey('provider_cost_usd', $case);
            self::assertArrayHasKey('expected_execution_status', $case);
            self::assertNotEmpty($case['expected_items'] ?? []);
        }

        $platform = Tenant::factory()->create([
            'name' => 'Conecta HN AI',
            'slug' => 'conecta-hn-ai-evaluation',
            'ai_balance' => 10,
            'ai_reserved_balance' => 0,
        ]);
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'name' => 'Cafe Evaluado',
            'slug' => 'cafe-evaluado',
            'is_active' => true,
            'subscription_status' => 'active',
            'ai_balance' => 10,
            'ai_reserved_balance' => 0,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $foreignTenant = Tenant::factory()->pro()->create([
            'name' => 'Negocio Ajeno',
            'slug' => 'negocio-ajeno-evaluation',
            'is_active' => true,
            'subscription_status' => 'active',
            'publication_paused_at' => now(),
        ]);
        $ownedProduct = Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe local',
            'description' => 'Tueste verificado',
            'price' => 120,
            'is_active' => true,
            'is_visible' => true,
        ]);
        Product::factory()->forTenant($foreignTenant)->create([
            'name' => 'SECRETO TENANT AJENO',
            'description' => 'NO DEBE APARECER',
            'price' => 1,
            'is_active' => true,
            'is_visible' => false,
        ]);
        app(HondurasMarketBackfill::class)->run();
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        config()->set([
            'ai.enabled' => true,
            'ai.allowed_environments' => ['testing'],
            'ai.providers.fake.enabled' => true,
            'ai.public.billing_tenant_id' => (string) $platform->id,
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'fake',
            'ai.capabilities.public_discovery.models' => ['fake-1'],
            'ai.capabilities.public_discovery.max_cost_usd' => 0.02,
            'ai.capabilities.public_discovery.requests_per_minute' => 100,
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
            'ai.capabilities.private_tenant_agent.models' => ['fake-1'],
            'ai.capabilities.private_tenant_agent.max_cost_usd' => 0.02,
            'ai.capabilities.private_tenant_agent.requests_per_minute' => 100,
        ]);
        AiEligibleRegistryFixture::create(
            tenantId: (string) $platform->getKey(),
            capability: 'public_discovery',
            audience: 'public',
            outputSchema: 'public_discovery.answer.v1',
        );
        AiEligibleRegistryFixture::create(
            tenantId: (string) $tenant->getKey(),
            capability: 'private_tenant_agent',
            audience: 'private',
            outputSchema: 'private_agent.evidence_selection.v1',
            workforceRole: 'manager',
        );

        $provider = new FakeAiProvider;
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'fake_structured_text' => ['fake.primary' => $provider],
        ]));
        $this->app->forgetInstance(AiGateway::class);

        $groundedPasses = 0;
        $leakages = 0;
        $unauthorizedToolForwards = 0;
        $evaluated = 0;
        $completedCosts = [];
        $groundingFailures = [];

        foreach ($dataset['cases'] ?? [] as $case) {
            Cache::flush();
            $evidenceId = ($case['provider_evidence'] ?? null) === 'owned'
                ? 'product:'.$ownedProduct->id
                : 'product:999999999';
            $providerData = [
                'evidence' => [[
                    'type' => 'product',
                    'id' => $evidenceId,
                ]],
            ];
            if (($case['surface'] ?? null) !== 'private') {
                $providerData['answer'] = (string) ($case['provider_answer'] ?? 'MODEL INVENTION');
                $providerData['evidence'][0]['reason'] = 'MODEL REASON';
            }

            $provider->queueResult(new AiProviderResult(
                data: $providerData,
                model: 'fake-1',
                inputTokens: 20,
                outputTokens: 10,
                costUsd: (float) $case['provider_cost_usd'],
                finishReason: 'stop',
            ));

            $response = ($case['surface'] ?? null) === 'private'
                ? $this->runPrivateCase($owner, $foreignTenant, $case)
                : $this->runPublicCase($case);

            self::assertSame(200, $response->getStatusCode(), (string) $case['id'].' response: '.$response->getContent());
            self::assertSame(
                (bool) $case['expected_fallback'],
                $response->json('fallback'),
                (string) $case['id'].' response: '.$response->getContent()
            );

            $responseText = $response->getContent();
            foreach ($case['forbidden_fragments'] ?? [] as $fragment) {
                if (str_contains($responseText, (string) $fragment)) {
                    $leakages++;
                }
            }

            $returnedIds = ($case['surface'] ?? null) === 'private'
                ? collect($response->json('data.items') ?? [])->pluck('id')->all()
                : collect($response->json('items') ?? [])->pluck('evidence_id')->filter()->all();
            $expectedIds = collect($case['expected_items'])
                ->map(static fn (string $item): string => match ($item) {
                    'owned' => 'product:'.$ownedProduct->id,
                    default => $item,
                })
                ->all();
            sort($returnedIds);
            sort($expectedIds);
            $failClosedWithoutClaims = (bool) $case['expected_fallback'] && $returnedIds === [];
            if ($failClosedWithoutClaims || ($returnedIds !== [] && $returnedIds === $expectedIds)) {
                $groundedPasses++;
            } else {
                $groundingFailures[] = [
                    'id' => (string) $case['id'],
                    'returned' => $returnedIds,
                    'expected' => $expectedIds,
                ];
            }

            $execution = DB::table('ai_executions')
                ->where('correlation_id', $response->json('meta.correlation_id'))
                ->first();
            self::assertNotNull($execution, (string) $case['id']);
            self::assertSame($case['expected_execution_status'], $execution->status, (string) $case['id']);
            self::assertSame($case['expected_failure_code'] ?? null, $execution->failure_code, (string) $case['id']);
            self::assertEqualsWithDelta(
                (float) $case['provider_cost_usd'],
                (float) $execution->provider_cost,
                0.000001,
                (string) $case['id']
            );
            if ($execution->status === 'completed') {
                $completedCosts[] = (float) $execution->charged_cost;
            }

            self::assertCount($evaluated + 1, $provider->requests, (string) $case['id'].' response: '.$response->getContent());
            $providerRequest = $provider->requests[array_key_last($provider->requests)];
            foreach ($case['hostile_tools'] ?? [] as $tool) {
                if (
                    str_contains($providerRequest->systemPrompt, (string) $tool)
                    || str_contains($providerRequest->userInput, (string) $tool)
                ) {
                    $unauthorizedToolForwards++;
                }
            }

            self::assertSame($case['expected_capability'], $providerRequest->capability, $case['id']);
            $evaluated++;
        }

        self::assertGreaterThan(0, $evaluated);
        self::assertGreaterThanOrEqual(
            (float) $dataset['thresholds']['groundedness'],
            $groundedPasses / $evaluated,
            json_encode($groundingFailures, JSON_THROW_ON_ERROR),
        );
        self::assertLessThanOrEqual((int) $dataset['thresholds']['tenant_leakage'], $leakages);
        self::assertLessThanOrEqual(
            (int) $dataset['thresholds']['unauthorized_tool_forwarding'],
            $unauthorizedToolForwards
        );

        self::assertTrue(
            collect($completedCosts)->every(
                static fn (float $cost): bool => $cost <= (float) $dataset['thresholds']['max_charged_cost_usd']
            )
        );
    }

    /** @param array<string, mixed> $case */
    private function runPublicCase(array $case): TestResponse
    {
        return $this->postJson('/api/v1/directory/guide/chat', [
            'message' => $case['prompt'],
            'budget' => $case['budget'],
            'tools' => $case['hostile_tools'],
        ]);
    }

    /** @param array<string, mixed> $case */
    private function runPrivateCase(
        User $owner,
        Tenant $foreignTenant,
        array $case
    ): TestResponse {
        Sanctum::actingAs($owner);

        return $this->withHeader('Idempotency-Key', 'golden-'.$case['id'])
            ->postJson('/api/v1/tenant/agent/chat', [
                'tenant_id' => (string) $foreignTenant->id,
                'message' => $case['prompt'],
                'tools' => $case['hostile_tools'],
            ]);
    }
}
