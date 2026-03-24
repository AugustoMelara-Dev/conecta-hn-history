<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Interactions\Events\InteractionRecorded;
use App\Domain\Sales\Events\CartAbandoned;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Services\BusinessRolePresetCatalog;
use App\Events\Order\OrderDelivered;
use App\Jobs\SendAppointmentReminders;
use App\Listeners\Interactions\ProcessInteractionSideEffects;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\City;
use App\Models\Interaction;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class OperationalAutomationNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->setAsSystemRoot(isConsoleOrTesting: true);
    }

    public function test_lead_trigger_notifies_owner_and_vertical_followup_member(): void
    {
        [$owner, $tenant] = $this->makeTenantOwner(
            businessType: 'service',
            name: 'Clinica Sonrisa Dental',
            slug: 'clinica-sonrisa-operaciones',
            categoryName: 'Salud',
            categorySlug: 'salud',
        );

        $reception = $this->attachPresetMember($tenant, 'dental_reception');
        $viewer = $this->attachViewer($tenant);

        $interaction = new Interaction;
        $interaction->forceFill([
            'tenant_id' => (string) $tenant->getKey(),
            'type' => Interaction::TYPE_WHATSAPP_CLICK,
            'session_id' => 'session-lead-001',
            'ip_address' => '127.0.0.1',
            'metadata' => ['message' => 'Quiero una valoracion dental'],
        ]);
        $interaction->save();

        app(ProcessInteractionSideEffects::class)->handle(
            new InteractionRecorded((string) $interaction->getKey(), (string) $tenant->getKey(), now()->toDateTimeImmutable())
        );

        $this->assertContains('Nuevo lead listo para seguimiento', $this->notificationTitlesFor($owner));
        $this->assertContains('Nuevo lead listo para seguimiento', $this->notificationTitlesFor($reception));
        $this->assertNotContains('Nuevo lead listo para seguimiento', $this->notificationTitlesFor($viewer));

        $task = $this->findPlaybookTask($tenant, 'lead_follow_up');

        $this->assertSame('leads', $task->queue);
        $this->assertSame((string) $reception->getKey(), $task->assigned_user_id);
        $this->assertSame(OperationalPlaybookTask::STATUS_OPEN, $task->status);
    }

    public function test_appointment_booking_and_reminder_notify_reception_roles(): void
    {
        Mail::fake();

        [$owner, $tenant] = $this->makeTenantOwner(
            businessType: 'service',
            name: 'Clinica Sonrisa Dental',
            slug: 'clinica-sonrisa-agenda',
            categoryName: 'Salud',
            categorySlug: 'salud',
        );

        $reception = $this->attachPresetMember($tenant, 'dental_reception');
        $viewer = $this->attachViewer($tenant);

        $service = Service::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Limpieza dental',
        ]);
        $provider = ServiceProvider::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Dra. Martinez',
        ]);

        Appointment::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'service_id' => $service->getKey(),
            'service_provider_id' => $provider->getKey(),
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'end_at' => now()->addHours(24)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'customer_name' => 'Carlos Rivera',
            'customer_email' => 'carlos@example.com',
            'customer_phone' => '9999-1111',
        ]);

        (new SendAppointmentReminders)->handle(
            app(\App\Domain\Contracts\AdminNotifier::class),
            app(\App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager::class),
        );

        $ownerTitles = $this->notificationTitlesFor($owner);
        $receptionTitles = $this->notificationTitlesFor($reception);
        $viewerTitles = $this->notificationTitlesFor($viewer);

        $this->assertContains('Nueva cita reservada', $ownerTitles);
        $this->assertContains('Recordatorio de cita enviado', $ownerTitles);
        $this->assertContains('Nueva cita reservada', $receptionTitles);
        $this->assertContains('Recordatorio de cita enviado', $receptionTitles);
        $this->assertNotContains('Nueva cita reservada', $viewerTitles);
        $this->assertNotContains('Recordatorio de cita enviado', $viewerTitles);

        $bookingTask = $this->findPlaybookTask($tenant, 'appointment_booking');
        $reminderTask = $this->findPlaybookTask($tenant, 'appointment_reminder');

        $this->assertSame((string) $reception->getKey(), $bookingTask->assigned_user_id);
        $this->assertSame((string) $reception->getKey(), $reminderTask->assigned_user_id);
    }

    public function test_abandoned_cart_and_repeat_purchase_notify_growth_roles(): void
    {
        Mail::fake();

        [$owner, $tenant] = $this->makeTenantOwner(
            businessType: 'retail',
            name: 'Flagship Mobile',
            slug: 'flagship-mobile-operaciones',
            categoryName: 'Tecnologia',
            categorySlug: 'tecnologia',
        );

        $advisor = $this->attachPresetMember($tenant, 'phone_sales_advisor');
        $viewer = $this->attachViewer($tenant);

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);
        $customer->forceFill(['tenant_id' => $tenant->id])->save();

        Event::dispatch(new CartAbandoned(
            checkoutSessionId: 1001,
            tenantId: (string) $tenant->getKey(),
            sessionId: 'cart-premium-001',
            userId: (string) $customer->getKey(),
            subtotal: 12499.00,
        ));

        $order = Order::factory()
            ->forTenant($tenant)
            ->create([
                'status' => Order::STATUS_DELIVERED,
                'payment_status' => 'paid',
                'customer_name' => 'Andrea Lopez',
                'customer_email' => 'andrea@example.com',
                'customer_phone' => '9876-0000',
            ]);

        Event::dispatch(OrderDelivered::create(
            tenantId: (string) $tenant->getKey(),
            orderId: (int) $order->getKey(),
            orderNumber: (string) $order->order_number,
        ));

        $ownerTitles = $this->notificationTitlesFor($owner);
        $advisorTitles = $this->notificationTitlesFor($advisor);
        $viewerTitles = $this->notificationTitlesFor($viewer);

        $this->assertContains('Recuperacion de carrito activada', $ownerTitles);
        $this->assertContains('Oportunidad de recompra detectada', $ownerTitles);
        $this->assertContains('Recuperacion de carrito activada', $advisorTitles);
        $this->assertContains('Oportunidad de recompra detectada', $advisorTitles);
        $this->assertNotContains('Recuperacion de carrito activada', $viewerTitles);
        $this->assertNotContains('Oportunidad de recompra detectada', $viewerTitles);

        $recoveryTask = $this->findPlaybookTask($tenant, 'cart_recovery');
        $repeatTask = $this->findPlaybookTask($tenant, 'repeat_purchase');

        $this->assertSame((string) $advisor->getKey(), $recoveryTask->assigned_user_id);
        $this->assertSame((string) $advisor->getKey(), $repeatTask->assigned_user_id);
    }

    public function test_renewal_attention_notifies_owner_and_billing_capable_member(): void
    {
        Mail::fake();

        [$owner, $tenant] = $this->makeTenantOwner(
            businessType: 'service',
            name: 'Bufete Corporativo Central',
            slug: 'bufete-central-renovacion',
            categoryName: 'Legal',
            categorySlug: 'legal',
            tenantState: [
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addDays(3),
            ],
        );

        $billing = $this->attachPresetMember($tenant, 'legal_accounts');
        $viewer = $this->attachViewer($tenant);

        Artisan::call('subscription:lifecycle', [
            '--warning-days' => 3,
        ]);

        $this->assertContains('Renovacion a revisar esta semana', $this->notificationTitlesFor($owner));
        $this->assertContains('Renovacion a revisar esta semana', $this->notificationTitlesFor($billing));
        $this->assertNotContains('Renovacion a revisar esta semana', $this->notificationTitlesFor($viewer));

        $task = $this->findPlaybookTask($tenant, 'renewal_attention');

        $this->assertSame('billing', $task->queue);
        $this->assertSame((string) $billing->getKey(), $task->assigned_user_id);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $tenantState
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(
        string $businessType,
        string $name,
        string $slug,
        string $categoryName,
        string $categorySlug,
        array $tenantState = [],
    ): array {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create([
            'name' => $categoryName,
            'slug' => $categorySlug,
        ]);

        $tenant = Tenant::factory()->pro()->state(array_merge([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
            'business_type' => $businessType,
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ], $tenantState))->create();

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        return [$owner, $tenant];
    }

    private function attachPresetMember(Tenant $tenant, string $presetKey): User
    {
        $user = User::factory()->create([
            'role' => UserRole::STAFF,
        ]);

        $catalog = app(BusinessRolePresetCatalog::class);
        $preset = $catalog->findForTenant($tenant, $presetKey);

        $this->assertIsArray($preset, "Expected preset [{$presetKey}] to be available for {$tenant->name}.");

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $preset['role'],
            'preset_key' => $presetKey,
            'granted_permissions' => $catalog->permissionsForTenantPreset($tenant, $presetKey),
            'accepted_at' => now(),
        ]);

        return $user;
    }

    private function attachViewer(Tenant $tenant): User
    {
        $user = User::factory()->create([
            'role' => UserRole::STAFF,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => Role::VIEWER->value,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function notificationTitlesFor(User $user): array
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', (string) $user->getKey())
            ->where('notifiable_type', $user->getMorphClass())
            ->latest()
            ->get()
            ->map(static fn (DatabaseNotification $notification): string => (string) ($notification->data['title'] ?? ''))
            ->filter(static fn (string $title): bool => $title !== '')
            ->values()
            ->all();
    }

    private function findPlaybookTask(Tenant $tenant, string $triggerKey): OperationalPlaybookTask
    {
        return OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('trigger_key', $triggerKey)
            ->latest('created_at')
            ->firstOrFail();
    }
}
