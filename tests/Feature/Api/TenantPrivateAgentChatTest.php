<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\Appointment;
use App\Models\City;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TenantPrivateAgentChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetEventDispatcher();
    }

    protected function tearDown(): void
    {
        Model::setEventDispatcher(app('events'));

        parent::tearDown();
    }

    public function test_private_agent_is_disabled_by_default(): void
    {
        [$owner, $tenant] = $this->tenantOwner('agente-off');

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/tenant/agent/chat', ['message' => 'Cuanto he vendido hoy?'])
            ->assertForbidden();
    }

    public function test_allowlisted_owner_gets_deterministic_sales_answer_for_current_tenant(): void
    {
        [$owner, $tenant] = $this->tenantOwner('agente-owner');
        $this->enablePrivateAgentFor($tenant);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'total' => 150.50,
            'status' => Order::STATUS_CONFIRMED,
            'created_at' => now(),
        ]);
        Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'total' => 999.00,
            'status' => Order::STATUS_CANCELLED,
            'created_at' => now(),
        ]);
        $this->insertVerifiedRevenue((string) $tenant->getKey(), (string) $order->getKey(), 15050);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/tenant/agent/chat', ['message' => 'Cuanto he vendido hoy?'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'sales_today')
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.verified_net_revenue', 150.5)
            ->assertJsonPath('data.currency', 'HNL')
            ->assertJsonPath('meta.mode', 'deterministic_tenant_private_agent')
            ->assertJsonPath('meta.llm_used', false)
            ->assertJsonPath('meta.privacy_scope', 'tenant_private_only')
            ->assertJsonPath('meta.tenant_id', (string) $tenant->getKey());
    }

    public function test_private_agent_blocks_cross_tenant_context(): void
    {
        [$owner] = $this->tenantOwner('agente-a');
        [, $otherTenant] = $this->tenantOwner('agente-b', 'other-owner@example.com');
        $this->enablePrivateAgentFor($otherTenant);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->getKey())
            ->postJson('/api/v1/tenant/agent/chat', ['message' => 'Cuanto he vendido hoy?'])
            ->assertForbidden();
    }

    public function test_private_agent_requires_ai_permission_for_members(): void
    {
        [$owner, $tenant] = $this->tenantOwner('agente-permisos');
        $viewer = User::factory()->create(['email' => 'viewer-agent@example.com']);
        $this->enablePrivateAgentFor($tenant);

        $tenant->memberships()->create([
            'user_id' => $viewer->getKey(),
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/tenant/agent/chat', ['message' => 'Que reservas tengo hoy?'])
            ->assertForbidden();
    }

    public function test_private_agent_returns_today_appointments_without_cross_tenant_data(): void
    {
        [$owner, $tenant] = $this->tenantOwner('agente-reservas');
        [, $otherTenant] = $this->tenantOwner('agente-reservas-otro', 'appointments-other@example.com');
        $this->enablePrivateAgentFor($tenant);

        Appointment::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'scheduled_at' => now()->setTime(10, 30),
            'end_at' => now()->setTime(11, 0),
            'status' => Appointment::STATUS_CONFIRMED,
            'customer_name' => 'Cliente Correcto',
        ]);
        Appointment::factory()->create([
            'tenant_id' => $otherTenant->getKey(),
            'scheduled_at' => now()->setTime(12, 0),
            'end_at' => now()->setTime(12, 30),
            'status' => Appointment::STATUS_CONFIRMED,
            'customer_name' => 'Cliente Ajeno',
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/tenant/agent/chat', ['message' => 'Que reservas tengo hoy y a que hora?'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'appointments_today')
            ->assertJsonMissingPath('data.appointments.0.customer_name')
            ->assertJsonMissing(['customer_name' => 'Cliente Correcto'])
            ->assertJsonMissing(['customer_name' => 'Cliente Ajeno']);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function tenantOwner(string $slug, string $email = 'owner-agent@example.com'): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);

        $tenant = Tenant::factory()
            ->pro()
            ->forUser($owner)
            ->create([
                'slug' => $slug,
                'city_id' => City::factory()->create()->id,
            ]);

        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        $tenant->memberships()->create([
            'user_id' => $owner->getKey(),
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner, $tenant];
    }

    private function enablePrivateAgentFor(Tenant $tenant): void
    {
        config()->set([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->getKey()],
        ]);
    }

    private function insertVerifiedRevenue(string $tenantId, string $aggregateId, int $amountMinor): void
    {
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'order',
            'aggregate_id' => $aggregateId,
            'outcome' => CommercialOutcome::OrderPaid->value,
            'projection_key' => "{$tenantId}:order:{$aggregateId}:paid-test",
            'amount_minor' => $amountMinor,
            'currency' => 'HNL',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
