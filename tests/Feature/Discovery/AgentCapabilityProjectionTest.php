<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Discovery\AgentCapabilityProjection;
use App\Application\Markets\HondurasMarketBackfill;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\TestCase;

final class AgentCapabilityProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_an_explicit_market_and_canonical_active_registry_release(): void
    {
        $tenant = Tenant::factory()->create();
        $projection = app(AgentCapabilityProjection::class);

        self::assertSame([], $projection->forTenant((string) $tenant->id));

        $this->assignHondurasMarket($tenant->id);
        $selection = $this->createEligibleAgent($tenant->id, 'public');

        $capabilities = $projection->forTenant((string) $tenant->id);

        self::assertCount(1, $capabilities);
        self::assertSame('public_tenant_agent', $capabilities[0]['capability']);
        self::assertSame($selection->agentVersionId, $capabilities[0]['agentVersionId']);
        self::assertTrue($capabilities[0]['marketContextRequired']);
    }

    public function test_it_never_projects_a_private_agent_as_public_discover_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignHondurasMarket($tenant->id);
        $this->createEligibleAgent($tenant->id, 'private');

        self::assertSame([], app(AgentCapabilityProjection::class)->forTenant((string) $tenant->id));
    }

    public function test_it_rejects_noncanonical_decisions_lineage_and_nonactive_agent_versions(): void
    {
        $projection = app(AgentCapabilityProjection::class);

        $approvedTenant = Tenant::factory()->create();
        $this->assignHondurasMarket($approvedTenant->id);
        $approved = $this->createEligibleAgent($approvedTenant->id);
        DB::table('ai_release_decisions')->where('id', $approved->releaseDecisionId)->update([
            'decision' => 'approved',
        ]);
        self::assertSame([], $projection->forTenant((string) $approvedTenant->id));

        $checksumTenant = Tenant::factory()->create();
        $this->assignHondurasMarket($checksumTenant->id);
        $checksum = $this->createEligibleAgent($checksumTenant->id);
        DB::table('ai_release_decisions')->where('id', $checksum->releaseDecisionId)->update([
            'candidate_checksum' => hash('sha256', 'different-agent'),
        ]);
        self::assertSame([], $projection->forTenant((string) $checksumTenant->id));

        $lineageTenant = Tenant::factory()->create();
        $this->assignHondurasMarket($lineageTenant->id);
        $lineage = $this->createEligibleAgent($lineageTenant->id);
        DB::table('ai_evaluation_runs')->where('id', $this->decisionRunId($lineage->releaseDecisionId))->update([
            'status' => 'pending',
            'completed_at' => null,
        ]);
        self::assertSame([], $projection->forTenant((string) $lineageTenant->id));

        $eligibleTenant = Tenant::factory()->create();
        $this->assignHondurasMarket($eligibleTenant->id);
        $eligible = $this->createEligibleAgent($eligibleTenant->id);
        DB::table('ai_agent_versions')->where('id', $eligible->agentVersionId)->update([
            'lifecycle_status' => 'eligible',
        ]);
        self::assertSame([], $projection->forTenant((string) $eligibleTenant->id));
    }

    public function test_tenant_scoping_does_not_project_another_tenants_release(): void
    {
        $owner = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->assignHondurasMarket($owner->id);
        $this->assignHondurasMarket($other->id);
        $this->createEligibleAgent($owner->id);

        self::assertSame([], app(AgentCapabilityProjection::class)->forTenant((string) $other->id));
    }

    private function createEligibleAgent(string $tenantId, string $audience = 'public'): AiRuntimeSelection
    {
        return AiEligibleRegistryFixture::create(
            tenantId: $tenantId,
            capability: 'public_tenant_agent',
            audience: $audience,
            outputSchema: 'private_agent.answer.v1',
            marketCountryCode: 'HN',
        );
    }

    private function assignHondurasMarket(string $tenantId): void
    {
        app(HondurasMarketBackfill::class)->run();
        $versionId = DB::table('market_pack_versions as versions')
            ->join('market_packs as packs', 'packs.id', '=', 'versions.market_pack_id')
            ->where('packs.key', 'hn')
            ->where('versions.status', 'published')
            ->value('versions.id');

        if ($versionId === null) {
            $this->markTestSkipped('The Honduras Market Pack is not available.');
        }

        $now = now();
        DB::table('tenant_markets')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'id' => (string) (DB::table('tenant_markets')->where('tenant_id', $tenantId)->value('id') ?: Str::uuid()),
                'market_pack_version_id' => $versionId,
                'country_code' => 'HN',
                'locale_code' => 'es-HN',
                'currency_code' => 'HNL',
                'timezone_name' => 'America/Tegucigalpa',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function decisionRunId(string $decisionId): string
    {
        return (string) DB::table('ai_release_decisions')
            ->where('id', $decisionId)
            ->value('evaluation_run_id');
    }
}
