<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

use Illuminate\Http\Request;

/**
 * UpdateScheduleResult - DTO for Business Hours Update
 *
 * IMMUTABILITY: This DTO is read-only after construction.
 * VALIDATION: Structural validation happens here; business rules in Action.
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
     * Create DTO from HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'schedule' => ['required', 'array'],
            'schedule.*.isOpen' => ['required', 'boolean'],
            'schedule.*.open' => ['nullable', 'date_format:H:i'],
            'schedule.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        return new self(
            schedule: $validated['schedule']
        );
    }

    /**
     * Convert to array for persistence.
     *
     * @return array<string, array{isOpen: bool, open: ?string, close: ?string}>
     */
    public function toArray(): array
    {
        return $this->schedule;
    }
}
