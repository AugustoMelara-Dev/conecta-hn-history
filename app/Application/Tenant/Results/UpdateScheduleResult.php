<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

/**
 * UpdateScheduleResult - Immutable Result DTO for Schedule Updates
 *
 * DOMAIN: Tenant Governance
 * TYPE: Pure DTO (Data Transfer Object)
 * INVARIANT: No transportation coupling (HTTP/Console agnostic).
 */
final readonly class UpdateScheduleResult
{
    /**
     * @param array<string, array{isOpen: bool, open: ?string, close: ?string}> $schedule
     */
    public function __construct(
        public array $schedule
    ) {}

    /**
     * Convert to array for domain service consumption or persistence.
     *
     * @return array<string, array{isOpen: bool, open: ?string, close: ?string}>
     */
    public function toArray(): array
    {
        return $this->schedule;
    }
}
