<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Domain\Access\Enums\Role;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class TenantAiConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'fake_structured_text' => [
                'fake.primary' => new FakeAiProvider,
            ],
        ]));
    }

    public function test_tenant_configuration_returns_only_authorized_labels_credit_summary_and_kill_switch_state(): void
    {
        [$owner, $tenant] = $this->tenantOwner('config-owner@example.test');
        $tenant->forceFill([
            'ai_balance' => 4.50,
            'ai_reserved_balance' => 1.25,
        ])->save();
        GlobalMarketFixture::assignCanada($tenant);

        config()->set([
            'ai.enabled' => true,
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.cutover.public_percent' => 100,
            'ai.cutover.private_percent' => 100,
            'ai.cutover.cohort_salt' => 'task12-cutover',
        ]);

        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.answer.v1',
            marketCountryCode: 'CA',
        );

        $conversationId = (string) Str::uuid();
        DB::table('ai_conversations')->insert([
            'id' => $conversationId,
            'tenant_id' => (string) $tenant->id,
            'visibility' => 'tenant_private',
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => (string) $owner->id,
            'agent_version_id' => null,
            'locale' => 'en-CA',
            'market_country_code' => 'CA',
            'currency' => 'CAD',
            'timezone' => 'America/Toronto',
            'status' => 'active',
            'retention_category' => 'conversation_standard',
            'retention_expires_at' => now()->addDays(30),
            'last_message_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->getJson('/api/v1/tenant/ai/configuration?conversation_id='.$conversationId)
            ->assertOk()
            ->assertJsonPath('data.tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.active_agent.id', $selection->agentVersionId)
            ->assertJsonPath('data.active_agent.name', 'Fixture agent')
            ->assertJsonPath('data.active_agent.version_label', 'v1')
            ->assertJsonPath('data.channel_eligibility.web.enabled', true)
            ->assertJsonPath('data.credit_summary.balance', 4.5)
            ->assertJsonPath('data.credit_summary.reserved', 1.25)
            ->assertJsonPath('data.credit_summary.available', 3.25)
            ->assertJsonPath('data.kill_switch.enabled', false)
            ->assertJsonPath('data.canary.private.assignment', 'canonical')
            ->assertJsonPath('data.canary.private.persisted_path', true)
            ->assertJsonMissingPath('data.active_agent.system_prompt')
            ->assertJsonMissingPath('data.active_agent.secret_reference')
            ->assertJsonMissingPath('data.credit_summary.public_platform_balance');

        self::assertStringNotContainsString('Registry-authorized test prompt.', $response->getContent());
        self::assertStringNotContainsString('openai.primary', $response->getContent());
        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversationId,
            'agent_version_id' => $selection->agentVersionId,
        ]);
    }

    public function test_same_tenant_viewers_are_forbidden_and_other_tenants_only_see_their_own_projection(): void
    {
        [$owner, $tenant] = $this->tenantOwner('config-primary@example.test');
        $viewer = User::factory()->create();
        $tenant->memberships()->create([
            'user_id' => $viewer->id,
            'role' => Role::VIEWER,
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        GlobalMarketFixture::assignCanada($tenant);

        config()->set([
            'ai.enabled' => true,
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.cutover.public_percent' => 100,
            'ai.cutover.private_percent' => 100,
            'ai.cutover.cohort_salt' => 'task12-cutover',
        ]);

        $selection = AiEligibleRegistryFixture::create(
            (string) $tenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.answer.v1',
            marketCountryCode: 'CA',
        );

        Sanctum::actingAs($viewer);
        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->getJson('/api/v1/tenant/ai/configuration')
            ->assertForbidden();

        [$otherOwner, $otherTenant] = $this->tenantOwner('config-secondary@example.test');
        $otherTenant->forceFill([
            'ai_balance' => 2.00,
            'ai_reserved_balance' => 0.50,
        ])->save();
        GlobalMarketFixture::assignCanada($otherTenant);
        config()->set('ai.private.tenant_allowlist', [
            (string) $tenant->id,
            (string) $otherTenant->id,
        ]);
        AiEligibleRegistryFixture::create(
            (string) $otherTenant->id,
            'private_tenant_agent',
            'private',
            'private_agent.answer.v1',
            marketCountryCode: 'CA',
        );

        Sanctum::actingAs($otherOwner);
        $response = $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->getJson('/api/v1/tenant/ai/configuration')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', (string) $otherTenant->id)
            ->assertJsonPath('data.credit_summary.balance', 2.0)
            ->assertJsonPath('data.credit_summary.available', 1.5);

        self::assertStringNotContainsString($selection->agentVersionId, $response->getContent());
        self::assertStringNotContainsString((string) $tenant->id, $response->getContent());
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
