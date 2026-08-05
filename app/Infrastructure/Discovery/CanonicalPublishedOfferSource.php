<?php

declare(strict_types=1);

namespace App\Infrastructure\Discovery;

use App\Application\Discovery\AgentCapabilityProjection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class CanonicalPublishedOfferSource
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $agentCapabilityCache = [];

    public function __construct(
        private readonly CanonicalAvailabilityInspector $availability,
        private readonly AgentCapabilityProjection $agentCapabilities,
    ) {}

    /** @return list<array<string, mixed>> */
    public function currentOfferCandidates(): array
    {
        return array_merge($this->productCandidates(), $this->serviceCandidates());
    }

    /** @return array<string, mixed>|null */
    public function candidateForProjectionKey(string $projectionKey): ?array
    {
        foreach ($this->currentOfferCandidates() as $candidate) {
            if ((string) $candidate['projection_key'] === $projectionKey) {
                return $candidate;
            }
        }

        return null;
    }

    public function tenantCanPublish(string $tenantId): bool
    {
        $query = DB::table('tenants')->where('id', $tenantId);

        if (Schema::hasColumn('tenants', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $columns = ['is_active', 'subscription_status'];
        if (Schema::hasColumn('tenants', 'status')) {
            $columns[] = 'status';
        }

        $tenant = $query->first($columns);

        if ($tenant === null) {
            return false;
        }

        return (bool) $tenant->is_active
            && in_array((string) $tenant->subscription_status, ['active', 'trial'], true)
            && (! isset($tenant->status)
                || ! in_array((string) $tenant->status, ['draft', 'suspended', 'disabled', 'archived', 'rejected'], true));
    }

    /** @return list<array<string, mixed>> */
    private function productCandidates(): array
    {
        $query = DB::table('products as p')
            ->join('tenants as t', 't.id', '=', 'p.tenant_id')
            ->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('tenant_markets as tm', function ($join): void {
                $join->on('tm.tenant_id', '=', 't.id')
                    ->where('tm.is_primary', '=', 1);
            })
            ->leftJoin('market_pack_versions as mpv', 'mpv.id', '=', 'tm.market_pack_version_id')
            ->leftJoin('market_packs as mp', 'mp.id', '=', 'mpv.market_pack_id')
            ->leftJoin('countries as mc', 'mc.code', '=', 'mp.country_code')
            ->select([
                'p.id as source_record_id',
                'p.tenant_id as source_tenant_id',
                'p.name as offer_name',
                'p.description as offer_description',
                'p.price',
                'p.price_minor',
                'p.currency',
                'p.manage_stock',
                'p.stock_quantity',
                'p.allow_backorders',
                'p.updated_at as source_updated_at',
                't.name as business_name',
                't.slug as business_slug',
                'tm.id as tenant_market_id',
                'tm.market_pack_version_id',
                'tm.country_code as market_country_code',
                'tm.locale_code as market_locale_code',
                'tm.currency_code as market_currency_code',
                'tm.timezone_name as market_timezone_name',
                'mp.country_code as market_pack_country_code',
                'mp.is_active as market_pack_active',
                'mpv.status as market_pack_status',
                'mpv.published_at as market_pack_published_at',
                'mpv.effective_from as market_pack_effective_from',
                'mpv.supported_locales as market_pack_supported_locales',
                'mpv.supported_currencies as market_pack_supported_currencies',
                'mpv.supported_timezones as market_pack_supported_timezones',
                'mc.is_active as market_country_active',
                't.category_id as business_category_id',
                'c.slug as business_category_slug',
                'c.is_active as business_category_active',
            ])
            ->when(Schema::hasColumn('tenants', 'verification_status'), fn ($q) => $q->addSelect('t.verification_status'))
            ->when(! Schema::hasColumn('tenants', 'verification_status'), fn ($q) => $q->addSelect(DB::raw("'unknown' as verification_status")));

        if (Schema::hasColumn('products', 'deleted_at')) {
            $query->whereNull('p.deleted_at');
        }

        if (Schema::hasColumn('tenants', 'deleted_at')) {
            $query->whereNull('t.deleted_at');
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('p.is_active', true);
        }

        if (Schema::hasColumn('products', 'is_visible')) {
            $query->where('p.is_visible', true);
        }

        if (Schema::hasColumn('products', 'is_locked')) {
            $query->where('p.is_locked', false);
        }

        if (Schema::hasColumn('products', 'status')) {
            $query->whereIn('p.status', ['active', 'approved', 'published']);
        }

        if (Schema::hasColumn('tenants', 'is_active')) {
            $query->where('t.is_active', true);
        }

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $query->whereIn('t.subscription_status', ['active', 'trial']);
        }

        if (Schema::hasColumn('tenants', 'status')) {
            $query->whereNotIn('t.status', ['draft', 'suspended', 'disabled', 'archived', 'rejected']);
        }

        $rows = $query->get();
        $productContexts = [];
        foreach ($rows as $row) {
            $market = $this->validMarketContext($row);
            $productContexts[(int) $row->source_record_id] = [
                'market' => $market,
                'tenant_id' => (string) $row->source_tenant_id,
                'country_code' => $market['country_code'] ?? null,
            ];
        }
        $productLocations = $this->productLocationMap($productContexts);

        return $rows
            ->map(function (object $row) use ($productContexts, $productLocations): array {
                $context = $productContexts[(int) $row->source_record_id];
                $market = $context['market'];
                $marketContextExplicit = $market !== null;
                $countryCode = $market['country_code'] ?? null;
                $localeCode = $market['locale_code'] ?? null;
                $currencyCode = $row->currency ?: ($marketContextExplicit ? $row->market_currency_code : null);
                $timezoneName = $market['timezone_name'] ?? null;
                $availabilityStatus = $this->productAvailabilityStatus($row);
                $locationKey = $this->locationMapKey((string) $row->source_tenant_id, $countryCode);
                $locations = array_map(
                    static fn (array $location): array => array_merge($location, [
                        'availability_status' => $availabilityStatus,
                    ]),
                    $productLocations[$locationKey] ?? [],
                );
                $baseLocation = $locations[0] ?? null;
                $marketCurrencyCompatible = $marketContextExplicit
                    && ($row->market_currency_code === null
                        || strtoupper((string) $currencyCode) === strtoupper((string) $row->market_currency_code));
                $priceMinor = $row->price_minor !== null
                    ? (int) $row->price_minor
                    : (int) round(((float) $row->price) * 100);
                $agentEvidence = $this->agentCapabilityEvidence(
                    (string) $row->source_tenant_id,
                    $row->source_updated_at,
                );
                $categoryId = (bool) ($row->business_category_active ?? false)
                    && $row->business_category_id !== null
                    ? (int) $row->business_category_id
                    : null;
                $categorySlug = $categoryId !== null ? (string) $row->business_category_slug : null;

                return [
                    'projection_key' => 'product:'.$row->source_record_id,
                    'source_type' => 'product',
                    'source_record_id' => (int) $row->source_record_id,
                    'source_tenant_id' => (string) $row->source_tenant_id,
                    'business_category_id' => $categoryId,
                    'business_category_slug' => $categorySlug,
                    'tenant_market_id' => $market['id'] ?? null,
                    'location_id' => $baseLocation['location_id'] ?? null,
                    'country_code' => $countryCode,
                    'locale_code' => $localeCode,
                    'currency_code' => $currencyCode,
                    'timezone_name' => $timezoneName,
                    'market_context_explicit' => $marketContextExplicit,
                    'market_currency_compatible' => $marketCurrencyCompatible,
                    'business_name' => (string) $row->business_name,
                    'business_slug' => $row->business_slug !== null ? (string) $row->business_slug : null,
                    'business_url' => $this->businessUrl($row->business_slug !== null ? (string) $row->business_slug : null),
                    'offer_name' => (string) $row->offer_name,
                    'offer_description' => $row->offer_description !== null ? (string) $row->offer_description : null,
                    'price_minor' => $priceMinor,
                    'price_display' => sprintf('%s %s', strtoupper((string) $currencyCode), number_format($priceMinor / 100, 2)),
                    'availability_status' => $availabilityStatus,
                    'verification_level' => strtolower((string) $row->verification_status),
                    'source_updated_at' => $row->source_updated_at !== null
                        ? CarbonImmutable::parse((string) $row->source_updated_at)
                        : CarbonImmutable::now(),
                    'search_document' => implode(' ', array_filter([
                        $row->offer_name,
                        $row->offer_description,
                        $row->business_name,
                        $baseLocation['location_name'] ?? null,
                    ])),
                    'locations' => $locations,
                    'evidence' => array_values(array_filter([
                        [
                            'evidence_type' => 'catalog_offer',
                            'evidence_key' => 'product:'.$row->source_record_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'products',
                            'source_identifier' => (string) $row->source_record_id,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Published product offer',
                            'facts' => ['price_minor' => $priceMinor, 'currency_code' => $currencyCode],
                        ],
                        $countryCode !== null ? [
                            'evidence_type' => 'tenant_market',
                            'evidence_key' => 'market:'.$market['id'],
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'tenant_markets',
                            'source_identifier' => $market['id'],
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Primary tenant market',
                            'facts' => ['country_code' => $countryCode, 'currency_code' => $currencyCode],
                        ] : null,
                        ...$this->locationEvidence($locations, (string) $row->source_tenant_id, $row->source_updated_at),
                        ...$agentEvidence,
                    ])),
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function serviceCandidates(): array
    {
        $query = DB::table('services as s')
            ->join('tenants as t', 't.id', '=', 's.tenant_id')
            ->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('tenant_markets as tm', function ($join): void {
                $join->on('tm.tenant_id', '=', 't.id')
                    ->where('tm.is_primary', '=', 1);
            })
            ->leftJoin('market_pack_versions as mpv', 'mpv.id', '=', 'tm.market_pack_version_id')
            ->leftJoin('market_packs as mp', 'mp.id', '=', 'mpv.market_pack_id')
            ->leftJoin('countries as mc', 'mc.code', '=', 'mp.country_code')
            ->select([
                's.id as source_record_id',
                's.tenant_id as source_tenant_id',
                's.name as offer_name',
                's.description as offer_description',
                's.price',
                's.price_minor',
                's.currency',
                's.updated_at as source_updated_at',
                't.name as business_name',
                't.slug as business_slug',
                'tm.id as tenant_market_id',
                'tm.market_pack_version_id',
                'tm.country_code as market_country_code',
                'tm.locale_code as market_locale_code',
                'tm.currency_code as market_currency_code',
                'tm.timezone_name as market_timezone_name',
                'mp.country_code as market_pack_country_code',
                'mp.is_active as market_pack_active',
                'mpv.status as market_pack_status',
                'mpv.published_at as market_pack_published_at',
                'mpv.effective_from as market_pack_effective_from',
                'mpv.supported_locales as market_pack_supported_locales',
                'mpv.supported_currencies as market_pack_supported_currencies',
                'mpv.supported_timezones as market_pack_supported_timezones',
                'mc.is_active as market_country_active',
                't.category_id as business_category_id',
                'c.slug as business_category_slug',
                'c.is_active as business_category_active',
            ])
            ->when(Schema::hasColumn('tenants', 'verification_status'), fn ($q) => $q->addSelect('t.verification_status'))
            ->when(! Schema::hasColumn('tenants', 'verification_status'), fn ($q) => $q->addSelect(DB::raw("'unknown' as verification_status")));

        if (Schema::hasColumn('services', 'is_active')) {
            $query->where('s.is_active', true);
        }

        if (Schema::hasColumn('services', 'deleted_at')) {
            $query->whereNull('s.deleted_at');
        }

        if (Schema::hasColumn('tenants', 'is_active')) {
            $query->where('t.is_active', true);
        }

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $query->whereIn('t.subscription_status', ['active', 'trial']);
        }

        if (Schema::hasColumn('tenants', 'status')) {
            $query->whereNotIn('t.status', ['draft', 'suspended', 'disabled', 'archived', 'rejected']);
        }

        if (Schema::hasColumn('services', 'status')) {
            $query->whereNotIn('s.status', ['draft', 'suspended', 'disabled', 'archived', 'rejected']);
        }

        $rows = $query->get();
        $serviceContexts = [];
        foreach ($rows as $row) {
            $market = $this->validMarketContext($row);
            $serviceContexts[(int) $row->source_record_id] = [
                'market' => $market,
                'tenant_id' => (string) $row->source_tenant_id,
                'country_code' => $market['country_code'] ?? null,
            ];
        }
        $serviceLocations = $this->serviceLocationMap($serviceContexts);

        return $rows
            ->map(function (object $row) use ($serviceContexts, $serviceLocations): array {
                $context = $serviceContexts[(int) $row->source_record_id];
                $market = $context['market'];
                $marketContextExplicit = $market !== null;
                $countryCode = $market['country_code'] ?? null;
                $localeCode = $market['locale_code'] ?? null;
                $currencyCode = $row->currency ?: ($marketContextExplicit ? $row->market_currency_code : null);
                $timezoneName = $market['timezone_name'] ?? null;
                $locations = $serviceLocations[(int) $row->source_record_id] ?? [];
                $baseLocation = $this->baseLocation($locations);
                $availabilityStatus = $this->aggregateAvailability($locations);
                $marketCurrencyCompatible = $marketContextExplicit
                    && ($row->market_currency_code === null
                        || strtoupper((string) $currencyCode) === strtoupper((string) $row->market_currency_code));
                $priceMinor = $row->price_minor !== null
                    ? (int) $row->price_minor
                    : (int) round(((float) $row->price) * 100);
                $agentEvidence = $this->agentCapabilityEvidence(
                    (string) $row->source_tenant_id,
                    $row->source_updated_at,
                );
                $categoryId = (bool) ($row->business_category_active ?? false)
                    && $row->business_category_id !== null
                    ? (int) $row->business_category_id
                    : null;
                $categorySlug = $categoryId !== null ? (string) $row->business_category_slug : null;

                return [
                    'projection_key' => 'service:'.$row->source_record_id,
                    'source_type' => 'service',
                    'source_record_id' => (int) $row->source_record_id,
                    'source_tenant_id' => (string) $row->source_tenant_id,
                    'business_category_id' => $categoryId,
                    'business_category_slug' => $categorySlug,
                    'tenant_market_id' => $market['id'] ?? null,
                    'location_id' => $baseLocation['location_id'] ?? null,
                    'country_code' => $countryCode,
                    'locale_code' => $localeCode,
                    'currency_code' => $currencyCode,
                    'timezone_name' => $timezoneName,
                    'market_context_explicit' => $marketContextExplicit,
                    'market_currency_compatible' => $marketCurrencyCompatible,
                    'business_name' => (string) $row->business_name,
                    'business_slug' => $row->business_slug !== null ? (string) $row->business_slug : null,
                    'business_url' => $this->businessUrl($row->business_slug !== null ? (string) $row->business_slug : null),
                    'offer_name' => (string) $row->offer_name,
                    'offer_description' => $row->offer_description !== null ? (string) $row->offer_description : null,
                    'price_minor' => $priceMinor,
                    'price_display' => sprintf('%s %s', strtoupper((string) $currencyCode), number_format($priceMinor / 100, 2)),
                    'availability_status' => $availabilityStatus,
                    'verification_level' => strtolower((string) $row->verification_status),
                    'source_updated_at' => $row->source_updated_at !== null
                        ? CarbonImmutable::parse((string) $row->source_updated_at)
                        : CarbonImmutable::now(),
                    'search_document' => implode(' ', array_filter([
                        $row->offer_name,
                        $row->offer_description,
                        $row->business_name,
                        $baseLocation['location_name'] ?? null,
                    ])),
                    'locations' => $locations,
                    'evidence' => array_values(array_filter([
                        [
                            'evidence_type' => 'catalog_offer',
                            'evidence_key' => 'service:'.$row->source_record_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'services',
                            'source_identifier' => (string) $row->source_record_id,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Published service offer',
                            'facts' => ['price_minor' => $priceMinor, 'currency_code' => $currencyCode],
                        ],
                        $countryCode !== null ? [
                            'evidence_type' => 'tenant_market',
                            'evidence_key' => 'market:'.$market['id'],
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'tenant_markets',
                            'source_identifier' => $market['id'],
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Primary tenant market',
                            'facts' => ['country_code' => $countryCode, 'currency_code' => $currencyCode],
                        ] : null,
                        ...$this->locationEvidence($locations, (string) $row->source_tenant_id, $row->source_updated_at),
                        ...$this->availabilityEvidence($locations, (string) $row->source_record_id, (string) $row->source_tenant_id, $row->source_updated_at, $timezoneName),
                        ...$agentEvidence,
                    ])),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array{market:array<string,mixed>|null,tenant_id:string,country_code:?string}>  $contexts
     * @return array<string, list<array<string, mixed>>>
     */
    private function productLocationMap(array $contexts): array
    {
        $countriesByTenant = [];
        foreach ($contexts as $context) {
            if ($context['country_code'] === null) {
                continue;
            }

            $countriesByTenant[$context['tenant_id']][strtoupper($context['country_code'])] = true;
        }

        $tenantIds = array_keys($countriesByTenant);
        sort($tenantIds, SORT_STRING);
        $map = [];

        foreach (array_chunk($tenantIds, 500) as $tenantChunk) {
            $locations = DB::table('locations as l')
                ->leftJoin('localities as lo', function ($join): void {
                    $join->on('lo.id', '=', 'l.locality_id')
                        ->whereColumn('lo.country_code', '=', 'l.country_code')
                        ->where('lo.is_active', '=', 1);
                })
                ->whereIn('l.tenant_id', $tenantChunk)
                ->where('l.is_active', true)
                ->where('l.is_primary', true)
                ->orderBy('l.tenant_id')
                ->orderBy('l.country_code')
                ->orderBy('l.id')
                ->get([
                    'l.tenant_id',
                    'l.id as location_id',
                    'l.name as location_name',
                    'l.country_code',
                    'l.is_primary',
                    'lo.id as locality_id',
                    'lo.slug as locality_slug',
                ]);

            foreach ($locations as $location) {
                $tenantId = (string) $location->tenant_id;
                $countryCode = strtoupper((string) $location->country_code);
                if (! isset($countriesByTenant[$tenantId][$countryCode])) {
                    continue;
                }

                $key = $this->locationMapKey($tenantId, $countryCode);
                if (isset($map[$key])) {
                    continue;
                }

                $map[$key] = [$this->locationPayload($location, null, $tenantId)];
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{market:array<string,mixed>|null,tenant_id:string,country_code:?string}>  $contexts
     * @return array<int, list<array<string, mixed>>>
     */
    private function serviceLocationMap(array $contexts): array
    {
        $authority = [];
        foreach ($contexts as $serviceId => $context) {
            if ($context['country_code'] === null) {
                continue;
            }

            $authority[(int) $serviceId] = [
                'tenant_id' => $context['tenant_id'],
                'country_code' => strtoupper($context['country_code']),
            ];
        }

        $serviceIds = array_keys($authority);
        sort($serviceIds, SORT_NUMERIC);
        $map = [];
        $scopes = [];

        foreach (array_chunk($serviceIds, 500) as $serviceChunk) {
            $locations = DB::table('service_locations as sl')
                ->join('locations as l', function ($join): void {
                    $join->on('l.id', '=', 'sl.location_id')
                        ->on('l.tenant_id', '=', 'sl.tenant_id');
                })
                ->leftJoin('localities as lo', function ($join): void {
                    $join->on('lo.id', '=', 'l.locality_id')
                        ->whereColumn('lo.country_code', '=', 'l.country_code')
                        ->where('lo.is_active', '=', 1);
                })
                ->whereIn('sl.service_id', $serviceChunk)
                ->where('l.is_active', true)
                ->orderBy('sl.service_id')
                ->orderBy('l.id')
                ->get([
                    'sl.service_id',
                    'sl.tenant_id as service_location_tenant_id',
                    'l.tenant_id as location_tenant_id',
                    'l.id as location_id',
                    'l.name as location_name',
                    'l.country_code',
                    'l.is_primary',
                    'lo.id as locality_id',
                    'lo.slug as locality_slug',
                ]);

            foreach ($locations as $location) {
                $serviceId = (int) $location->service_id;
                $expected = $authority[$serviceId] ?? null;
                if ($expected === null
                    || (string) $location->service_location_tenant_id !== $expected['tenant_id']
                    || (string) $location->location_tenant_id !== $expected['tenant_id']
                    || strtoupper((string) $location->country_code) !== $expected['country_code']) {
                    continue;
                }

                $locationId = (string) $location->location_id;
                $existing = $map[$serviceId] ?? [];
                $alreadyAccepted = false;
                foreach ($existing as $accepted) {
                    if ((string) $accepted['location_id'] === $locationId) {
                        $alreadyAccepted = true;
                        break;
                    }
                }
                if ($alreadyAccepted) {
                    continue;
                }

                $map[$serviceId][] = $this->locationPayload($location, null, $expected['tenant_id']);
                $scopes[] = [
                    'tenant_id' => $expected['tenant_id'],
                    'service_id' => $serviceId,
                    'location_id' => $locationId,
                ];
            }
        }

        $statuses = $this->availability->projectableStatuses($scopes);
        foreach ($map as $serviceId => $locations) {
            foreach ($locations as $index => $location) {
                $tenantId = (string) $location['source_tenant_id'];
                $locationId = (string) $location['location_id'];
                $map[$serviceId][$index]['availability_status'] = $statuses[$tenantId][$serviceId][$locationId] ?? 'unknown';
            }
        }

        return $map;
    }

    /** @param list<array<string, mixed>> $locations @return array<string, mixed>|null */
    private function baseLocation(array $locations): ?array
    {
        if ($locations === []) {
            return null;
        }

        usort($locations, static function (array $left, array $right): int {
            $statusRank = static fn (string $status): int => match ($status) {
                'available' => 4,
                'limited' => 3,
                'unavailable' => 2,
                default => 1,
            };
            $status = $statusRank((string) $right['availability_status']) <=> $statusRank((string) $left['availability_status']);
            if ($status !== 0) {
                return $status;
            }

            $primary = ((bool) $right['is_primary'] <=> (bool) $left['is_primary']);
            if ($primary !== 0) {
                return $primary;
            }

            return strcmp((string) $left['location_id'], (string) $right['location_id']);
        });

        return $locations[0];
    }

    /** @param list<array<string, mixed>> $locations */
    private function aggregateAvailability(array $locations): string
    {
        if ($locations === []) {
            return 'unknown';
        }

        foreach (['available', 'limited', 'unavailable', 'unknown'] as $status) {
            foreach ($locations as $location) {
                if ($location['availability_status'] === $status) {
                    return $status;
                }
            }
        }

        return 'unknown';
    }

    private function locationMapKey(string $tenantId, ?string $countryCode): string
    {
        return $tenantId.'|'.strtoupper((string) $countryCode);
    }

    /** @return array<string, mixed> */
    private function locationPayload(object $location, ?string $availabilityStatus, string $tenantId): array
    {
        return [
            'source_tenant_id' => $tenantId,
            'location_id' => (string) $location->location_id,
            'location_name' => (string) $location->location_name,
            'country_code' => strtoupper((string) $location->country_code),
            'locality_id' => $location->locality_id !== null ? (int) $location->locality_id : null,
            'locality_slug' => $location->locality_slug !== null ? (string) $location->locality_slug : null,
            'availability_status' => $availabilityStatus,
            'is_primary' => (bool) $location->is_primary,
        ];
    }

    /** @param list<array<string, mixed>> $locations @return list<array<string, mixed>> */
    private function locationEvidence(array $locations, string $tenantId, mixed $freshnessAt): array
    {
        return array_map(
            static fn (array $location): array => [
                'evidence_type' => 'location',
                'evidence_key' => 'location:'.$location['location_id'],
                'source_tenant_id' => $tenantId,
                'source_table' => 'locations',
                'source_identifier' => (string) $location['location_id'],
                'freshness_at' => $freshnessAt,
                'summary' => $location['is_primary'] ? 'Primary public location' : 'Public offer location',
                'facts' => [
                    'location_name' => $location['location_name'],
                    'country_code' => $location['country_code'],
                    'locality_slug' => $location['locality_slug'],
                ],
            ],
            $locations,
        );
    }

    /** @param list<array<string, mixed>> $locations @return list<array<string, mixed>> */
    private function availabilityEvidence(
        array $locations,
        string $serviceId,
        string $tenantId,
        mixed $freshnessAt,
        ?string $timezoneName,
    ): array {
        $evidence = [];
        foreach ($locations as $location) {
            if ($location['availability_status'] === 'unknown') {
                continue;
            }

            $evidence[] = [
                'evidence_type' => 'availability_rule',
                'evidence_key' => 'service-availability:'.$serviceId.':'.$location['location_id'],
                'source_tenant_id' => $tenantId,
                'source_table' => 'availability_rules',
                'source_identifier' => (string) $serviceId,
                'freshness_at' => $freshnessAt,
                'summary' => 'Active availability rule exists',
                'facts' => [
                    'availability_status' => $location['availability_status'],
                    'location_id' => $location['location_id'],
                    'timezone_name' => $timezoneName,
                ],
            ];
        }

        return $evidence;
    }

    /** @return array{id:string, country_code:string, locale_code:string, currency_code:string, timezone_name:string}|null */
    private function validMarketContext(object $row): ?array
    {
        if ($row->tenant_market_id === null
            || (bool) ($row->market_pack_active ?? false) !== true
            || (bool) ($row->market_country_active ?? false) !== true
            || strtolower((string) ($row->market_pack_status ?? '')) !== 'published'
            || $row->market_pack_published_at === null
            || $row->market_pack_country_code === null
            || strtoupper((string) $row->market_country_code) !== strtoupper((string) $row->market_pack_country_code)) {
            return null;
        }

        try {
            $now = CarbonImmutable::now();
            if (CarbonImmutable::parse((string) $row->market_pack_published_at)->isAfter($now)
                || ($row->market_pack_effective_from !== null
                    && CarbonImmutable::parse((string) $row->market_pack_effective_from)->isAfter($now))) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $supported = static function (mixed $encoded, string $value): bool {
            $values = is_array($encoded) ? $encoded : json_decode((string) $encoded, true);

            return is_array($values) && in_array($value, array_map(static fn (mixed $item): string => (string) $item, $values), true);
        };
        $localeCode = (string) $row->market_locale_code;
        $currencyCode = strtoupper((string) $row->market_currency_code);
        $timezoneName = (string) $row->market_timezone_name;

        if ($localeCode === '' || $currencyCode === '' || $timezoneName === ''
            || ! $supported($row->market_pack_supported_locales, $localeCode)
            || ! $supported($row->market_pack_supported_currencies, $currencyCode)
            || ! $supported($row->market_pack_supported_timezones, $timezoneName)) {
            return null;
        }

        return [
            'id' => (string) $row->tenant_market_id,
            'country_code' => strtoupper((string) $row->market_country_code),
            'locale_code' => $localeCode,
            'currency_code' => $currencyCode,
            'timezone_name' => $timezoneName,
        ];
    }

    private function productAvailabilityStatus(object $row): string
    {
        $manageStock = (bool) ($row->manage_stock ?? false);
        $stockQuantity = (int) ($row->stock_quantity ?? 0);
        $allowBackorders = (bool) ($row->allow_backorders ?? false);

        if (! $manageStock) {
            return 'available';
        }

        if ($stockQuantity > 0) {
            return 'available';
        }

        return $allowBackorders ? 'backorder' : 'unavailable';
    }

    private function businessUrl(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if (Route::has('tenant.public.show')) {
            return route('tenant.public.show', ['tenant' => $slug], false);
        }

        return '/directorio/'.$slug;
    }

    /** @return list<array<string, mixed>> */
    private function agentCapabilityEvidence(string $tenantId, mixed $freshnessAt): array
    {
        if (! array_key_exists($tenantId, $this->agentCapabilityCache)) {
            $this->agentCapabilityCache[$tenantId] = $this->agentCapabilities->forTenant($tenantId);
        }

        return array_map(
            static fn (array $capability): array => [
                'evidence_type' => 'agent_capability',
                'evidence_key' => 'agent-capability:'.$tenantId.':'.$capability['agentVersionId'],
                'source_tenant_id' => $tenantId,
                'source_table' => 'ai_agent_activations',
                'source_identifier' => (string) $capability['agentVersionId'],
                'freshness_at' => $freshnessAt,
                'summary' => 'Governed active agent capability',
                'facts' => [
                    'capability' => $capability['capability'],
                    'agent_key' => $capability['agentKey'],
                    'agent_version' => $capability['agentVersion'],
                    'required_capabilities' => $capability['requiredCapabilities'],
                    'policy_key' => $capability['policyKey'],
                ],
                'metadata' => [
                    'capability' => $capability['capability'],
                    'agent_version_id' => $capability['agentVersionId'],
                    'policy_checksum' => $capability['policyChecksum'],
                    'market_context_required' => $capability['marketContextRequired'],
                ],
            ],
            $this->agentCapabilityCache[$tenantId],
        );
    }
}
