<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use Carbon\Carbon;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Application\Shared\Contracts\ServiceProviderRepositoryInterface;

final class SlotCalculator
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly ServiceProviderRepositoryInterface $providerRepository
    ) {}

    /**
     * Generate available time slots for a given date.
     *
     * Uses spatie/opening-hours to respect tenant business hours
     * and provider schedule overrides.
     *
     * @param string $tenantId The tenant ID for fallback hours
     * @param Carbon $date The date to generate slots for
     * @param int $durationMinutes Service duration in minutes
     * @param string|null $providerId Optional provider ID for schedule override
     * @return array<array{time: string, display: string}>
     */
    public function calculate(
        string $tenantId,
        Carbon $date,
        int $durationMinutes,
        ?string $providerId
    ): array {
        $slots = [];

        // Decoupled OpeningHours abstraction
        $openingHours = clone $this->tenantRepository->getOpeningHours($tenantId);
        if ($providerId) {
            $openingHours = $this->providerRepository->getOpeningHours($providerId) ?? $openingHours;
        }

        // Get open ranges for the requested date (handles closed days automatically)
        $ranges = $openingHours->forDate($date);

        // Each range is a Spatie\OpeningHours\TimeRange with start/end
        foreach ($ranges as $range) {
            $rangeStart = $date->copy()->setTimeFromTimeString($range->start()->format('H:i:s'));
            $rangeEnd = $date->copy()->setTimeFromTimeString($range->end()->format('H:i:s'));

            // Round duration to nearest 15 minutes for reasonable slot intervals
            $interval = max(15, min($durationMinutes, 60));
            $current = $rangeStart->copy();

            while ($current < $rangeEnd) {
                $slotEnd = $current->copy()->addMinutes($durationMinutes);

                // Slot must fit strictly within the open range
                if ($slotEnd <= $rangeEnd) {
                    $isAvailable = true;

                    // If provider specified, check for appointment conflicts
                    if ($providerId) {
                        $isAvailable = $this->providerRepository->isAvailableAt($providerId, $current, $slotEnd);
                    }

                    if ($isAvailable) {
                        $slots[] = [
                            'time' => $current->format('H:i'),
                            'display' => $current->format('h:i A'),
                        ];
                    }
                }

                $current->addMinutes($interval);
            }
        }

        return $slots;
    }
}
