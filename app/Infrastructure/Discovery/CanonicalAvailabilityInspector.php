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

        $statuses = $this->projectableStatuses([[
            'tenant_id' => $tenantId,
            'service_id' => $serviceId,
            'location_id' => $locationId,
        ]]);

        return $statuses[$tenantId][$serviceId][$locationId] ?? 'unknown';
    }

    /**
     * @param  list<array{tenant_id:string,service_id:int,location_id:string}>  $scopes
     * @return array<string, array<int, array<string, string>>>
     */
    public function projectableStatuses(array $scopes): array
    {
        $requested = [];
        foreach ($scopes as $scope) {
            $tenantId = trim((string) ($scope['tenant_id'] ?? ''));
            $serviceId = (int) ($scope['service_id'] ?? 0);
            $locationId = trim((string) ($scope['location_id'] ?? ''));
            if ($tenantId === '' || $serviceId <= 0 || $locationId === '') {
                continue;
            }

            $requested[$this->scopeKey($tenantId, $serviceId, $locationId)] = [
                'tenant_id' => $tenantId,
                'service_id' => $serviceId,
                'location_id' => $locationId,
            ];
        }

        if ($requested === []) {
            return [];
        }

        $statuses = [];
        foreach ($requested as $scope) {
            $statuses[$scope['tenant_id']][$scope['service_id']][$scope['location_id']] = 'unknown';
        }

        $serviceIds = array_values(array_unique(array_map(
            static fn (array $scope): int => $scope['service_id'],
            array_values($requested),
        )));
        sort($serviceIds, SORT_NUMERIC);
        $requestedKeys = array_fill_keys(array_keys($requested), true);

        foreach (array_chunk($serviceIds, 500) as $serviceChunk) {
            $rules = DB::table('availability_rules')
                ->whereIn('service_id', $serviceChunk)
                ->where('is_active', true)
                ->distinct()
                ->orderBy('service_id')
                ->orderBy('tenant_id')
                ->orderBy('location_id')
                ->get(['tenant_id', 'service_id', 'location_id']);

            foreach ($rules as $rule) {
                $tenantId = (string) $rule->tenant_id;
                $serviceId = (int) $rule->service_id;
                $locationId = (string) $rule->location_id;
                $key = $this->scopeKey($tenantId, $serviceId, $locationId);
                if (! isset($requestedKeys[$key])) {
                    continue;
                }

                $statuses[$tenantId][$serviceId][$locationId] = 'available';
            }
        }

        return $statuses;
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

    private function scopeKey(string $tenantId, int $serviceId, string $locationId): string
    {
        return $tenantId.'|'.$serviceId.'|'.$locationId;
    }
}
