<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

use DateTimeInterface;

final readonly class CreateAppointmentCommand
{
    public function __construct(
        public string $tenantId,
        public string|int $serviceId,
        public string|int $serviceProviderId,
        public DateTimeInterface $scheduledAt,
        public DateTimeInterface $endAt,
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public ?string $customerNotes = null,
    ) {}
}
