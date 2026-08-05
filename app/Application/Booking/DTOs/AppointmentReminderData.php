<?php

declare(strict_types=1);

namespace App\Application\Booking\DTOs;

final readonly class AppointmentReminderData
{
    public function __construct(
        public string $appointmentId,
        public string $tenantId,
        public string $tenantName,
        public string $customerEmail,
        public string $serviceName,
        public string $date,
        public string $time,
        public string $providerName,
        public string $managementUrl,
    ) {}
}
