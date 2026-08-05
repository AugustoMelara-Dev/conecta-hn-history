<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Crm\Commands\CreateOpportunityCommand;
use App\Application\Crm\Commands\MoveOpportunityStageCommand;
use App\Application\Crm\Commands\UpdateOpportunityCommand;
use App\Domain\Access\Enums\Role;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalOpportunityWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_uses_tenant_market_minor_units_and_idempotent_replay(): void
    {
        [$tenant, $owner] = $this->crmTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        [$pipeline, $stage] = $this->defaultPipelineAndStage($tenant);
        $writer = app(CanonicalOpportunityWriter::class);
        $command = new CreateOpportunityCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'opportunity:create:ca-1',
            title: 'Toronto rollout',
            valueMinor: 12_500,
            pipelineId: (int) $pipeline->id,
            stageId: (int) $stage->id,
            locationId: (string) $location->id,
            ownerUserId: (string) $owner->id,
        );

        $first = $writer->create($command);
        $second = $writer->create($command);

        self::assertSame($first->deal->getKey(), $second->deal->getKey());
        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame('CAD', $first->deal->currency);
        self::assertSame('125.00', (string) $first->deal->getRawOriginal('value'));
        self::assertSame(CrmDeal::STATUS_OPEN, $first->deal->status);
        self::assertNull($first->deal->closed_at);
        self::assertSame(1, DB::table('crm_deals')->where('tenant_id', $tenant->id)->count());
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', BusinessOperationChanged::class)
            ->count());
    }

    public function test_create_rejects_payload_drift_pipeline_mismatch_and_foreign_references(): void
    {
        [$tenant, $owner] = $this->crmTenant();
        GlobalMarketFixture::assignCanada($tenant);
        [$pipeline, $stage] = $this->defaultPipelineAndStage($tenant);
        [$other, $otherOwner] = $this->crmTenant('other@example.test');
        $foreignLocation = GlobalMarketFixture::assignCanada($other);
        [, $foreignStage] = $this->defaultPipelineAndStage($other);
        $writer = app(CanonicalOpportunityWriter::class);
        $base = new CreateOpportunityCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'opportunity:create:drift',
            title: 'Canonical deal',
            valueMinor: 8_000,
            currency: 'CAD',
            pipelineId: (int) $pipeline->id,
            stageId: (int) $stage->id,
        );
        $writer->create($base);

        try {
            $writer->create(new CreateOpportunityCommand(
                tenantId: (string) $tenant->id,
                actorId: (string) $owner->id,
                idempotencyKey: 'opportunity:create:drift',
                title: 'Canonical deal',
                valueMinor: 8_001,
                currency: 'CAD',
                pipelineId: (int) $pipeline->id,
                stageId: (int) $stage->id,
            ));
            self::fail('A changed opportunity payload cannot reuse an idempotency key.');
        } catch (\LogicException) {
            self::assertSame(1, DB::table('crm_deals')->where('tenant_id', $tenant->id)->count());
        }

        foreach ([
            ['stageId' => (int) $foreignStage->id],
            ['locationId' => (string) $foreignLocation->id],
            ['ownerUserId' => (string) $otherOwner->id],
        ] as $index => $foreignReference) {
            try {
                $writer->create(new CreateOpportunityCommand(
                    tenantId: (string) $tenant->id,
                    actorId: (string) $owner->id,
                    idempotencyKey: "opportunity:create:foreign-{$index}",
                    title: 'Denied',
                    valueMinor: 100,
                    currency: 'CAD',
                    pipelineId: (int) $pipeline->id,
                    stageId: $foreignReference['stageId'] ?? (int) $stage->id,
                    locationId: $foreignReference['locationId'] ?? null,
                    ownerUserId: $foreignReference['ownerUserId'] ?? null,
                ));
                self::fail('Cross-tenant CRM references must fail closed.');
            } catch (ValidationException|ModelNotFoundException) {
                self::assertSame(1, DB::table('crm_deals')->where('tenant_id', $tenant->id)->count());
            }
        }
    }

    public function test_explicit_market_currency_and_stage_lifecycle_are_canonical(): void
    {
        [$tenant, $owner] = $this->crmTenant();
        GlobalMarketFixture::assignJapan($tenant);
        [$pipeline, $open] = $this->defaultPipelineAndStage($tenant);
        $won = $this->stageOfType($tenant, $pipeline, CrmStage::TYPE_WON);
        $writer = app(CanonicalOpportunityWriter::class);
        $created = $writer->create(new CreateOpportunityCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'opportunity:create:jp-1',
            title: 'Tokyo expansion',
            valueMinor: 75_000,
            currency: 'JPY',
            pipelineId: (int) $pipeline->id,
            stageId: (int) $open->id,
        ));

        self::assertSame('JPY', $created->deal->currency);
        self::assertSame('75000.00', (string) $created->deal->getRawOriginal('value'));

        $wonResult = $writer->moveStage(new MoveOpportunityStageCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            dealId: (int) $created->deal->id,
            stageId: (int) $won->id,
        ));
        self::assertSame(CrmDeal::STATUS_WON, $wonResult->deal->status);
        self::assertNotNull($wonResult->deal->closed_at);

        $reopened = $writer->moveStage(new MoveOpportunityStageCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            dealId: (int) $created->deal->id,
            stageId: (int) $open->id,
        ));
        self::assertSame(CrmDeal::STATUS_OPEN, $reopened->deal->status);
        self::assertNull($reopened->deal->closed_at);
        self::assertNull($reopened->deal->lost_reason);
    }

    public function test_update_preserves_historical_currency_and_hides_cross_tenant_deals(): void
    {
        [$tenant, $owner] = $this->crmTenant();
        GlobalMarketFixture::assignCanada($tenant);
        [$pipeline, $stage] = $this->defaultPipelineAndStage($tenant);
        [$other, $otherOwner] = $this->crmTenant('hostile@example.test');
        GlobalMarketFixture::assignCanada($other);
        $writer = app(CanonicalOpportunityWriter::class);
        $deal = $writer->create(new CreateOpportunityCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'opportunity:create:update',
            title: 'Original',
            valueMinor: 10_000,
            currency: 'CAD',
            pipelineId: (int) $pipeline->id,
            stageId: (int) $stage->id,
        ))->deal;

        try {
            $writer->update(new UpdateOpportunityCommand(
                tenantId: (string) $other->id,
                actorId: (string) $otherOwner->id,
                dealId: (int) $deal->id,
                title: 'Hostile',
            ));
            self::fail('Cross-tenant opportunity updates must be hidden.');
        } catch (ModelNotFoundException) {
            self::assertSame('Original', $deal->fresh()->title);
        }

        $updated = $writer->update(new UpdateOpportunityCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            dealId: (int) $deal->id,
            title: 'Updated',
            valueMinor: 11_250,
        ));

        self::assertSame('Updated', $updated->deal->title);
        self::assertSame('CAD', $updated->deal->currency);
        self::assertSame('112.50', (string) $updated->deal->getRawOriginal('value'));
    }

    public function test_lead_conversion_is_idempotent_and_archival_is_tenant_scoped(): void
    {
        [$tenant, $owner] = $this->crmTenant();
        GlobalMarketFixture::assignCanada($tenant);
        [$other, $otherOwner] = $this->crmTenant('archive-hostile@example.test');
        GlobalMarketFixture::assignCanada($other);
        $lead = Lead::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Avery Chen',
            'email' => 'avery@example.test',
            'message' => 'Needs a rollout proposal.',
            'status' => 'new',
        ]);
        $writer = app(CanonicalOpportunityWriter::class);

        $first = $writer->convertLead(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $lead->id,
            'opportunity:lead:'.$lead->id,
            ['value_minor' => 25_000],
        );
        $second = $writer->convertLead(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $lead->id,
            'opportunity:lead:'.$lead->id,
            ['value_minor' => 25_000],
        );

        self::assertSame($first->deal->getKey(), $second->deal->getKey());
        self::assertSame('converted', $lead->fresh()->status);
        self::assertSame(1, DB::table('crm_deals')->where('lead_id', $lead->id)->count());

        try {
            $writer->archive(
                (string) $other->id,
                (string) $otherOwner->id,
                (int) $first->deal->id,
            );
            self::fail('Cross-tenant opportunity archival must be hidden.');
        } catch (ModelNotFoundException) {
            self::assertDatabaseHas('crm_deals', ['id' => $first->deal->id]);
        }

        $writer->archive(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $first->deal->id,
        );
        self::assertDatabaseMissing('crm_deals', ['id' => $first->deal->id]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function crmTenant(string $email = 'crm-owner@example.test'): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'plan_type' => TenantPlan::PRO->value,
            'subscription_status' => 'active',
            'business_type' => 'retail',
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    /**
     * @return array{CrmPipeline, CrmStage}
     */
    private function defaultPipelineAndStage(Tenant $tenant): array
    {
        $pipeline = CrmPipeline::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Sales',
            'key' => 'sales',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $stage = CrmStage::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->id,
            'crm_pipeline_id' => $pipeline->id,
            'name' => 'New',
            'key' => 'new',
            'type' => CrmStage::TYPE_OPEN,
            'probability' => 10,
            'is_default' => true,
            'sort_order' => 0,
        ]);
        CrmStage::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->id,
            'crm_pipeline_id' => $pipeline->id,
            'name' => 'Won',
            'key' => 'won',
            'type' => CrmStage::TYPE_WON,
            'probability' => 100,
            'is_default' => false,
            'sort_order' => 10,
        ]);
        CrmStage::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->id,
            'crm_pipeline_id' => $pipeline->id,
            'name' => 'Lost',
            'key' => 'lost',
            'type' => CrmStage::TYPE_LOST,
            'probability' => 0,
            'is_default' => false,
            'sort_order' => 20,
        ]);

        return [$pipeline, $stage];
    }

    private function stageOfType(Tenant $tenant, CrmPipeline $pipeline, string $type): CrmStage
    {
        return CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->id)
            ->where('crm_pipeline_id', $pipeline->id)
            ->where('type', $type)
            ->firstOrFail();
    }
}
