<?php

declare(strict_types=1);

use App\Application\Ai\Registry\AiReleaseEligibility;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Api\V1\PlatformAiEvaluationController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AI\AiEvaluationFixture;
use Tests\Support\AI\AiRuntimeSelectionFixture;

uses(RefreshDatabase::class);

it('protects run comparison decision and canonical revocation with redacted responses', function (): void {
    $tenant = Tenant::factory()->create();
    config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
    config()->set('security.admin_mfa_required', true);
    $admin = User::factory()->superAdmin()->create([
        'email' => fake()->unique()->safeEmail(),
        'two_factor_secret' => 'test-encrypted-secret',
        'two_factor_confirmed_at' => now(),
    ]);
    config()->set('auth.super_admins', [$admin->email]);
    $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
    $fixture = AiEvaluationFixture::create((string) $tenant->id, $selection);
    $payload = evaluationRunPayload($selection, $fixture['evaluationVersionId'], (string) $tenant->id);

    $this->postJson('/api/v1/platform/ai/evaluations/runs', $payload)->assertUnauthorized();
    $operator = User::factory()->tenantOwner()->create(['tenant_id' => $tenant->id]);
    Sanctum::actingAs($operator);
    $this->postJson('/api/v1/platform/ai/evaluations/runs', $payload)->assertForbidden();

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/platform/ai/evaluations/runs', [
        ...$payload, 'current_password' => 'incorrect',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->postJson('/api/v1/platform/ai/evaluations/runs', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $mfaSession = ['auth.two_factor_verified' => [
        'user_id' => (string) $admin->id,
        'verified_at' => now()->getTimestamp(),
    ]];
    $runResponse = $this->withSession($mfaSession)
        ->postJson('/api/v1/platform/ai/evaluations/runs', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'passed')
        ->assertJsonPath('data.blockers', []);
    $runId = (string) $runResponse->json('data.run_id');
    $serialized = json_encode($runResponse->json(), JSON_THROW_ON_ERROR);
    expect($serialized)->not->toContain('What did I sell today?')
        ->not->toContain('authorized_evidence')
        ->not->toContain('input_checksum')
        ->not->toContain($selection->secretReference);

    $candidatePayload = $payload;
    $candidatePayload['artifact_sha'] = 'git:1111111111111111';
    $candidateRunId = (string) $this->withSession($mfaSession)
        ->postJson('/api/v1/platform/ai/evaluations/runs', $candidatePayload)
        ->assertCreated()->json('data.run_id');
    $comparison = $this->withSession($mfaSession)
        ->postJson('/api/v1/platform/ai/evaluations/comparisons', [
            'tenant_id' => (string) $tenant->id,
            'baseline_run_id' => $runId,
            'candidate_run_id' => $candidateRunId,
            'current_password' => 'password',
            'reason' => 'Compare exact deterministic aggregate evaluation evidence.',
        ])->assertOk()->assertJsonPath('data.blocking_regressions', []);
    expect(json_encode($comparison->json(), JSON_THROW_ON_ERROR))->not->toContain('What did I sell today?');

    $decisionResponse = $this->withSession($mfaSession)
        ->postJson('/api/v1/platform/ai/evaluations/decisions', [
            'tenant_id' => (string) $tenant->id,
            'evaluation_run_id' => $runId,
            'current_password' => 'password',
            'reason' => 'Approve the exact deterministic release evaluation evidence.',
        ])->assertCreated();
    $decisionId = (string) $decisionResponse->json('data.decision_id');
    expect(Str::isUuid($decisionId))->toBeTrue();

    app(AiReleaseEligibility::class)->assertExact(
        (string) $tenant->id, $decisionId, ['active', 'eligible', 'candidate'], 'evaluation_gate',
    );

    $this->withSession($mfaSession)
        ->postJson("/api/v1/platform/ai/evaluations/decisions/{$decisionId}/revoke", [
            'tenant_id' => (string) $tenant->id,
            'current_password' => 'password',
            'reason' => 'Revoke the evaluated release after a controlled regression drill.',
        ])->assertOk()->assertJsonPath('data.revoked', true);
    expect(fn () => app(AiReleaseEligibility::class)->assertExact(
        (string) $tenant->id, $decisionId, ['active', 'eligible', 'candidate'], 'evaluation_gate',
    ))->toThrow(AiExecutionDeniedException::class);
});

it('rejects failed and cross-tenant evaluation decisions without revealing existence', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
    config()->set('security.admin_mfa_required', false);
    $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
    config()->set('auth.super_admins', [$admin->email]);
    Sanctum::actingAs($admin);

    $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
    $failed = AiEvaluationFixture::create((string) $tenant->id, $selection, ['p95_latency_ms' => 100]);
    $run = $this->postJson('/api/v1/platform/ai/evaluations/runs', evaluationRunPayload($selection, $failed['evaluationVersionId'], (string) $tenant->id))
        ->assertCreated()->assertJsonPath('data.status', 'failed')->json('data.run_id');
    $this->postJson('/api/v1/platform/ai/evaluations/decisions', [
        'tenant_id' => (string) $tenant->id,
        'evaluation_run_id' => $run,
        'current_password' => 'password',
        'reason' => 'Attempt to approve a deliberately failed evaluation run.',
    ])->assertUnprocessable()->assertExactJson(['code' => 'ai_evaluation_transition_denied']);

    $otherSelection = AiRuntimeSelectionFixture::create((string) $otherTenant->id, providerKey: 'other-fake');
    $other = AiEvaluationFixture::create((string) $otherTenant->id, $otherSelection);
    $this->postJson('/api/v1/platform/ai/evaluations/runs', evaluationRunPayload($otherSelection, $other['evaluationVersionId'], (string) $otherTenant->id))
        ->assertForbidden();
});

it('requires explicit tenant context for platform evaluation transitions', function (): void {
    $tenant = Tenant::factory()->create();
    config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
    config()->set('security.admin_mfa_required', false);
    $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
    config()->set('auth.super_admins', [$admin->email]);
    Sanctum::actingAs($admin);

    $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
    $fixture = AiEvaluationFixture::create((string) $tenant->id, $selection);
    $payload = evaluationRunPayload($selection, $fixture['evaluationVersionId'], (string) $tenant->id);
    unset($payload['tenant_id']);

    $this->postJson('/api/v1/platform/ai/evaluations/runs', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tenant_id');
});

it('rejects exact candidate version substitutions at the evaluation API boundary', function (): void {
    $tenant = Tenant::factory()->create();
    $tenantId = (string) $tenant->id;
    config()->set('ai.public.billing_tenant_id', $tenantId);
    config()->set('security.admin_mfa_required', false);
    $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
    config()->set('auth.super_admins', [$admin->email]);
    Sanctum::actingAs($admin);

    $selection = AiRuntimeSelectionFixture::create($tenantId, providerKey: 'base-'.Str::lower(Str::random(8)));
    $fixture = AiEvaluationFixture::create($tenantId, $selection);
    $substitute = AiRuntimeSelectionFixture::create($tenantId, providerKey: 'substitute-'.Str::lower(Str::random(8)));
    $payload = evaluationRunPayload($selection, $fixture['evaluationVersionId'], $tenantId);

    foreach ([
        'agent_version_id' => $substitute->agentVersionId,
        'provider_version_id' => $substitute->providerVersionId,
        'model_version_id' => $substitute->modelVersionId,
        'prompt_version_id' => $substitute->promptVersionId,
    ] as $field => $value) {
        $this->postJson('/api/v1/platform/ai/evaluations/runs', [
            ...$payload,
            $field => $value,
        ])->assertUnprocessable()
            ->assertExactJson(['code' => 'ai_evaluation_transition_denied']);
    }

    $this->assertDatabaseCount('ai_evaluation_runs', 0);
});

it('requires explicit tenant context for comparisons decisions and revocation', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $tenantId = (string) $tenant->id;
    $otherTenantId = (string) $otherTenant->id;
    config()->set('ai.public.billing_tenant_id', $tenantId);
    config()->set('security.admin_mfa_required', false);
    $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
    config()->set('auth.super_admins', [$admin->email]);
    Sanctum::actingAs($admin);

    $transitions = [
        [
            'path' => '/api/v1/platform/ai/evaluations/comparisons',
            'payload' => [
                'baseline_run_id' => (string) Str::uuid(),
                'candidate_run_id' => (string) Str::uuid(),
                'current_password' => 'password',
                'reason' => 'Compare exact deterministic aggregate evaluation evidence.',
            ],
        ],
        [
            'path' => '/api/v1/platform/ai/evaluations/decisions',
            'payload' => [
                'evaluation_run_id' => (string) Str::uuid(),
                'current_password' => 'password',
                'reason' => 'Approve the exact deterministic release evaluation evidence.',
            ],
        ],
        [
            'path' => '/api/v1/platform/ai/evaluations/decisions/'.Str::uuid().'/revoke',
            'payload' => [
                'current_password' => 'password',
                'reason' => 'Revoke the evaluated release after a controlled regression drill.',
            ],
        ],
    ];

    foreach ($transitions as $transition) {
        $this->postJson($transition['path'], $transition['payload'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');

        $this->postJson($transition['path'], [
            ...$transition['payload'],
            'tenant_id' => $otherTenantId,
        ])->assertForbidden();
    }
});

it('rejects non-UUID release decision ids before lifecycle forwarding', function (): void {
    $response = app(PlatformAiEvaluationController::class)->revoke(
        Request::create('/api/v1/platform/ai/evaluations/decisions/not-a-uuid/revoke', 'POST'),
        'not-a-uuid',
    );

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe(['code' => 'ai_evaluation_transition_denied']);
});

/** @return array<string, mixed> */
function evaluationRunPayload(object $selection, string $evaluationVersionId, string $tenantId): array
{
    return [
        'tenant_id' => $tenantId,
        'evaluation_version_id' => $evaluationVersionId,
        'agent_version_id' => $selection->agentVersionId,
        'prompt_version_id' => $selection->promptVersionId,
        'provider_version_id' => $selection->providerVersionId,
        'model_version_id' => $selection->modelVersionId,
        'artifact_sha' => 'git:0123456789abcdef',
        'current_password' => 'password',
        'reason' => 'Run the exact deterministic release evaluation candidate.',
    ];
}
