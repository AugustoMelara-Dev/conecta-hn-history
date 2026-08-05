<?php

declare(strict_types=1);

namespace App\Application\Availability;

use Carbon\CarbonImmutable;

interface AvailabilityQuery
{
    /**
     * @return list<array{
     *     time: string,
     *     display: string,
     *     starts_at_utc: string,
     *     ends_at_utc: string,
     *     timezone: string
     * }>
     */
    public function slots(
        string $tenantId,
        int $serviceId,
        ?int $providerId,
        string $locationId,
        CarbonImmutable $localDate,
    ): array;

    public function assertAvailable(
        string $tenantId,
        int $serviceId,
        ?int $providerId,
        string $locationId,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): AvailabilityDecision;
}
