<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Crm\Enums\DealStatus;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Filament\App\Resources\Crm\ActivityResource\Pages\CreateActivity;
use App\Filament\App\Resources\Crm\ActivityResource\Pages\EditActivity;
use App\Filament\App\Resources\Crm\CompanyResource\Pages\CreateCompany;
use App\Filament\App\Resources\Crm\CompanyResource\Pages\EditCompany;
use App\Filament\App\Resources\Crm\ContactResource\Pages\CreateContact;
use App\Filament\App\Resources\Crm\ContactResource\Pages\EditContact;
use App\Filament\App\Resources\Crm\ContactResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\App\Resources\Crm\TagResource\Pages\CreateTag;
use App\Filament\App\Resources\Crm\TagResource\Pages\EditTag;
use App\Application\Crm\BulkOperationsService;
use App\Models\Contact;
use App\Models\CrmCompany;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;
use App\Models\Tag;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

final class CrmEntityTenantWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_create_rejects_foreign_company_and_owner_without_mutating(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        [$foreignOwner, $foreignTenant] = $this->ownerTenant('tenant-b');
        $foreignCompany = CrmCompany::query()->forceCreate([
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign company',
        ]);

        $this->setPanelContext($actor, $tenant);

        Livewire::test(CreateContact::class)
            ->fillForm([
                'first_name' => 'Should not persist',
                'company_id' => $foreignCompany->getKey(),
                'owner_id' => $foreignOwner->getKey(),
                'lifecycle_stage' => 'lead',
            ])
            ->call('create')
            ->assertHasFormErrors(['company_id']);

        self::assertDatabaseMissing('crm_contacts', [
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Should not persist',
        ]);
    }

    public function test_contact_create_persists_same_tenant_relationships_and_edit_cannot_move_target(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        $company = CrmCompany::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Same tenant company',
        ]);
        $this->setPanelContext($actor, $tenant);

        Livewire::test(CreateContact::class)
            ->fillForm([
                'first_name' => 'Same tenant',
                'company_id' => $company->getKey(),
                'owner_id' => $actor->getKey(),
                'lifecycle_stage' => 'lead',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $contact = Contact::query()->where('first_name', 'Same tenant')->firstOrFail();
        self::assertSame((string) $tenant->getKey(), (string) $contact->tenant_id);
        self::assertSame((string) $company->getKey(), (string) $contact->company_id);

        [, $foreignTenant] = $this->ownerTenant('tenant-b');
        $foreignCompany = CrmCompany::query()->forceCreate([
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign edit target',
        ]);

        Livewire::test(EditContact::class, ['record' => $contact->getKey()])
            ->fillForm(['company_id' => $foreignCompany->getKey()])
            ->call('save')
            ->assertHasFormErrors(['company_id']);

        self::assertSame((string) $company->getKey(), (string) $contact->fresh()->company_id);
    }

    public function test_activity_create_accepts_canonical_same_tenant_deal_and_rejects_foreign_deal(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        $contact = Contact::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Local contact',
            'lifecycle_stage' => 'lead',
        ]);
        $deal = $this->deal($tenant, 'Local deal');
        [, $foreignTenant] = $this->ownerTenant('tenant-b');
        $foreignDeal = $this->deal($foreignTenant, 'Foreign deal');
        $this->setPanelContext($actor, $tenant);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'type' => 'call',
                'subject' => 'Local activity',
                'contact_id' => $contact->getKey(),
                'deal_id' => $deal->getKey(),
                'owner_id' => $actor->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        self::assertDatabaseHas('crm_activities', [
            'tenant_id' => $tenant->getKey(),
            'contact_id' => $contact->getKey(),
            'deal_id' => $deal->getKey(),
        ]);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'type' => 'call',
                'subject' => 'Foreign activity',
                'deal_id' => $foreignDeal->getKey(),
            ])
            ->call('create')
            ->assertHasFormErrors(['deal_id']);

        self::assertDatabaseMissing('crm_activities', [
            'tenant_id' => $tenant->getKey(),
            'subject' => 'Foreign activity',
        ]);
    }

    public function test_company_and_tag_create_edit_hooks_use_active_tenant_writer(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        $this->setPanelContext($actor, $tenant);

        Livewire::test(CreateCompany::class)
            ->fillForm(['name' => 'Tenant company'])
            ->call('create')
            ->assertHasNoFormErrors();
        $company = CrmCompany::query()->where('name', 'Tenant company')->firstOrFail();
        self::assertSame((string) $tenant->getKey(), (string) $company->tenant_id);

        Livewire::test(EditCompany::class, ['record' => $company->getKey()])
            ->fillForm(['name' => 'Tenant company updated'])
            ->call('save')
            ->assertHasNoFormErrors();
        self::assertSame('Tenant company updated', $company->fresh()->name);

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => 'VIP', 'slug' => 'vip', 'type' => 'all'])
            ->call('create')
            ->assertHasNoFormErrors();
        $tag = Tag::query()->where('slug', 'vip')->firstOrFail();

        Livewire::test(EditTag::class, ['record' => $tag->getKey()])
            ->fillForm(['name' => 'VIP updated'])
            ->call('save')
            ->assertHasNoFormErrors();
        self::assertSame('VIP updated', $tag->fresh()->name);
    }

    public function test_activity_edit_hook_and_bulk_owner_assignment_delegate_with_actor(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        $contact = Contact::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Bulk contact',
            'lifecycle_stage' => 'lead',
        ]);
        $activity = \App\Models\CrmActivity::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'type' => 'call',
            'subject' => 'Before edit',
        ]);
        $this->setPanelContext($actor, $tenant);

        Livewire::test(EditActivity::class, ['record' => $activity->getKey()])
            ->fillForm(['subject' => 'After edit'])
            ->call('save')
            ->assertHasNoFormErrors();
        self::assertSame('After edit', $activity->fresh()->subject);

        $count = app(BulkOperationsService::class)->bulkAssignOwner(
            [$contact->getKey()],
            (string) $actor->getKey(),
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
        );
        self::assertSame(1, $count);
        self::assertSame((string) $actor->getKey(), (string) $contact->fresh()->owner_id);

        [, $foreignTenant] = $this->ownerTenant('tenant-b');
        $foreignContact = Contact::query()->forceCreate([
            'tenant_id' => $foreignTenant->getKey(),
            'first_name' => 'Foreign bulk contact',
            'lifecycle_stage' => 'lead',
        ]);
        $contact->forceFill(['owner_id' => null])->saveQuietly();

        try {
            app(BulkOperationsService::class)->bulkAssignOwner(
                [$contact->getKey(), $foreignContact->getKey()],
                (string) $actor->getKey(),
                (string) $tenant->getKey(),
                (string) $actor->getKey(),
            );
            self::fail('Mixed-tenant bulk assignment must fail atomically.');
        } catch (ValidationException) {
            self::assertNull($contact->fresh()->owner_id);
        }
    }

    public function test_relationship_and_owner_options_are_current_tenant_scoped(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        [, $foreignTenant] = $this->ownerTenant('tenant-b');
        $localCompany = CrmCompany::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Local option',
        ]);
        $foreignCompany = CrmCompany::query()->forceCreate([
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign option',
        ]);
        $accepted = User::factory()->create();
        $pending = User::factory()->create();
        $suspended = User::factory()->create();
        $revoked = User::factory()->create();
        foreach ([
            [$accepted, ['accepted_at' => now()]],
            [$pending, ['accepted_at' => null]],
            [$suspended, ['accepted_at' => now(), 'suspended_at' => now()]],
            [$revoked, ['accepted_at' => now(), 'revoked_at' => now()]],
        ] as [$member, $state]) {
            $tenant->memberships()->create([
                'user_id' => $member->getKey(),
                'role' => Role::OPERATOR,
                'status' => 'active',
                ...$state,
            ]);
        }
        $this->setPanelContext($actor, $tenant);

        Livewire::test(CreateContact::class)
            ->assertFormFieldExists('company_id', null, function (Select $field) use ($localCompany, $foreignCompany): bool {
                $options = $field->getOptions() ?? [];

                return isset($options[$localCompany->getKey()])
                    && ! isset($options[$foreignCompany->getKey()]);
            })
            ->assertFormFieldExists('owner_id', null, function (Select $field) use ($actor, $accepted, $pending, $suspended, $revoked): bool {
                $options = $field->getOptions() ?? [];

                return isset($options[$actor->getKey()])
                    && isset($options[$accepted->getKey()])
                    && ! isset($options[$pending->getKey()])
                    && ! isset($options[$suspended->getKey()])
                    && ! isset($options[$revoked->getKey()]);
            });

        Livewire::test(CreateContact::class)
            ->fillForm([
                'first_name' => 'Inactive owner',
                'owner_id' => $suspended->getKey(),
                'lifecycle_stage' => 'lead',
            ])
            ->call('create')
            ->assertHasFormErrors(['owner_id']);

        self::assertDatabaseMissing('crm_contacts', [
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Inactive owner',
        ]);
    }

    public function test_contact_activity_relation_manager_forces_parent_contact(): void
    {
        [$actor, $tenant] = $this->ownerTenant('tenant-a');
        $contact = Contact::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Parent contact',
            'lifecycle_stage' => 'lead',
        ]);
        $this->setPanelContext($actor, $tenant);

        Livewire::test(ActivitiesRelationManager::class, [
            'ownerRecord' => $contact,
            'pageClass' => EditContact::class,
        ])
            ->callTableAction('create', data: [
                'type' => 'call',
                'subject' => 'Child activity',
            ])
            ->assertHasNoTableActionErrors();

        self::assertDatabaseHas('crm_activities', [
            'tenant_id' => $tenant->getKey(),
            'contact_id' => $contact->getKey(),
            'subject' => 'Child activity',
        ]);
    }

    public function test_missing_filament_tenant_fails_closed_before_entity_creation(): void
    {
        [$actor] = $this->ownerTenant('tenant-a');
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant(null, isQuiet: true);

        $page = new class extends CreateContact
        {
            /** @param array<string, mixed> $data */
            public function invokeHandle(array $data): void
            {
                $this->handleRecordCreation($data);
            }
        };

        $this->expectException(\RuntimeException::class);
        $page->invokeHandle(['first_name' => 'No tenant']);
        self::assertDatabaseMissing('crm_contacts', ['first_name' => 'No tenant']);
    }

    /** @return array{User, Tenant} */
    private function ownerTenant(string $slug): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => $slug,
            'plan_type' => 'pro',
            'subscription_status' => 'active',
        ]);
        $tenant->memberships()->create([
            'user_id' => $owner->getKey(),
            'role' => 'owner',
            'accepted_at' => now(),
            'status' => 'active',
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        return [$owner->refresh(), $tenant->refresh()];
    }

    private function setPanelContext(User $actor, Tenant $tenant): void
    {
        $this->actingAs($actor);
        app(TenantContext::class)->setTenant((string) $tenant->getKey());
        app()->instance('currentTenant', $tenant);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
    }

    private function deal(Tenant $tenant, string $title): CrmDeal
    {
        $pipeline = CrmPipeline::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => $title.' pipeline',
            'key' => strtolower(str_replace(' ', '-', $title)),
            'is_default' => true,
            'is_active' => true,
        ]);
        $stage = CrmStage::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'name' => 'New',
            'key' => 'new',
            'type' => CrmStage::TYPE_OPEN,
            'probability' => 10,
            'sort_order' => 1,
        ]);

        return CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => $title,
            'status' => DealStatus::Open->value,
        ]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);
        app()->forgetInstance('currentTenant');
        parent::tearDown();
    }
}
