<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TenantWorkforceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_rejects_unknown_roles_before_any_rollout_claim(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->withHeader('Idempotency-Key', 'workforce-unknown-role-001')
            ->postJson('/api/v1/tenant/workforce/activate', [
                'role_key' => 'unknown-role',
                'approval_mode' => 'human_review',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_key')
            ->assertJsonMissingPath('data.live');
    }

    public function test_activation_requires_an_explicit_approval_mode(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson('/api/v1/tenant/workforce/activate', [
                'role_key' => 'support',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approval_mode');
    }

    public function test_a_retried_activation_request_is_a_simulation_state_not_a_live_deployment(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        Sanctum::actingAs($owner);
        $payload = [
            'role_key' => 'support',
            'approval_mode' => 'human_review',
            'simulation_case_ids' => ['support-case-001'],
        ];

        $first = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->withHeader('Idempotency-Key', 'workforce-simulation-001')
            ->postJson('/api/v1/tenant/workforce/activate', $payload)
            ->assertOk()
            ->assertJsonPath('data.state', 'simulation')
            ->assertJsonPath('data.live_evidence.verified', false);

        $second = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->withHeader('Idempotency-Key', 'workforce-simulation-001')
            ->postJson('/api/v1/tenant/workforce/activate', $payload)
            ->assertOk();

        self::assertSame($first->json(), $second->json());
        self::assertDatabaseHas('dovra_workforce_activation_requests', [
            'tenant_id' => $tenant->id,
            'idempotency_key' => 'workforce-simulation-001',
            'state' => 'simulation',
        ]);
    }

    public function test_reusing_an_idempotency_key_with_a_different_payload_is_rejected(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        Sanctum::actingAs($owner);
        $headers = [
            'X-Tenant-ID' => (string) $tenant->id,
            'Idempotency-Key' => 'workforce-conflict-001',
        ];

        $this->withHeaders($headers)->postJson('/api/v1/tenant/workforce/activate', [
            'role_key' => 'support',
            'approval_mode' => 'human_review',
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/v1/tenant/workforce/activate', [
            'role_key' => 'support',
            'approval_mode' => 'policy_gate',
        ])->assertStatus(409)->assertJsonPath('code', 'workforce_idempotency_conflict');

        self::assertSame(1, DB::table('dovra_workforce_activation_requests')
            ->where('tenant_id', $tenant->id)
            ->where('idempotency_key', 'workforce-conflict-001')
            ->count());
    }

    public function test_stale_channel_health_never_becomes_live_evidence(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $now = now();
        DB::table('channel_connections')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'channel' => 'whatsapp',
            'connection_key' => 'primary',
            'provider_key' => 'whatsapp.fake',
            'status' => 'active',
            'connected_at' => $now->copy()->subHour(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('channel_provider_health')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'channel' => 'whatsapp',
            'connection_key' => 'primary',
            'provider_key' => 'whatsapp.fake',
            'status' => 'available',
            'checked_at' => $now->copy()->subMinutes(10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->withHeader('Idempotency-Key', 'workforce-stale-channel-001')
            ->postJson('/api/v1/tenant/workforce/activate', [
                'role_key' => 'support',
                'channel_keys' => ['whatsapp:primary'],
                'approval_mode' => 'human_review',
            ])
            ->assertOk()
            ->assertJsonPath('data.live_evidence.verified', false)
            ->assertJsonPath('data.channels.0.evidence_state', 'pending_external_verification');
    }

    public function test_future_channel_health_never_becomes_live_evidence(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $now = now();
        DB::table('channel_connections')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'channel' => 'whatsapp',
            'connection_key' => 'primary',
            'provider_key' => 'whatsapp.fake',
            'status' => 'active',
            'connected_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('channel_provider_health')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'channel' => 'whatsapp',
            'connection_key' => 'primary',
            'provider_key' => 'whatsapp.fake',
            'status' => 'available',
            'checked_at' => $now->copy()->addMinutes(10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->withHeader('Idempotency-Key', 'workforce-future-channel-001')
            ->postJson('/api/v1/tenant/workforce/activate', [
                'role_key' => 'support',
                'channel_keys' => ['whatsapp:primary'],
                'approval_mode' => 'human_review',
            ])
            ->assertOk()
            ->assertJsonPath('data.live_evidence.verified', false)
            ->assertJsonPath('data.channels.0.evidence_state', 'pending_external_verification');
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner->refresh(), $tenant];
    }
}
