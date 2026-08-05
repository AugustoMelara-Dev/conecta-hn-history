<?php

declare(strict_types=1);

namespace App\Application\Reservations;

use App\Models\Appointment;

final readonly class ReservationWriteResult
{
    public function __construct(
        public Appointment $appointment,
        public bool $replayed = false,
    ) {}
}
