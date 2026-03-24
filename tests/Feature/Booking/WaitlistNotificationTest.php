<?php

use App\Domain\Booking\Events\AppointmentCancelled;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Listeners\Booking\CheckWaitlistOnCancellation;
use App\Mail\WaitlistSlotFreedMail;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

test('listener dispatches mail and updates waitlist status to notified', function () {
    Mail::fake();

    $tenantContext = app(TenantContext::class);
    $tenantContext->reset();
    $tenantContext->setAsSystemRoot(true);

    $tenantId = Str::uuid()->toString();

    Tenant::withoutEvents(function () use ($tenantId) {
        Tenant::query()->forceCreate([
            'id' => $tenantId,
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'plan' => 'SEED',
            'is_active' => true,
        ]);
    });

    $service = Service::query()->forceCreate([
        'tenant_id' => $tenantId,
        'name' => 'Haircut',
        'price' => 200,
        'duration_minutes' => 30,
        'buffer_before' => 0,
        'buffer_after' => 0,
        'is_active' => true,
    ]);

    $waitlist = Waitlist::query()->forceCreate([
        'tenant_id' => $tenantId,
        'service_id' => $service->id,
        'customer_email' => 'waiting@example.com',
        'customer_name' => 'Alice',
        'requested_date' => '2026-03-20',
        'status' => 'active',
    ]);

    $event = new AppointmentCancelled(
        appointmentId: 1,
        tenantId: $tenantId,
        serviceId: $service->id,
        scheduledAt: '2026-03-20T10:00:00Z'
    );

    $listener = new CheckWaitlistOnCancellation;
    $listener->handle($event);

    Mail::assertSent(WaitlistSlotFreedMail::class, function ($mail) use ($waitlist) {
        return $mail->hasTo($waitlist->customer_email);
    });

    expect($waitlist->fresh()->status)->toBe('notified');
});
