<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

final readonly class CancelAppointmentCommand
{
    public function __construct(
        public string|int $appointmentId,
    ) {}
}
