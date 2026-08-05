<?php

declare(strict_types=1);

namespace App\Application\Reservations\Commands;

use Carbon\CarbonImmutable;

final readonly class CreateReservationCommand
{
    public function __construct(
        public string $tenantId,
        public ?string $actorId,
        public string $idempotencyKey,
        public int $serviceId,
        public int $providerId,
        public string $locationId,
        public CarbonImmutable $startsAtUtc,
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public ?string $customerNotes,
        public ?string $userId = null,
        public string $source = 'public',
        public bool $publiclyBookableOnly = false,
    ) {}
}
