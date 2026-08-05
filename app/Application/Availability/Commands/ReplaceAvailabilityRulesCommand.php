<?php

declare(strict_types=1);

namespace App\Application\Availability\Commands;

final readonly class ReplaceAvailabilityRulesCommand
{
    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  list<array<string, mixed>>  $exceptions
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $idempotencyKey,
        public string $locationId,
        public ?int $serviceId,
        public ?int $providerId,
        public array $rules,
        public array $exceptions,
    ) {}
}
