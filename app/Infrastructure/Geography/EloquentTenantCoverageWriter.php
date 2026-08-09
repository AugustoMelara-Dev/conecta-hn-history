<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\Contracts\TenantCoverageWriter;
use App\Domain\Geography\Enums\FulfillmentMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EloquentTenantCoverageWriter implements TenantCoverageWriter
{
    private const AREA_TYPES = ['locality', 'administrative_area', 'radius', 'national'];

    public function replace(string $tenantId, string $locationId, array $modes, string $areaType, ?float $radiusKm = null): void
    {
        if ($tenantId === '' || $locationId === '' || ! in_array($areaType, self::AREA_TYPES, true)) {
            throw ValidationException::withMessages(['coverage' => 'La cobertura solicitada no es valida.']);
        }

        $normalizedModes = [];
        foreach ($modes as $mode) {
            if ($mode instanceof FulfillmentMode) {
                $normalizedModes[$mode->value] = $mode;
            }
        }
        $modes = array_values($normalizedModes);
        if ($modes === []) {
            throw ValidationException::withMessages(['modes' => 'Selecciona al menos una modalidad de atencion.']);
        }

        $location = DB::table('locations as l')
            ->leftJoin('localities as loc', 'loc.id', '=', 'l.locality_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.id', $locationId)
            ->where('l.is_active', true)
            ->select(['l.id', 'l.country_code', 'l.administrative_area_id', 'l.locality_id', 'loc.latitude', 'loc.longitude'])
            ->first();

        if ($location === null) {
            throw ValidationException::withMessages(['location_id' => 'La ubicacion no pertenece al negocio activo.']);
        }
        if ($location->locality_id === null && in_array($areaType, ['locality', 'radius'], true)) {
            throw ValidationException::withMessages(['location_id' => 'La ubicacion necesita una localidad canonica para este alcance.']);
        }
        if ($location->administrative_area_id === null && $areaType === 'administrative_area') {
            throw ValidationException::withMessages(['location_id' => 'La ubicacion necesita un area administrativa canonica.']);
        }
        if ($areaType === 'radius' && ($radiusKm === null || $radiusKm <= 0 || $radiusKm > 500 || $location->latitude === null || $location->longitude === null)) {
            throw ValidationException::withMessages(['radius_km' => 'El radio requiere entre 0 y 500 km y coordenadas oficiales para la localidad.']);
        }

        DB::transaction(function () use ($tenantId, $locationId, $location, $modes, $areaType, $radiusKm): void {
            DB::table('location_service_areas')->where('tenant_id', $tenantId)->where('location_id', $locationId)->delete();
            $now = now();
            foreach ($modes as $mode) {
                $resolvedType = match ($mode) {
                    FulfillmentMode::Storefront, FulfillmentMode::Pickup => 'locality',
                    FulfillmentMode::Remote => 'national',
                    default => $areaType,
                };
                $areaId = $resolvedType === 'administrative_area' ? (int) $location->administrative_area_id : null;
                $localityId = in_array($resolvedType, ['locality', 'radius'], true) ? (int) $location->locality_id : null;
                $radius = $resolvedType === 'radius' ? $radiusKm : null;
                $key = match ($resolvedType) {
                    'locality' => 'locality:'.$localityId,
                    'administrative_area' => 'administrative_area:'.$areaId,
                    'radius' => 'radius:'.$localityId.':'.number_format((float) $radius, 2, '.', ''),
                    default => 'national:'.strtoupper((string) $location->country_code),
                };
                DB::table('location_service_areas')->insert([
                    'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'location_id' => $locationId,
                    'fulfillment_mode' => $mode->value, 'area_type' => $resolvedType,
                    'country_code' => strtoupper((string) $location->country_code),
                    'administrative_area_id' => $areaId, 'locality_id' => $localityId,
                    'radius_km' => $radius, 'coverage_key' => $key, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('tenants')->where('id', $tenantId)->update([
                'delivery_enabled' => in_array(FulfillmentMode::Delivery, $modes, true),
                'pickup_enabled' => in_array(FulfillmentMode::Pickup, $modes, true),
                'updated_at' => $now,
            ]);
        });
    }
}
