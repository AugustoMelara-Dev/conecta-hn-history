<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmCompany;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CrmEntityTenantWriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_contact_and_activity_create_use_the_initialized_tenant(): void
    {
        [$owner, $tenant] = $this->ownerTenant('crm-api-a@example.com');
        [, $foreignTenant] = $this->ownerTenant('crm-api-b@example.com');
        $foreignCompany = $this->company($foreignTenant, 'Foreign company');

        Sanctum::actingAs($owner);

        $company = $this->withTenant($tenant)
            ->postJson('/api/v1/crm/companies', ['name' => 'Local company'])
            ->assertCreated()
            ->assertJsonStructure(['id', 'tenant_id', 'name'])
            ->json();

        $contact = $this->withTenant($tenant)
            ->postJson('/api/v1/crm/contacts', [
                'first_name' => 'Ada',
                'company_id' => $company['id'],
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'tenant_id', 'company_id', 'first_name']])
            ->json('data');

        $activity = $this->withTenant($tenant)
            ->postJson('/api/v1/crm/activities', [
                'type' => 'call',
                'contact_id' => $contact['id'],
                'company_id' => $company['id'],
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'tenant_id', 'contact_id', 'type'])
            ->json();

        self::assertArrayNotHasKey('company_id', $activity);

        self::assertNotSame((string) $foreignTenant->getKey(), (string) $tenant->getKey());
        self::assertNotSame((string) $foreignCompany->getKey(), (string) $company['id']);
        self::assertDatabaseHas('crm_companies', [
            'id' => $company['id'],
            'tenant_id' => (string) $tenant->getKey(),
        ]);
        self::assertDatabaseHas('crm_contacts', [
            'id' => $contact['id'],
            'tenant_id' => (string) $tenant->getKey(),
            'company_id' => $company['id'],
        ]);
        self::assertDatabaseHas('crm_activities', [
            'id' => $activity['id'],
            'tenant_id' => (string) $tenant->getKey(),
            'company_id' => null,
        ]);
    }

    public function test_foreign_and_absent_contact_companies_are_equivalent_validation_errors(): void
    {
        [$owner, $tenant] = $this->ownerTenant('crm-api-reference-a@example.com');
        [, $foreignTenant] = $this->ownerTenant('crm-api-reference-b@example.com');
        $foreignCompany = $this->company($foreignTenant, 'Foreign company');
        Sanctum::actingAs($owner);

        $foreign = $this->withTenant($tenant)->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Foreign reference',
            'company_id' => $foreignCompany->getKey(),
        ]);
        $absent = $this->withTenant($tenant)->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Absent reference',
            'company_id' => (string) Str::uuid(),
        ]);

        $foreign->assertStatus(422);
        $absent->assertStatus(422);
        self::assertSame($foreign->json('errors.company_id.0'), $absent->json('errors.company_id.0'));
    }

    public function test_activity_create_accepts_the_canonical_integer_deal_id(): void
    {
        [$owner, $tenant] = $this->ownerTenant('crm-api-deal@example.com');
        $dealId = $this->deal($tenant);
        Sanctum::actingAs($owner);

        $this->withTenant($tenant)
            ->postJson('/api/v1/crm/activities', [
                'type' => 'task',
                'deal_id' => $dealId,
            ])
            ->assertCreated()
            ->assertJsonPath('deal_id', $dealId);
    }

    public function test_member_can_select_a_tenant_different_from_users_tenant_id(): void
    {
        [$owner, $tenant] = $this->ownerTenant('crm-api-member-owner@example.com');
        $member = User::factory()->create(['tenant_id' => (string) $tenant->getKey()]);
        $selectedTenant = Tenant::factory()->create(['user_id' => $owner->getKey()]);
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $selectedTenant->getKey(),
            'user_id' => (string) $member->getKey(),
            'role' => 'manager',
            'status' => 'active',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->grantPermission($member, 'crm.companies.create');

        Sanctum::actingAs($member);

        $this->withTenant($selectedTenant)
            ->postJson('/api/v1/crm/companies', ['name' => 'Selected tenant company'])
            ->assertCreated()
            ->assertJsonPath('tenant_id', (string) $selectedTenant->getKey());

        self::assertDatabaseHas('crm_companies', [
            'name' => 'Selected tenant company',
            'tenant_id' => (string) $selectedTenant->getKey(),
        ]);
    }

    public function test_inactive_actor_is_denied_and_inactive_owner_is_an_invalid_reference(): void
    {
        [$owner, $tenant] = $this->ownerTenant('crm-api-inactive-owner@example.com');
        $inactive = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $inactive->getKey(),
            'role' => 'manager',
            'status' => 'suspended',
            'accepted_at' => now(),
            'suspended_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($inactive);
        $this->withTenant($tenant)
            ->postJson('/api/v1/crm/companies', ['name' => 'Denied'])
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $response = $this->withTenant($tenant)
            ->postJson('/api/v1/crm/contacts', [
                'first_name' => 'Invalid owner',
                'owner_id' => (string) $inactive->getKey(),
            ]);

        $response->assertStatus(422);
        self::assertSame('The selected owner is invalid for this tenant.', $response->json('errors.owner_id.0'));
    }

    public function test_updates_keep_status_response_shape_and_route_target_tenant_immutable(): void
    {
        [$owner, $tenant] = $this->ownerTenant('crm-api-update-a@example.com');
        [, $foreignTenant] = $this->ownerTenant('crm-api-update-b@example.com');
        $company = $this->company($tenant, 'Before');
        $contact = $this->contact($tenant, $company, 'Before');
        $activity = $this->activity($tenant, $contact, $company);
        Sanctum::actingAs($owner);

        $this->withTenant($tenant)
            ->patchJson('/api/v1/crm/companies/'.$company->getKey(), [
                'name' => 'After',
                'tenant_id' => (string) $foreignTenant->getKey(),
            ])
            ->assertOk()
            ->assertJsonStructure(['id', 'tenant_id', 'name']);

        $this->withTenant($tenant)
            ->patchJson('/api/v1/crm/contacts/'.$contact->getKey(), ['first_name' => 'After'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'tenant_id', 'first_name', 'full_name', 'company_id']]);

        $this->withTenant($tenant)
            ->patchJson('/api/v1/crm/activities/'.$activity->getKey(), ['subject' => 'After'])
            ->assertOk()
            ->assertJsonStructure(['id', 'tenant_id', 'type', 'subject']);

        self::assertSame((string) $tenant->getKey(), (string) $company->fresh()->tenant_id);
        self::assertSame('After', $company->fresh()->name);
    }

    /** @return array{0: User, 1: Tenant} */
    private function ownerTenant(string $email): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->create(['plan_type' => 'pro']);
        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->saveQuietly();

        foreach ([
            'crm.companies.create',
            'crm.companies.update',
            'crm.contacts.create',
            'crm.contacts.update',
            'crm.activities.create',
            'crm.activities.update',
        ] as $permission) {
            $this->grantPermission($owner, $permission);
        }

        return [$owner, $tenant];
    }

    private function withTenant(Tenant $tenant): self
    {
        return $this->withHeader('X-Tenant-ID', (string) $tenant->getKey());
    }

    private function company(Tenant $tenant, string $name): CrmCompany
    {
        return CrmCompany::withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => $name,
        ]);
    }

    private function contact(Tenant $tenant, CrmCompany $company, string $firstName): Contact
    {
        return Contact::withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'company_id' => (string) $company->getKey(),
            'first_name' => $firstName,
        ]);
    }

    private function activity(Tenant $tenant, Contact $contact, CrmCompany $company): CrmActivity
    {
        return CrmActivity::withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'contact_id' => (string) $contact->getKey(),
            'company_id' => (string) $company->getKey(),
            'type' => 'call',
        ]);
    }

    private function deal(Tenant $tenant): int
    {
        $pipelineId = DB::table('crm_pipelines')->insertGetId([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Pipeline',
            'key' => 'api-deal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('crm_stages')->insertGetId([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipelineId,
            'name' => 'Open',
            'key' => 'open',
            'type' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('crm_deals')->insertGetId([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipelineId,
            'crm_stage_id' => $stageId,
            'title' => 'API deal',
            'value' => 0,
            'currency' => 'HNL',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPermission(User $user, string $permission): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->value('id');
        $permissionId ??= DB::table('permissions')->insertGetId([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => $permissionId,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
        ]);
    }
}
