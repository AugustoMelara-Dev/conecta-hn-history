<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Infrastructure\Jobs\TenantGovernance\ProcessAppointmentCustomerProfileEffects;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AppointmentCustomerProfileObserverTest extends TestCase
{
    #[Test]
    public function it_queues_appointment_customer_profile_effects_after_save(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $service = Service::factory()->create([
            'tenant_id' => $tenant->getKey(),
        ]);
        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->getKey(),
        ]);

        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'service_id' => $service->getKey(),
            'service_provider_id' => $provider->getKey(),
        ]);

        Queue::assertPushed(ProcessAppointmentCustomerProfileEffects::class, function (ProcessAppointmentCustomerProfileEffects $job) use ($appointment, $tenant): bool {
            return $job->appointmentId === (int) $appointment->getKey()
                && $job->tenantId === (string) $tenant->getKey()
                && $job->originalProfileId === null
                && $job->shouldRecordSignals === true
                && $job->shouldTriggerLifecycle === true;
        });
    }
}
