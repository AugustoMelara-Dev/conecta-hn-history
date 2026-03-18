<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Application\Tenant\Commands\CreateAppointmentCommand;

final class CreateAppointmentHandler
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    /**
     * @return array{id: string|int}
     */
    public function handle(CreateAppointmentCommand $command): array
    {
        $appointment = $this->appointmentRepository->create([
            'tenant_id' => $command->tenantId,
            'service_id' => $command->serviceId,
            'service_provider_id' => $command->serviceProviderId,
            'scheduled_at' => $command->scheduledAt,
            'end_at' => $command->endAt,
            'status' => 'pending',
            'customer_name' => $command->customerName,
            'customer_email' => $command->customerEmail,
            'customer_phone' => $command->customerPhone,
            'customer_notes' => $command->customerNotes,
        ]);

        return [
            'id' => $appointment->id,
        ];
    }
}
