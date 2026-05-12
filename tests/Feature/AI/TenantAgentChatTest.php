<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TenantAgentChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_agent_chat_is_deny_by_default(): void
    {
        config([
            'ai.private.enabled' => false,
            'ai.private.tenant_allowlist' => [],
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create([
            'slug' => 'cafe-norte',
            'is_active' => true,
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'cuanto vendi hoy',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Private AI is not enabled for this tenant.');
    }

    public function test_tenant_agent_chat_requires_authentication(): void
    {
        $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'cuanto vendi hoy',
        ])->assertUnauthorized();
    }

    public function test_tenant_agent_chat_answers_private_sales_only_for_authorized_allowlisted_tenant(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'slug' => 'cafe-privado',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->pro()->forUser($otherOwner)->create([
            'slug' => 'otro-cafe',
            'is_active' => true,
        ]);

        Order::factory()->forTenant($tenant)->create([
            'total' => 275,
            'created_at' => now(),
        ]);

        Order::factory()->forTenant($otherTenant)->create([
            'total' => 999,
            'created_at' => now(),
        ]);

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [$tenant->slug],
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'cuanto vendi hoy',
        ])->assertOk()
            ->assertJsonPath('source', 'private_tenant_scope')
            ->assertJsonPath('tenant_id', (string) $tenant->id)
            ->assertJsonFragment(['answer' => 'Hoy tenes 1 ordenes registradas por L. 275.00.'])
            ->assertJsonMissing(['999']);
    }

    public function test_tenant_agent_chat_rejects_cross_tenant_body_even_when_target_is_allowlisted(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'slug' => 'tenant-propio',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->pro()->forUser($otherOwner)->create([
            'slug' => 'tenant-ajeno',
            'is_active' => true,
        ]);

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [$otherTenant->slug],
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => $otherTenant->id,
            'message' => 'cuanto vendi hoy',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Private AI is not enabled for this tenant.');
    }
}
