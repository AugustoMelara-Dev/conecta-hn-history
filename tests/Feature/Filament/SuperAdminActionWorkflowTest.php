<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\Resources\OperationalPlaybookTaskResource\Pages\ListOperationalPlaybookTasks;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class SuperAdminActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_approve_a_pending_business(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'pending', isActive: false);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListTenants::class)
            ->callTableAction('approve', $tenant)
            ->assertHasNoTableActionErrors();

        $tenant->refresh();

        $this->assertSame('approved', (string) $tenant->getRawOriginal('status'));
        $this->assertTrue((bool) $tenant->getRawOriginal('is_active'));
    }

    public function test_superadmin_can_reject_a_pending_business(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'pending', isActive: true);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListTenants::class)
            ->callTableAction('reject', $tenant, [
                'rejection_reason' => 'Documentacion comercial incompleta.',
            ])
            ->assertHasNoTableActionErrors();

        $tenant->refresh();

        $this->assertSame('rejected', (string) $tenant->getRawOriginal('status'));
        $this->assertFalse((bool) $tenant->getRawOriginal('is_active'));
    }

    public function test_superadmin_can_update_tenant_crm_profile_from_business_queue(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $secondSuperAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Mariana Growth',
            'email' => 'mariana.growth@example.com',
        ]);
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true);

        $nextTouch = now()->addHours(6)->startOfMinute();

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListTenants::class)
            ->callTableAction('crmFollowUp', $tenant, [
                'crm_owner_id' => (string) $secondSuperAdmin->getKey(),
                'crm_team' => Tenant::CRM_TEAM_GROWTH,
                'crm_status' => Tenant::CRM_STATUS_UPSELL,
                'crm_priority' => Tenant::CRM_PRIORITY_HIGH,
                'crm_sla_hours' => 12,
                'crm_next_follow_up_at' => $nextTouch->format('Y-m-d\TH:i'),
                'crm_note' => 'Negocio listo para upsell despues de publicar su catalogo base.',
            ])
            ->assertHasNoTableActionErrors();

        $tenant->refresh();

        $this->assertSame((string) $secondSuperAdmin->getKey(), (string) $tenant->crm_owner_id);
        $this->assertSame(Tenant::CRM_TEAM_GROWTH, (string) $tenant->getRawOriginal('crm_team'));
        $this->assertSame(Tenant::CRM_STATUS_UPSELL, (string) $tenant->getRawOriginal('crm_status'));
        $this->assertSame(Tenant::CRM_PRIORITY_HIGH, (string) $tenant->getRawOriginal('crm_priority'));
        $this->assertSame(12, (int) $tenant->crm_sla_hours);
        $this->assertNotNull($tenant->crm_next_follow_up_at);
        $this->assertSame($nextTouch->format('Y-m-d H:i'), $tenant->crm_next_follow_up_at?->format('Y-m-d H:i'));
        $this->assertNotNull($tenant->crm_last_contacted_at);
        $this->assertStringContainsString('Negocio listo para upsell despues de publicar su catalogo base.', (string) $tenant->crm_notes);
    }

    public function test_superadmin_can_toggle_business_operational_state(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListTenants::class)
            ->callTableAction('killSwitch', $tenant)
            ->assertHasNoTableActionErrors();

        $tenant->refresh();

        $this->assertFalse((bool) $tenant->getRawOriginal('is_active'));

        Livewire::test(ListTenants::class)
            ->callTableAction('killSwitch', $tenant->fresh())
            ->assertHasNoTableActionErrors();

        $tenant->refresh();

        $this->assertTrue((bool) $tenant->getRawOriginal('is_active'));
    }

    public function test_superadmin_can_force_upgrade_a_business_to_pro(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true, isPro: false);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListTenants::class)
            ->callTableAction('forceUpgrade', $tenant, [
                'duration' => '3',
            ])
            ->assertHasNoTableActionErrors();

        $tenant->refresh();

        $this->assertSame('pro', (string) $tenant->getRawOriginal('plan_type'));
        $this->assertSame('active', (string) $tenant->getRawOriginal('subscription_status'));
        $this->assertTrue((bool) $tenant->getRawOriginal('is_active'));
        $this->assertTrue($tenant->is_pro);
        $this->assertNotNull($tenant->subscription_ends_at);
    }

    public function test_superadmin_can_approve_a_payment_and_extend_subscription(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true, isPro: false);
        $payment = $this->makePendingPayment($tenant);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListPayments::class)
            ->callTableAction('approve', $payment)
            ->assertHasNoTableActionErrors();

        $payment->refresh();
        $tenant->refresh();

        $this->assertSame(SubscriptionPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame((string) $superAdmin->getKey(), (string) $payment->reviewed_by);
        $this->assertNotNull($payment->reviewed_at);
        $this->assertNotNull($payment->subscription_extended_at);
        $this->assertSame('semilla', (string) $payment->plan_before_upgrade);
        $this->assertSame('pro', (string) $tenant->getRawOriginal('plan_type'));
        $this->assertSame('active', (string) $tenant->getRawOriginal('subscription_status'));
        $this->assertTrue($tenant->is_pro);
        $this->assertNotNull($tenant->subscription_ends_at);
    }

    public function test_superadmin_can_reject_a_payment_with_notes(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true, isPro: false);
        $payment = $this->makePendingPayment($tenant);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListPayments::class)
            ->callTableAction('reject', $payment, [
                'rejection_reason' => 'El comprobante no coincide con el monto reportado.',
            ])
            ->assertHasNoTableActionErrors();

        $payment->refresh();

        $this->assertSame(SubscriptionPayment::STATUS_REJECTED, $payment->status);
        $this->assertSame((string) $superAdmin->getKey(), (string) $payment->reviewed_by);
        $this->assertSame('El comprobante no coincide con el monto reportado.', $payment->admin_notes);
        $this->assertNotNull($payment->reviewed_at);
    }

    public function test_superadmin_can_rotate_user_password_from_table_action(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $targetUser = User::factory()->create([
            'email' => 'target-user@example.com',
        ]);

        $originalHash = (string) $targetUser->password;
        $originalRememberToken = $targetUser->remember_token;

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListUsers::class)
            ->callTableAction('change_password', $targetUser, [
                'new_password' => 'NuevaClave123',
                'new_password_confirmation' => 'NuevaClave123',
            ])
            ->assertHasNoTableActionErrors();

        $targetUser->refresh();

        $this->assertNotSame($originalHash, (string) $targetUser->password);
        $this->assertTrue(Hash::check('NuevaClave123', (string) $targetUser->password));
        $this->assertNotSame($originalRememberToken, $targetUser->remember_token);
    }

    public function test_superadmin_can_ban_and_restore_a_user(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $targetUser = User::factory()->create([
            'email' => 'ban-target@example.com',
        ]);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListUsers::class)
            ->callTableAction('ban', $targetUser)
            ->assertHasNoTableActionErrors();

        $targetUser->refresh();

        $this->assertNotNull($targetUser->banned_at);

        Livewire::test(ListUsers::class)
            ->callTableAction('unban', $targetUser->fresh())
            ->assertHasNoTableActionErrors();

        $targetUser->refresh();

        $this->assertNull($targetUser->banned_at);
    }

    public function test_superadmin_can_take_internal_ownership_of_a_playbook_case(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true);
        $suggestedAssignee = User::factory()->create([
            'role' => UserRole::STAFF,
            'email' => 'suggested-owner@example.com',
        ]);
        $task = $this->makeOperationalPlaybookTask($tenant, $suggestedAssignee);

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListOperationalPlaybookTasks::class)
            ->callTableAction('takeOwnership', $task)
            ->assertHasNoTableActionErrors();

        $task->refresh();

        $this->assertSame((string) $superAdmin->getKey(), (string) $task->admin_owner_id);
        $this->assertSame(OperationalPlaybookTask::FOLLOW_UP_ASSIGNED, (string) $task->follow_up_status);
        $this->assertNotNull($task->last_followed_up_at);
    }

    public function test_superadmin_can_update_internal_follow_up_notes_and_sla(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $secondSuperAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'email' => 'ops-owner@example.com',
        ]);
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true);
        $task = $this->makeOperationalPlaybookTask($tenant);

        $nextTouch = now()->addHours(4)->format('Y-m-d\TH:i');
        $internalSla = now()->addDay()->format('Y-m-d\TH:i');

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListOperationalPlaybookTasks::class)
            ->callTableAction('followUp', $task, [
                'admin_owner_id' => (string) $secondSuperAdmin->getKey(),
                'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_SCHEDULED,
                'next_follow_up_at' => $nextTouch,
                'due_at' => $internalSla,
                'internal_note' => 'Llamar despues del almuerzo y confirmar stock.',
            ])
            ->assertHasNoTableActionErrors();

        $task->refresh();

        $this->assertSame((string) $secondSuperAdmin->getKey(), (string) $task->admin_owner_id);
        $this->assertSame(OperationalPlaybookTask::FOLLOW_UP_SCHEDULED, (string) $task->follow_up_status);
        $this->assertNotNull($task->next_follow_up_at);
        $this->assertNotNull($task->due_at);
        $this->assertNotNull($task->last_followed_up_at);
        $this->assertStringContainsString('Llamar despues del almuerzo y confirmar stock.', (string) $task->internal_notes);
    }

    public function test_superadmin_can_close_internal_follow_up_case(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [, $tenant] = $this->makeTenantOwner(status: 'approved', isActive: true);
        $task = $this->makeOperationalPlaybookTask($tenant);

        $task->forceFill([
            'admin_owner_id' => (string) $superAdmin->getKey(),
            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_SCHEDULED,
            'next_follow_up_at' => now()->addHour(),
        ])->save();

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListOperationalPlaybookTasks::class)
            ->callTableAction('closeFollowUp', $task)
            ->assertHasNoTableActionErrors();

        $task->refresh();

        $this->assertSame(OperationalPlaybookTask::FOLLOW_UP_CLOSED, (string) $task->follow_up_status);
        $this->assertNull($task->next_follow_up_at);
        $this->assertNotNull($task->last_followed_up_at);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    private function makeSuperAdmin(): User
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'email' => 'workflow.superadmin@example.com',
        ]);

        config(['auth.super_admins' => [$superAdmin->email]]);

        return $superAdmin;
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $status = 'approved', bool $isActive = true, bool $isPro = false): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email' => 'owner-'.Str::lower(Str::random(8)).'@example.com',
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $owner->getKey(),
            'slug' => 'negocio-'.Str::lower(Str::random(8)),
            'status' => $status,
            'is_active' => $isActive,
            'plan_type' => $isPro ? 'pro' : 'semilla',
            'subscription_status' => $isPro ? 'active' : 'inactive',
            'subscription_ends_at' => $isPro ? now()->addMonth() : null,
            'business_type' => 'retail',
        ]);

        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$owner, $tenant];
    }

    private function makePendingPayment(Tenant $tenant): SubscriptionPayment
    {
        return SubscriptionPayment::forceCreate([
            'tenant_id' => $tenant->getKey(),
            'amount' => 500.00,
            'bank' => 'bank_transfer',
            'reference_number' => 'REF-'.Str::upper(Str::random(10)),
            'proof_path' => 'payments/proofs/test-proof.png',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);
    }

    private function makeOperationalPlaybookTask(Tenant $tenant, ?User $assignee = null): OperationalPlaybookTask
    {
        return OperationalPlaybookTask::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'assigned_user_id' => $assignee instanceof User ? (string) $assignee->getKey() : null,
            'trigger_key' => 'lead_follow_up',
            'queue' => 'leads',
            'route_key' => 'leads',
            'segment_key' => 'premium_phone_store',
            'suggested_preset_key' => 'phone_sales_advisor',
            'priority' => OperationalPlaybookTask::PRIORITY_HIGH,
            'status' => OperationalPlaybookTask::STATUS_OPEN,
            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_NEW,
            'sla_minutes' => 30,
            'title' => 'Responder cotizacion premium',
            'summary' => 'Lead de alto ticket esperando contacto comercial.',
            'playbook_steps' => ['Contactar al lead hoy mismo.'],
            'due_at' => now()->addMinutes(45),
        ]);
    }

    private function setAdminPanelContext(): void
    {
        Filament::setCurrentPanel('admin');
    }
}
