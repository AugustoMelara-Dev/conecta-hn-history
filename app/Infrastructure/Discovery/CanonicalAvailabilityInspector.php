<?php

declare(strict_types=1);

namespace App\Infrastructure\Discovery;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CanonicalAvailabilityInspector
{
    public function projectableStatus(string $tenantId, ?string $locationId, int $serviceId): string
    {
        if ($locationId === null) {
            return 'unknown';
        }

        $hasRules = DB::table('availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('is_active', true)
            ->exists();

        return $hasRules ? 'available' : 'unknown';
    }

    public function statusForDate(string $tenantId, ?string $locationId, int $serviceId, ?string $localDate): string
    {
        if ($locationId === null) {
            return 'unknown';
        }

        if ($localDate === null) {
            return $this->projectableStatus($tenantId, $locationId, $serviceId);
        }

        $date = CarbonImmutable::parse($localDate);

        $isClosed = DB::table('availability_exceptions')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('local_date', $date->toDateString())
            ->where('kind', 'closed')
            ->exists();

        if ($isClosed) {
            return 'unavailable';
        }

        $hasRule = DB::table('availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('is_active', true)
            ->where('weekday', $date->dayOfWeek)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date->toDateString());
            })
            ->exists();

        return $hasRule ? 'available' : 'unavailable';
    }
}
