<?php

declare(strict_types=1);

namespace App\Application\Reservations\Commands;

final readonly class TransitionReservationCommand
{
    public function __construct(
        public string $tenantId,
        public ?string $actorId,
        public int $appointmentId,
        public string $targetStatus,
        public ?string $reason = null,
        public bool $guestInitiated = false,
    ) {}
}
