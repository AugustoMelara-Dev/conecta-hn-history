<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Application\Shared\Contracts\ServiceProviderRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Carbon\Carbon;

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
     * @param  string  $tenantId  The tenant ID for fallback hours
     * @param  Carbon  $date  The date to generate slots for
     * @param  int  $durationMinutes  Service duration in minutes
     * @param  string|null  $providerId  Optional provider ID for schedule override
     * @param  int  $bufferBefore  Minutes required before the appointment
     * @param  int  $bufferAfter  Minutes required after the appointment
     * @return array<array{time: string, display: string}>
     */
    public function calculate(
        string $tenantId,
        Carbon $date,
        int $durationMinutes,
        ?string $providerId,
        int $bufferBefore = 0,
        int $bufferAfter = 0
    ): array {
        $slots = [];

        $openingHours = clone $this->tenantRepository->getOpeningHours($tenantId);
        if ($providerId) {
            $openingHours = $this->providerRepository->getOpeningHours($providerId) ?? $openingHours;
        }

        $ranges = $openingHours->forDate($date);

        foreach ($ranges as $range) {
            $rangeStart = $date->copy()->setTimeFromTimeString($range->start()->format('H:i:s'));
            $rangeEnd = $date->copy()->setTimeFromTimeString($range->end()->format('H:i:s'));

            $interval = max(15, min($durationMinutes, 60));
            $current = $rangeStart->copy();

            while ($current < $rangeEnd) {
                $slotEnd = $current->copy()->addMinutes($durationMinutes);

                if ($slotEnd <= $rangeEnd) {
                    $isAvailable = true;

                    if ($providerId) {
                        $isAvailable = $this->providerRepository->isAvailableAt(
                            $providerId,
                            $current,
                            $slotEnd,
                            $bufferBefore,
                            $bufferAfter
                        );
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
