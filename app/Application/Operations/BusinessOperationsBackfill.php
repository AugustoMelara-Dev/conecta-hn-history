<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BusinessOperationsBackfill
{
    /** @var list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}> */
    private array $gaps = [];

    /** @var list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}> */
    private array $conflicts = [];

    public function run(bool $dryRun = false, int $limit = 0, int $offset = 0): BusinessOperationsBackfillReport
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('The backfill limit and offset must be zero or greater.');
        }

        $this->gaps = [];
        $this->conflicts = [];

        $operation = function () use ($limit, $offset): void {
            $tenants = DB::table('tenants')->orderBy('id');

            if ($limit > 0) {
                $tenants->offset($offset)->limit($limit);
            } elseif ($offset > 0) {
                $tenants->offset($offset);
            }

            $tenants->each(function (object $tenant): void {
                $tenantId = (string) $tenant->id;
                $market = $this->marketForTenant($tenant);
                $location = $this->primaryLocation($tenantId);

                if ($market === null) {
                    $this->gap(
                        'tenant_market_missing',
                        $tenantId,
                        'tenant',
                        $tenantId,
                        'No explicit canonical market or legacy Honduras city can establish currency and timezone.',
                    );
                }

                if ($location === null) {
                    $this->gap(
                        'tenant_location_missing',
                        $tenantId,
                        'tenant',
                        $tenantId,
                        'No canonical primary location exists; no operational location was invented.',
                    );
                }

                $this->backfillProducts($tenantId, $market);
                $this->backfillServices($tenantId, $market, $location);
                $this->backfillProviders($tenantId, $market, $location);
                $this->backfillAppointments($tenantId, $market, $location);
                $this->reportDealCurrencyConflicts($tenantId, $market);
            });
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $operation();
                $report = $this->report();
                DB::rollBack();

                return $report;
            } catch (\Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                throw $exception;
            }
        }

        DB::transaction($operation);

        return $this->report();
    }

    private function report(): BusinessOperationsBackfillReport
    {
        $this->sortIssues($this->gaps);
        $this->sortIssues($this->conflicts);

        return new BusinessOperationsBackfillReport(
            productsMapped: DB::table('products')
                ->whereNotNull('currency')
                ->whereNotNull('price_minor')
                ->count(),
            servicesMapped: DB::table('services')
                ->whereNotNull('currency')
                ->whereNotNull('price_minor')
                ->count(),
            providersMapped: DB::table('service_provider_locations')
                ->distinct()
                ->count('service_provider_id'),
            appointmentsMapped: DB::table('appointments')
                ->whereNotNull('location_id')
                ->whereNotNull('currency')
                ->whereNotNull('price_minor')
                ->whereNotNull('timezone')
                ->whereNotNull('local_date')
                ->count(),
            dealsMapped: DB::table('crm_deals')->whereNotNull('currency')->count(),
            availabilityRulesTotal: DB::table('availability_rules')
                ->where('source', 'legacy_provider_schedule')
                ->count(),
            locationsMapped: DB::table('service_locations')->count()
                + DB::table('service_provider_locations')->count(),
            gaps: $this->gaps,
            conflicts: $this->conflicts,
        );
    }

    /**
     * @return object{currency_code: string, timezone_name: string}|null
     */
    private function marketForTenant(object $tenant): ?object
    {
        $canonical = DB::table('tenant_markets')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->first(['currency_code', 'timezone_name']);

        if ($canonical !== null) {
            return $canonical;
        }

        if ($tenant->city_id === null
            || ! DB::table('currencies')->where('code', 'HNL')->exists()
            || ! DB::table('timezones')->where('name', 'America/Tegucigalpa')->exists()) {
            return null;
        }

        return (object) [
            'currency_code' => 'HNL',
            'timezone_name' => 'America/Tegucigalpa',
        ];
    }

    private function primaryLocation(string $tenantId): ?object
    {
        return DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->first(['id', 'currency_code', 'timezone_name']);
    }

    private function backfillServices(string $tenantId, ?object $market, ?object $location): void
    {
        DB::table('services')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->each(function (object $service) use ($tenantId, $market, $location): void {
                $effectiveCurrency = $service->currency ?? $market?->currency_code;
                $updates = [];

                if ($service->currency === null && $market !== null) {
                    $updates['currency'] = $market->currency_code;
                } elseif ($service->currency !== null
                    && $market !== null
                    && $service->currency !== $market->currency_code) {
                    $this->conflict(
                        'service_currency_preserved',
                        $tenantId,
                        'service',
                        (string) $service->id,
                        "Historical service currency [{$service->currency}] differs from market [{$market->currency_code}] and was preserved.",
                    );
                }

                if ($service->price_minor === null && is_string($effectiveCurrency)) {
                    $minorUnit = DB::table('currencies')
                        ->where('code', $effectiveCurrency)
                        ->value('minor_unit');
                    $priceMinor = $this->decimalToMinor(
                        $service->price,
                        is_numeric($minorUnit) ? (int) $minorUnit : 2,
                    );
                    if ($priceMinor === null) {
                        $this->gap(
                            'service_price_precision_unsupported',
                            $tenantId,
                            'service',
                            (string) $service->id,
                            'The legacy decimal price cannot be represented exactly in the market currency minor unit.',
                        );
                    } else {
                        $updates['price_minor'] = $priceMinor;
                    }
                }

                if ($updates !== []) {
                    DB::table('services')
                        ->where('id', $service->id)
                        ->where('tenant_id', $tenantId)
                        ->update($updates + ['updated_at' => now()]);
                }

                if ($location !== null) {
                    DB::table('service_locations')->updateOrInsert(
                        [
                            'tenant_id' => $tenantId,
                            'service_id' => $service->id,
                            'location_id' => $location->id,
                        ],
                        ['updated_at' => now(), 'created_at' => $service->created_at ?? now()],
                    );
                }
            });
    }

    private function backfillProducts(string $tenantId, ?object $market): void
    {
        DB::table('products')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->each(function (object $product) use ($tenantId, $market): void {
                $effectiveCurrency = $product->currency ?? $market?->currency_code;
                $updates = [];

                if ($product->currency === null && $market !== null) {
                    $updates['currency'] = $market->currency_code;
                } elseif ($product->currency !== null
                    && $market !== null
                    && $product->currency !== $market->currency_code) {
                    $this->conflict(
                        'product_currency_preserved',
                        $tenantId,
                        'product',
                        (string) $product->id,
                        "Historical product currency [{$product->currency}] differs from market [{$market->currency_code}] and was preserved.",
                    );
                }

                if ($product->price_minor === null && is_string($effectiveCurrency)) {
                    $minorUnit = DB::table('currencies')
                        ->where('code', $effectiveCurrency)
                        ->value('minor_unit');
                    $priceMinor = $this->decimalToMinor(
                        $product->price,
                        is_numeric($minorUnit) ? (int) $minorUnit : 2,
                    );
                    if ($priceMinor === null) {
                        $this->gap(
                            'product_price_precision_unsupported',
                            $tenantId,
                            'product',
                            (string) $product->id,
                            'The legacy decimal price cannot be represented exactly in the market currency minor unit.',
                        );
                    } else {
                        $updates['price_minor'] = $priceMinor;
                    }
                }

                if ($updates !== []) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->where('tenant_id', $tenantId)
                        ->update($updates + ['updated_at' => now()]);
                }
            });
    }

    private function backfillProviders(string $tenantId, ?object $market, ?object $location): void
    {
        DB::table('service_providers')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->each(function (object $provider) use ($tenantId, $market, $location): void {
                if ($location !== null) {
                    DB::table('service_provider_locations')->updateOrInsert(
                        [
                            'tenant_id' => $tenantId,
                            'service_provider_id' => $provider->id,
                            'location_id' => $location->id,
                        ],
                        ['updated_at' => now(), 'created_at' => $provider->created_at ?? now()],
                    );
                }

                if ($provider->schedule_config === null || $provider->schedule_config === '') {
                    return;
                }

                if ($market === null || $location === null) {
                    $this->gap(
                        'provider_schedule_context_missing',
                        $tenantId,
                        'service_provider',
                        (string) $provider->id,
                        'A legacy provider schedule cannot be mapped without explicit market and location context.',
                    );

                    return;
                }

                $schedule = json_decode((string) $provider->schedule_config, true);
                $ranges = is_array($schedule) ? $this->normalizedScheduleRanges($schedule) : null;

                if ($ranges === null) {
                    $this->gap(
                        'provider_schedule_invalid',
                        $tenantId,
                        'service_provider',
                        (string) $provider->id,
                        'The legacy schedule is malformed and did not grant availability.',
                    );

                    return;
                }

                foreach ($ranges as $range) {
                    $sourceKey = implode(':', [
                        'legacy-provider',
                        $provider->id,
                        $range['weekday'],
                        $range['starts_at_local'],
                        $range['ends_at_local'],
                        $location->id,
                    ]);
                    $values = [
                        'location_id' => $location->id,
                        'service_id' => null,
                        'service_provider_id' => $provider->id,
                        'weekday' => $range['weekday'],
                        'starts_at_local' => $range['starts_at_local'],
                        'ends_at_local' => $range['ends_at_local'],
                        'timezone' => $location->timezone_name ?: $market->timezone_name,
                        'effective_from' => null,
                        'effective_until' => null,
                        'is_active' => true,
                        'source' => 'legacy_provider_schedule',
                        'updated_at' => now(),
                    ];
                    $existingId = DB::table('availability_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('source_key', $sourceKey)
                        ->value('id');

                    if ($existingId === null) {
                        DB::table('availability_rules')->insert($values + [
                            'id' => (string) Str::uuid(),
                            'tenant_id' => $tenantId,
                            'source_key' => $sourceKey,
                            'created_at' => $provider->created_at ?? now(),
                        ]);
                    } else {
                        DB::table('availability_rules')
                            ->where('id', $existingId)
                            ->where('tenant_id', $tenantId)
                            ->update($values);
                    }
                }
            });
    }

    private function backfillAppointments(string $tenantId, ?object $market, ?object $location): void
    {
        DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->each(function (object $appointment) use ($tenantId, $market, $location): void {
                if ($market === null || $location === null) {
                    if ($appointment->location_id === null
                        || $appointment->currency === null
                        || $appointment->timezone === null) {
                        $this->gap(
                            'appointment_snapshot_context_missing',
                            $tenantId,
                            'appointment',
                            (string) $appointment->id,
                            'The reservation snapshot remains incomplete because market or location context is missing.',
                        );
                    }

                    return;
                }

                $service = DB::table('services')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $appointment->service_id)
                    ->first(['name', 'duration_minutes', 'price', 'currency']);
                if ($service === null) {
                    $this->gap(
                        'appointment_service_missing',
                        $tenantId,
                        'appointment',
                        (string) $appointment->id,
                        'The linked service is unavailable; price was not invented.',
                    );

                    return;
                }

                $minorUnit = DB::table('currencies')
                    ->where('code', $service->currency ?: $market->currency_code)
                    ->value('minor_unit');
                $priceMinor = $this->decimalToMinor($service->price, is_numeric($minorUnit) ? (int) $minorUnit : 2);
                if ($priceMinor === null) {
                    $this->gap(
                        'service_price_precision_unsupported',
                        $tenantId,
                        'service',
                        (string) $appointment->service_id,
                        'The legacy decimal price cannot be represented exactly in the market currency minor unit.',
                    );

                    return;
                }

                $currency = (string) ($service->currency ?: $market->currency_code);
                $timezone = (string) ($location->timezone_name ?: $market->timezone_name);
                $localDate = substr((string) $appointment->scheduled_at, 0, 10);
                $updates = array_filter([
                    'location_id' => $appointment->location_id === null ? $location->id : null,
                    'service_name_snapshot' => $appointment->service_name_snapshot === null
                        ? (string) $service->name
                        : null,
                    'duration_minutes' => $appointment->duration_minutes === null
                        ? (int) $service->duration_minutes
                        : null,
                    'currency' => $appointment->currency === null ? $currency : null,
                    'price_minor' => $appointment->price_minor === null ? $priceMinor : null,
                    'timezone' => $appointment->timezone === null ? $timezone : null,
                    'local_date' => $appointment->local_date === null ? $localDate : null,
                    'source' => $appointment->source === null ? 'legacy_backfill' : null,
                ], static fn (mixed $value): bool => $value !== null);

                if ($updates !== []) {
                    DB::table('appointments')
                        ->where('id', $appointment->id)
                        ->where('tenant_id', $tenantId)
                        ->update($updates + ['updated_at' => now()]);
                }
            });
    }

    private function reportDealCurrencyConflicts(string $tenantId, ?object $market): void
    {
        if ($market === null) {
            return;
        }

        DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('currency')
            ->where('currency', '!=', $market->currency_code)
            ->orderBy('id')
            ->each(function (object $deal) use ($tenantId, $market): void {
                $this->conflict(
                    'deal_currency_preserved',
                    $tenantId,
                    'crm_deal',
                    (string) $deal->id,
                    "Historical deal currency [{$deal->currency}] differs from market [{$market->currency_code}] and was preserved.",
                );
            });
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return list<array{weekday: int, starts_at_local: string, ends_at_local: string}>|null
     */
    private function normalizedScheduleRanges(array $schedule): ?array
    {
        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];
        $normalized = [];

        foreach ($schedule as $day => $ranges) {
            $day = strtolower((string) $day);
            if (! isset($days[$day]) || ! is_array($ranges)) {
                return null;
            }

            foreach ($ranges as $range) {
                if (! is_string($range)
                    || preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?-([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $range, $matches) !== 1) {
                    return null;
                }

                $startSeconds = ($matches[3] ?? '') !== '' ? $matches[3] : '00';
                $endSeconds = ($matches[6] ?? '') !== '' ? $matches[6] : '00';
                $start = sprintf('%s:%s:%s', $matches[1], $matches[2], $startSeconds);
                $end = sprintf('%s:%s:%s', $matches[4], $matches[5], $endSeconds);
                if ($start >= $end) {
                    return null;
                }
                $normalized[] = [
                    'weekday' => $days[$day],
                    'starts_at_local' => $start,
                    'ends_at_local' => $end,
                ];
            }
        }

        return $normalized;
    }

    private function decimalToMinor(mixed $value, int $minorUnit): ?int
    {
        $decimal = trim((string) $value);
        if ($minorUnit < 0
            || $minorUnit > 6
            || preg_match('/^(\d+)(?:\.(\d+))?$/', $decimal, $matches) !== 1) {
            return null;
        }

        $fraction = $matches[2] ?? '';
        if (strlen($fraction) > $minorUnit
            && trim(substr($fraction, $minorUnit), '0') !== '') {
            return null;
        }

        $minorDigits = str_pad(substr($fraction, 0, $minorUnit), $minorUnit, '0');
        $combined = ltrim($matches[1].$minorDigits, '0');
        if ($combined === '') {
            return 0;
        }
        if (strlen($combined) > strlen((string) PHP_INT_MAX)
            || (strlen($combined) === strlen((string) PHP_INT_MAX)
                && strcmp($combined, (string) PHP_INT_MAX) > 0)) {
            return null;
        }

        return (int) $combined;
    }

    private function gap(
        string $code,
        string $tenantId,
        string $resourceType,
        ?string $resourceId,
        string $detail,
    ): void {
        $this->addIssue($this->gaps, $code, $tenantId, $resourceType, $resourceId, $detail);
    }

    private function conflict(
        string $code,
        string $tenantId,
        string $resourceType,
        ?string $resourceId,
        string $detail,
    ): void {
        $this->addIssue($this->conflicts, $code, $tenantId, $resourceType, $resourceId, $detail);
    }

    /**
     * @param  list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}>  $issues
     */
    private function addIssue(
        array &$issues,
        string $code,
        string $tenantId,
        string $resourceType,
        ?string $resourceId,
        string $detail,
    ): void {
        $candidate = [
            'code' => $code,
            'tenant_id' => $tenantId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'detail' => $detail,
        ];

        if (! in_array($candidate, $issues, true)) {
            $issues[] = $candidate;
        }
    }

    /**
     * @param  list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}>  $issues
     */
    private function sortIssues(array &$issues): void
    {
        usort($issues, static fn (array $left, array $right): int => [
            $left['tenant_id'],
            $left['resource_type'],
            $left['resource_id'] ?? '',
            $left['code'],
        ] <=> [
            $right['tenant_id'],
            $right['resource_type'],
            $right['resource_id'] ?? '',
            $right['code'],
        ]);
    }
}
