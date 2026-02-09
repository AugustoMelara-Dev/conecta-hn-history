<?php

declare(strict_types=1);

/**
 * SendAppointmentRemindersTest - 24h Pre-Notification Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  APPROACH: 100% RAW DB setup to avoid BelongsToTenant TenantContext     ║
 * ║  issues with factories. Appointment retrieval uses Eloquent for scope.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

namespace Tests\Feature\Jobs;

use App\Jobs\SendAppointmentReminders;
use App\Mail\AppointmentReminderMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendAppointmentRemindersTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;
    private string $userId;
    private int $serviceId;
    private int $providerId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Initialize TenantContext as System Root for Job execution
        app(\App\Domain\Shared\Services\TenantContext::class)->setAsSystemRoot();

        // ═══════════════════════════════════════════════════════════════════════
        // RAW DB SETUP: Avoids BelongsToTenant TenantContext issues with factories
        // ═══════════════════════════════════════════════════════════════════════

        // 1. Create User (UUID)
        $this->userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $this->userId,
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create Tenant (UUID)
        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id' => $this->tenantId,
            'user_id' => $this->userId,
            'name' => 'Test Reminder Tenant',
            'slug' => 'test-reminder-' . Str::random(6),
            'plan' => 'SEED',
            'is_active' => true,
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 1.00,
            'ai_reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create Service
        $this->serviceId = DB::table('services')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Service',
            'description' => 'Test service for reminders',
            'duration_minutes' => 60,
            'price' => 100.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Create ServiceProvider
        $this->providerId = DB::table('service_providers')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Provider',
            'email' => 'provider-' . Str::random(8) . '@example.com',
            'phone' => '9999-9999',
            'schedule_config' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER: Create appointment with RAW DB
    // ═══════════════════════════════════════════════════════════════════════════

    private function createAppointment(array $overrides = []): int
    {
        $defaults = [
            'tenant_id' => $this->tenantId,
            'service_id' => $this->serviceId,
            'service_provider_id' => $this->providerId,
            'user_id' => null,
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'end_at' => now()->addHours(24)->addMinutes(30),
            'status' => 'confirmed',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '9999-9999',
            'customer_notes' => null,
            'staff_notes' => null,
            'reminder_sent_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return DB::table('appointments')->insertGetId(array_merge($defaults, $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Sends reminder for appointment in 24h window
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_sends_reminder_for_appointment_in_24h_window(): void
    {
        $appointmentId = $this->createAppointment([
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'status' => 'confirmed',
            'customer_email' => 'test@example.com',
            'reminder_sent_at' => null,
        ]);

        (new SendAppointmentReminders)->handle();

        // Assert: Email was queued
        Mail::assertQueued(AppointmentReminderMail::class, function ($mail) use ($appointmentId) {
            return $mail->appointment->id === $appointmentId;
        });

        // Assert: Idempotency flag was set
        $reminderSentAt = DB::table('appointments')
            ->where('id', $appointmentId)
            ->value('reminder_sent_at');
        $this->assertNotNull($reminderSentAt, 'reminder_sent_at should be set after sending');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: Does NOT send reminder for appointment in 48 hours
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_does_not_send_reminder_for_appointment_in_48_hours(): void
    {
        $this->createAppointment([
            'scheduled_at' => now()->addHours(48),
            'status' => 'confirmed',
            'customer_email' => 'test@example.com',
            'reminder_sent_at' => null,
        ]);

        (new SendAppointmentReminders)->handle();

        Mail::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: Does NOT send duplicate reminders (idempotency)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_does_not_send_duplicate_reminders(): void
    {
        $this->createAppointment([
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'status' => 'confirmed',
            'customer_email' => 'test@example.com',
            'reminder_sent_at' => now()->subHour(), // Already reminded!
        ]);

        (new SendAppointmentReminders)->handle();

        Mail::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Skips appointments without customer email
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_skips_appointments_without_customer_email(): void
    {
        $this->createAppointment([
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'status' => 'confirmed',
            'customer_email' => null, // Walk-in
            'reminder_sent_at' => null,
        ]);

        (new SendAppointmentReminders)->handle();

        Mail::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Skips cancelled appointments
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_skips_cancelled_appointments(): void
    {
        $this->createAppointment([
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'status' => 'cancelled',
            'customer_email' => 'test@example.com',
            'reminder_sent_at' => null,
        ]);

        (new SendAppointmentReminders)->handle();

        Mail::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Email subject includes tenant name
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_email_subject_includes_tenant_name(): void
    {
        $this->createAppointment([
            'scheduled_at' => now()->addHours(23)->addMinutes(30),
            'status' => 'confirmed',
            'customer_email' => 'test@example.com',
            'reminder_sent_at' => null,
        ]);

        (new SendAppointmentReminders)->handle();

        Mail::assertQueued(AppointmentReminderMail::class, function ($mail) {
            $subject = $mail->envelope()->subject;
            return str_contains($subject, 'Recordatorio de Cita') &&
                   str_contains($subject, 'Test Reminder Tenant');
        });
    }
}
