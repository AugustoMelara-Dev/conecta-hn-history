<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Automation\WorkflowDefinitionService;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Filament\App\Resources\Automation\WorkflowResource\Pages\CreateWorkflow;
use App\Filament\App\Resources\Automation\WorkflowResource\Pages\EditWorkflow;
use App\Filament\App\Resources\Automation\WorkflowResource\Pages\ListWorkflows;
use App\Models\AutomationWorkflowDefinition;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class WorkflowResourceLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_persists_a_tenant_scoped_canonical_draft(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $this->setPanelContext($owner, $tenant);

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'workflow_key' => 'merchant.follow-up',
                'definition_json' => json_encode([
                    'steps' => [
                        ['id' => 'wait', 'type' => 'wait', 'delay_seconds' => 60],
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('automation_workflow_definitions', [
            'tenant_id' => (string) $tenant->getKey(),
            'workflow_key' => 'merchant.follow-up',
            'created_by' => (string) $owner->getKey(),
        ]);
        $definitionId = (string) DB::table('automation_workflow_definitions')
            ->where('tenant_id', $tenant->getKey())
            ->where('workflow_key', 'merchant.follow-up')
            ->value('id');

        $this->assertDatabaseHas('automation_workflow_versions', [
            'definition_id' => $definitionId,
            'tenant_id' => (string) $tenant->getKey(),
            'workflow_key' => 'merchant.follow-up',
            'status' => 'draft',
            'created_by' => (string) $owner->getKey(),
        ]);
    }

    public function test_list_page_shows_only_canonical_workflow_keys_for_the_active_tenant(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        [, $foreignTenant] = $this->ownerTenant();
        $this->setPanelContext($owner, $tenant);

        app(WorkflowDefinitionService::class)->createDraft(
            (string) $tenant->getKey(),
            'merchant.follow-up',
            ['steps' => []],
            (string) $owner->getKey(),
        );
        app(WorkflowDefinitionService::class)->createDraft(
            (string) $foreignTenant->getKey(),
            'foreign.workflow',
            ['steps' => []],
            (string) $owner->getKey(),
        );

        Livewire::test(ListWorkflows::class)
            ->assertSee('merchant.follow-up')
            ->assertDontSee('foreign.workflow');
    }

    public function test_edit_page_updates_only_the_active_tenants_current_draft(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $this->setPanelContext($owner, $tenant);
        $created = app(WorkflowDefinitionService::class)->createDraft(
            (string) $tenant->getKey(),
            'merchant.follow-up',
            ['steps' => []],
            (string) $owner->getKey(),
        );

        Livewire::test(EditWorkflow::class, ['record' => $created['definition_id']])
            ->fillForm([
                'definition_json' => json_encode([
                    'steps' => [
                        ['id' => 'wait', 'type' => 'wait', 'delay_seconds' => 120],
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = json_decode((string) DB::table('automation_workflow_versions')
            ->where('id', $created['version_id'])
            ->value('definition'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(120, $stored['steps'][0]['delay_seconds']);
        self::assertSame(1, DB::table('automation_workflow_versions')
            ->where('definition_id', $created['definition_id'])
            ->count());
    }

    public function test_publish_action_promotes_the_current_draft_without_leaving_a_mutable_pointer(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $this->setPanelContext($owner, $tenant);
        $created = app(WorkflowDefinitionService::class)->createDraft(
            (string) $tenant->getKey(),
            'merchant.follow-up',
            ['steps' => []],
            (string) $owner->getKey(),
        );
        $record = AutomationWorkflowDefinition::query()->findOrFail($created['definition_id']);

        Livewire::test(ListWorkflows::class)
            ->callTableAction('publish', $record, ['published_version' => 'v1'])
            ->assertNotified();

        $this->assertDatabaseHas('automation_workflow_versions', [
            'id' => $created['version_id'],
            'tenant_id' => (string) $tenant->getKey(),
            'version' => 'v1',
            'status' => 'published',
            'published_by' => (string) $owner->getKey(),
        ]);
        $this->assertDatabaseHas('automation_workflow_definitions', [
            'id' => $created['definition_id'],
            'tenant_id' => (string) $tenant->getKey(),
            'current_draft_version_id' => null,
            'current_published_version_id' => $created['version_id'],
        ]);
    }

    public function test_run_action_executes_the_published_version_with_the_authenticated_tenant_context(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $this->setPanelContext($owner, $tenant);
        $draft = app(WorkflowDefinitionService::class)->createDraft(
            (string) $tenant->getKey(),
            'merchant.follow-up',
            ['steps' => []],
            (string) $owner->getKey(),
        );
        app(WorkflowDefinitionService::class)->publishDraft(
            (string) $tenant->getKey(),
            'merchant.follow-up',
            $draft['version_id'],
            'v1',
            (string) $owner->getKey(),
        );
        $record = AutomationWorkflowDefinition::query()->findOrFail($draft['definition_id']);

        Livewire::test(ListWorkflows::class)
            ->callTableAction('run_now', $record)
            ->assertNotified();

        $this->assertDatabaseHas('automation_workflow_runs', [
            'tenant_id' => (string) $tenant->getKey(),
            'workflow_key' => 'merchant.follow-up',
            'workflow_version' => 'v1',
            'actor_id' => (string) $owner->getKey(),
            'plan' => 'pro',
            'channel' => 'web',
            'trigger_source' => 'manual',
            'status' => 'completed',
        ]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    /** @return array{User, Tenant} */
    private function ownerTenant(): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
        ]);
        $tenant->memberships()->create([
            'user_id' => $owner->getKey(),
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        return [$owner->refresh(), $tenant];
    }

    private function setPanelContext(User $owner, Tenant $tenant): void
    {
        $this->actingAs($owner);
        app(TenantContext::class)->setTenant((string) $tenant->getKey());
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
    }
}
