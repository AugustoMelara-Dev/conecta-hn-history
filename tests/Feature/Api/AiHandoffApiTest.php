<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
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

final class AiHandoffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_queue_is_tenant_scoped_redacted_and_supports_lifecycle(): void
    {
        [$owner, $tenant, $handoffId] = $this->fixture('queue-owner@example.test');
        $assignee = User::factory()->create();
        $tenant->memberships()->create([
            'user_id' => $assignee->id,
            'role' => Role::OPERATOR,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->withHeaders($headers)->getJson('/api/v1/tenant/ai/handoffs?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $handoffId)
            ->assertJsonPath('data.0.context_summary', 'Customer requested human support.')
            ->assertJsonMissingPath('data.0.context_ciphertext')
            ->assertJsonMissingPath('data.0.context_hash')
            ->assertJsonMissingPath('data.0.encryption_key_version');

        $this->withHeaders($headers)->postJson("/api/v1/tenant/ai/handoffs/{$handoffId}/assign", [
            'assignee_id' => (string) $assignee->id,
            'expected_version' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'assigned');

        Sanctum::actingAs($assignee);
        $this->withHeaders($headers)->postJson("/api/v1/tenant/ai/handoffs/{$handoffId}/accept", [
            'expected_version' => 3,
        ])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->withHeaders($headers)->postJson("/api/v1/tenant/ai/handoffs/{$handoffId}/resolve", [
            'expected_version' => 4,
            'resolution_note' => 'Resolved after direct human review.',
            'return_to_agent' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.return_to_agent', true)
            ->assertJsonPath('data.resolution_note', 'Resolved after direct human review.')
            ->assertJsonMissingPath('data.resolution_ciphertext');
    }

    public function test_cross_tenant_show_is_404_and_same_tenant_unauthorized_is_403(): void
    {
        [$owner, $tenant, $handoffId] = $this->fixture('queue-isolation@example.test');
        $viewer = User::factory()->create();
        $tenant->memberships()->create([
            'user_id' => $viewer->id,
            'role' => Role::VIEWER,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        Sanctum::actingAs($viewer);
        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->getJson("/api/v1/tenant/ai/handoffs/{$handoffId}")
            ->assertForbidden();

        [$otherOwner, $otherTenant] = $this->tenantOwner('queue-other@example.test');
        Sanctum::actingAs($otherOwner);
        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->getJson("/api/v1/tenant/ai/handoffs/{$handoffId}")
            ->assertNotFound();
    }

    public function test_stale_mutation_is_409_and_validation_is_422(): void
    {
        [$owner, $tenant, $handoffId] = $this->fixture('queue-conflict@example.test');
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->withHeaders($headers)->postJson("/api/v1/tenant/ai/handoffs/{$handoffId}/assign", [
            'assignee_id' => 'not-a-uuid',
            'expected_version' => 2,
        ])->assertUnprocessable();
        $this->withHeaders($headers)->postJson("/api/v1/tenant/ai/handoffs/{$handoffId}/assign", [
            'assignee_id' => (string) $owner->id,
            'expected_version' => 1,
        ])->assertStatus(409);
    }

    public function test_authorized_show_handles_historical_rows_without_ciphertext_as_redacted_null(): void
    {
        [$owner, $tenant, $handoffId] = $this->fixture('queue-legacy@example.test');
        DB::table('ai_handoffs')->where('id', $handoffId)->update([
            'context_ciphertext' => null,
            'context_hash' => null,
            'encryption_key_version' => null,
        ]);
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->getJson("/api/v1/tenant/ai/handoffs/{$handoffId}")
            ->assertOk()
            ->assertJsonPath('data.context_summary', null)
            ->assertJsonMissingPath('data.context_ciphertext')
            ->assertJsonMissingPath('data.context_hash');
    }

    /** @return array{User, Tenant, string} */
    private function fixture(string $email): array
    {
        [$owner, $tenant] = $this->tenantOwner($email);
        GlobalMarketFixture::assignCanada($tenant);
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.evidence_selection.v1',
        );
        $claim = app(ConversationStore::class)->claim(new AcceptConversationMessage(
            (string) $tenant->id,
            'tenant_private',
            'web',
            null,
            (string) $owner->id,
            'en-CA',
            'CA',
            'CAD',
            'America/Toronto',
            'Human support please.',
            'text',
            'api-handoff-'.Str::uuid(),
        ));
        $id = app(AiHandoffService::class)->request(new RequestAiHandoff(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection->agentVersionId,
            'explicit_user',
            'normal',
            'merchant-support',
            'Customer requested human support.',
        ));
        app(TenantContext::class)->reset();

        return [$owner, $tenant, $id];
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
}
