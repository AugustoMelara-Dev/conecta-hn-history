<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

/**
 * UpdateScheduleResult - Immutable Result
 *
 * DOMAIN: Tenant Governance
 * AXIOM: Zero HTTP Leakage.
 */
final readonly class UpdateScheduleResult
{
    /**
     * @param  array<string, array{isOpen: bool, open: ?string, close: ?string}>  $schedule
     */
    public function __construct(
        public string $tenantId,
        public array $schedule
    ) {}

    public static function fromArray(array $schedule, string $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            schedule: $schedule
        );
    }
}
