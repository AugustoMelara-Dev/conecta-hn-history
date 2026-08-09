<?php

declare(strict_types=1);

namespace App\Application\Geography;

use App\Domain\Geography\Enums\FulfillmentMode;
use Illuminate\Support\Facades\DB;

final class ServiceCoverageResolver
{
    public function covers(string $tenantId, string $countryCode, ?int $administrativeAreaId, ?int $localityId, FulfillmentMode $mode, ?float $latitude = null, ?float $longitude = null): bool
    {
        if ($tenantId === '' || strlen($countryCode) !== 2) {
            return false;
        }

        $areas = DB::table('location_service_areas as a')
            ->join('locations as l', function ($join): void {
                $join->on('l.id', '=', 'a.location_id')->on('l.tenant_id', '=', 'a.tenant_id');
            })
            ->leftJoin('localities as origin', 'origin.id', '=', 'a.locality_id')
            ->where('a.tenant_id', $tenantId)->where('a.fulfillment_mode', $mode->value)
            ->where('a.is_active', true)->where('l.is_active', true)
            ->where('a.country_code', strtoupper($countryCode))
            ->select(['a.area_type', 'a.administrative_area_id', 'a.locality_id', 'a.radius_km', 'origin.latitude as origin_latitude', 'origin.longitude as origin_longitude'])
            ->get();

        $ancestorIds = $administrativeAreaId === null ? [] : $this->ancestorIds($administrativeAreaId, strtoupper($countryCode));
        foreach ($areas as $area) {
            if ($area->area_type === 'national') {
                return true;
            }
            if ($area->area_type === 'locality' && $localityId !== null && (int) $area->locality_id === $localityId) {
                return true;
            }
            if ($area->area_type === 'administrative_area' && in_array((int) $area->administrative_area_id, $ancestorIds, true)) {
                return true;
            }
            if ($area->area_type === 'radius' && $latitude !== null && $longitude !== null && $area->origin_latitude !== null && $area->origin_longitude !== null
                && $this->distanceKm((float) $area->origin_latitude, (float) $area->origin_longitude, $latitude, $longitude) <= (float) $area->radius_km) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    private function ancestorIds(int $areaId, string $countryCode): array
    {
        $ids = [];
        for ($depth = 0; $depth < 8 && $areaId > 0; $depth++) {
            $area = DB::table('administrative_areas')->where('id', $areaId)->where('country_code', $countryCode)->first(['id', 'parent_id']);
            if ($area === null) {
                break;
            }
            $ids[] = (int) $area->id;
            $areaId = (int) ($area->parent_id ?? 0);
        }

        return $ids;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return 6371.0088 * 2 * asin(min(1.0, sqrt($a)));
    }
}
