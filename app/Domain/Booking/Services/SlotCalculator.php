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
        return array_values(array_filter(
            $this->calculateTimeline($tenantId, $date, $durationMinutes, $providerId, $bufferBefore, $bufferAfter),
            static fn (array $slot): bool => $slot['available'] === true
        ));
    }

    /**
     * Generate a full timeline with available and occupied slots.
     *
     * @return array<array{time: string, display: string, available: bool}>
     */
    public function calculateTimeline(
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

                    $slots[] = [
                        'time' => $current->format('H:i'),
                        'display' => $current->format('h:i A'),
                        'available' => $isAvailable,
                    ];
                }

                $current->addMinutes($interval);
            }
        }

        return $slots;
    }
}
