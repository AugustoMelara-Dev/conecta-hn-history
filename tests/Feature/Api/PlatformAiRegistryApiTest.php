<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AI\AiEligibleRegistryFixture;

uses(RefreshDatabase::class);

it('exposes only allowlisted tenant-safe registry projections to platform superadmins', function (): void {
    $fixture = platformRegistryFixture();
    $otherTenant = Tenant::factory()->create();
    $other = AiEligibleRegistryFixture::create(
        tenantId: (string) $otherTenant->id,
        capability: 'private_agent',
        audience: 'private',
        outputSchema: 'private_agent.answer.v1',
    );

    $this->getJson('/api/v1/platform/ai/registry/prompts')->assertUnauthorized();

    $owner = User::factory()->tenantOwner()->create([
        'tenant_id' => $fixture['tenant']->id,
    ]);
    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/platform/ai/registry/prompts')->assertForbidden();

    Sanctum::actingAs($fixture['admin']);
    $response = $this->getJson('/api/v1/platform/ai/registry/prompts?per_page=10')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tenant_id', (string) $fixture['tenant']->id);

    $payload = $response->json();
    expect(json_encode($payload, JSON_THROW_ON_ERROR))
        ->not->toContain((string) $other->promptVersionId)
        ->not->toContain('template_ciphertext')
        ->not->toContain('template_hash')
        ->not->toContain('encryption_key_version')
        ->not->toContain('checksum');

    $providers = $this->getJson('/api/v1/platform/ai/registry/providers?per_page=200')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->json();
    expect(json_encode($providers, JSON_THROW_ON_ERROR))
        ->not->toContain('secret_reference')
        ->not->toContain('default_secret_reference')
        ->not->toContain('checksum');
});

it('requires password mfa and reason before draft creation or lifecycle transitions', function (): void {
    $fixture = platformRegistryFixture(requireMfa: true);
    Sanctum::actingAs($fixture['admin']);

    $definitionId = (string) DB::table('ai_agent_definitions')
        ->where('tenant_id', $fixture['tenant']->id)
        ->value('id');

    $draftPayload = [
        'version_type' => 'agent',
        'definition_id' => $definitionId,
        'payload' => [
            'model_version_id' => $fixture['selection']->modelVersionId,
            'configuration' => [
                'capability' => 'private_agent',
                'audience' => 'private',
                'channels' => ['web'],
            ],
        ],
        'current_password' => 'password',
        'reason' => 'Create a reviewed registry draft for the platform tenant.',
    ];

    $this->postJson('/api/v1/platform/ai/registry/drafts', $draftPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $response = $this->withSession([
        'auth.two_factor_verified' => [
            'user_id' => (string) $fixture['admin']->id,
            'verified_at' => now()->getTimestamp(),
        ],
    ])->postJson('/api/v1/platform/ai/registry/drafts', $draftPayload)
        ->assertCreated()
        ->assertJsonPath('data.version_type', 'agent')
        ->assertJsonPath('data.lifecycle_status', 'draft');

    $draftId = (string) $response->json('data.id');
    expect(Str::isUuid($draftId))->toBeTrue();
    $this->assertDatabaseHas('ai_agent_versions', [
        'id' => $draftId,
        'tenant_id' => (string) $fixture['tenant']->id,
        'lifecycle_status' => 'draft',
        'kill_switch_enabled' => false,
    ]);

    $mutation = [
        'current_password' => 'password',
        'reason' => 'Freeze the draft as an immutable evaluation candidate.',
    ];
    $this->withSession([
        'auth.two_factor_verified' => [
            'user_id' => (string) $fixture['admin']->id,
            'verified_at' => now()->getTimestamp(),
        ],
    ])->postJson("/api/v1/platform/ai/registry/agent/{$draftId}/promote", [
        ...$mutation,
        'target_status' => 'candidate',
    ])->assertOk()->assertJsonPath('data.lifecycle_status', 'candidate');

    $this->withSession([
        'auth.two_factor_verified' => [
            'user_id' => (string) $fixture['admin']->id,
            'verified_at' => now()->getTimestamp(),
        ],
    ])->postJson("/api/v1/platform/ai/registry/agent/{$draftId}/promote", [
        ...$mutation,
        'target_status' => 'active',
    ])->assertUnprocessable()
        ->assertExactJson(['code' => 'ai_registry_transition_denied']);

    $this->assertDatabaseCount('outbox_messages', 2);
});

it('denies tenant operators before revealing kill-switch registry material', function (): void {
    $fixture = platformRegistryFixture();
    $owner = User::factory()->tenantOwner()->create([
        'tenant_id' => $fixture['tenant']->id,
    ]);
    Sanctum::actingAs($owner);

    $this->postJson(
        "/api/v1/platform/ai/registry/agent/{$fixture['selection']->agentVersionId}/kill-switch",
        [
            'current_password' => 'password',
            'reason' => 'Attempt a platform-only registry mutation.',
        ],
    )->assertForbidden();
});

it('does not require an mfa session when the platform mfa policy is disabled', function (): void {
    $fixture = platformRegistryFixture(requireMfa: false);
    Sanctum::actingAs($fixture['admin']);

    $this->postJson(
        "/api/v1/platform/ai/registry/agent/{$fixture['selection']->agentVersionId}/kill-switch",
        [
            'current_password' => 'password',
            'reason' => 'Stop the selected agent after an operational incident.',
        ],
    )->assertOk()
        ->assertJsonPath('data.lifecycle_status', 'blocked');

    $this->assertDatabaseHas('ai_agent_versions', [
        'id' => $fixture['selection']->agentVersionId,
        'lifecycle_status' => 'blocked',
        'kill_switch_enabled' => true,
    ]);
});

/**
 * @return array{admin:User,tenant:Tenant,selection:object}
 */
function platformRegistryFixture(bool $requireMfa = false): array
{
    config()->set('activitylog.enabled', true);
    config()->set('security.admin_mfa_required', $requireMfa);
    $admin = User::factory()->superAdmin()->create([
        'email' => fake()->unique()->safeEmail(),
        'two_factor_secret' => $requireMfa ? 'test-encrypted-secret' : null,
        'two_factor_confirmed_at' => $requireMfa ? now() : null,
    ]);
    config()->set('auth.super_admins', [$admin->email]);
    $tenant = Tenant::factory()->create([
        'slug' => 'platform-registry-'.Str::lower(Str::random(6)),
    ]);
    config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
    $selection = AiEligibleRegistryFixture::create(
        tenantId: (string) $tenant->id,
        capability: 'private_agent',
        audience: 'private',
        outputSchema: 'private_agent.answer.v1',
    );

    return compact('admin', 'tenant', 'selection');
}
