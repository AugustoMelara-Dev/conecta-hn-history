<?php

declare(strict_types=1);

namespace App\Infrastructure\Discovery;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class CanonicalPublishedOfferSource
{
    public function __construct(
        private readonly CanonicalAvailabilityInspector $availability,
    ) {}

    /** @return list<array<string, mixed>> */
    public function currentOfferCandidates(): array
    {
        return array_merge($this->productCandidates(), $this->serviceCandidates());
    }

    /** @return list<array<string, mixed>> */
    private function productCandidates(): array
    {
        $query = DB::table('products as p')
            ->join('tenants as t', 't.id', '=', 'p.tenant_id')
            ->leftJoin('tenant_markets as tm', function ($join): void {
                $join->on('tm.tenant_id', '=', 't.id')
                    ->where('tm.is_primary', '=', 1);
            })
            ->leftJoin('locations as l', function ($join): void {
                $join->on('l.tenant_id', '=', 't.id')
                    ->where('l.is_primary', '=', 1);
            })
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
                'tm.country_code as market_country_code',
                'tm.locale_code as market_locale_code',
                'tm.currency_code as market_currency_code',
                'tm.timezone_name as market_timezone_name',
                'l.id as location_id',
                'l.name as location_name',
                'l.country_code as location_country_code',
                'l.locale_code as location_locale_code',
                'l.currency_code as location_currency_code',
                'l.timezone_name as location_timezone_name',
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

        if (Schema::hasColumn('products', 'status')) {
            $query->whereIn('p.status', ['active', 'approved', 'published']);
        }

        if (Schema::hasColumn('tenants', 'is_active')) {
            $query->where('t.is_active', true);
        }

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $query->whereIn('t.subscription_status', ['active', 'trial']);
        }

        return collect($query->get())
            ->map(function (object $row): array {
                $countryCode = $row->market_country_code ?: $row->location_country_code;
                $localeCode = $row->market_locale_code ?: $row->location_locale_code;
                $currencyCode = $row->currency ?: $row->market_currency_code ?: $row->location_currency_code;
                $timezoneName = $row->market_timezone_name ?: $row->location_timezone_name;
                $priceMinor = $row->price_minor !== null
                    ? (int) $row->price_minor
                    : (int) round(((float) $row->price) * 100);
                $availabilityStatus = $this->productAvailabilityStatus($row);

                return [
                    'projection_key' => 'product:'.$row->source_record_id,
                    'source_type' => 'product',
                    'source_record_id' => (int) $row->source_record_id,
                    'source_tenant_id' => (string) $row->source_tenant_id,
                    'tenant_market_id' => $row->tenant_market_id !== null ? (string) $row->tenant_market_id : null,
                    'location_id' => $row->location_id !== null ? (string) $row->location_id : null,
                    'country_code' => $countryCode,
                    'locale_code' => $localeCode,
                    'currency_code' => $currencyCode,
                    'timezone_name' => $timezoneName,
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
                        $row->location_name,
                    ])),
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
                            'evidence_key' => 'market:'.$row->tenant_market_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'tenant_markets',
                            'source_identifier' => $row->tenant_market_id !== null ? (string) $row->tenant_market_id : null,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Primary tenant market',
                            'facts' => ['country_code' => $countryCode, 'currency_code' => $currencyCode],
                        ] : null,
                        $row->location_id !== null ? [
                            'evidence_type' => 'location',
                            'evidence_key' => 'location:'.$row->location_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'locations',
                            'source_identifier' => (string) $row->location_id,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Primary public location',
                            'facts' => ['location_name' => $row->location_name, 'country_code' => $countryCode],
                        ] : null,
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
            ->leftJoin('service_locations as sl', function ($join): void {
                $join->on('sl.service_id', '=', 's.id')
                    ->on('sl.tenant_id', '=', 's.tenant_id');
            })
            ->leftJoin('locations as l', 'l.id', '=', 'sl.location_id')
            ->leftJoin('tenant_markets as tm', function ($join): void {
                $join->on('tm.tenant_id', '=', 't.id')
                    ->where('tm.is_primary', '=', 1);
            })
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
                'tm.country_code as market_country_code',
                'tm.locale_code as market_locale_code',
                'tm.currency_code as market_currency_code',
                'tm.timezone_name as market_timezone_name',
                'l.id as location_id',
                'l.name as location_name',
                'l.country_code as location_country_code',
                'l.locale_code as location_locale_code',
                'l.currency_code as location_currency_code',
                'l.timezone_name as location_timezone_name',
            ])
            ->when(Schema::hasColumn('tenants', 'verification_status'), fn ($q) => $q->addSelect('t.verification_status'))
            ->when(! Schema::hasColumn('tenants', 'verification_status'), fn ($q) => $q->addSelect(DB::raw("'unknown' as verification_status")));

        if (Schema::hasColumn('services', 'is_active')) {
            $query->where('s.is_active', true);
        }

        if (Schema::hasColumn('tenants', 'is_active')) {
            $query->where('t.is_active', true);
        }

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $query->whereIn('t.subscription_status', ['active', 'trial']);
        }

        return collect($query->get())
            ->map(function (object $row): array {
                $countryCode = $row->market_country_code ?: $row->location_country_code;
                $localeCode = $row->market_locale_code ?: $row->location_locale_code;
                $currencyCode = $row->currency ?: $row->market_currency_code ?: $row->location_currency_code;
                $timezoneName = $row->market_timezone_name ?: $row->location_timezone_name;
                $priceMinor = $row->price_minor !== null
                    ? (int) $row->price_minor
                    : (int) round(((float) $row->price) * 100);
                $availabilityStatus = $this->availability->projectableStatus(
                    (string) $row->source_tenant_id,
                    $row->location_id !== null ? (string) $row->location_id : null,
                    (int) $row->source_record_id,
                );

                return [
                    'projection_key' => 'service:'.$row->source_record_id,
                    'source_type' => 'service',
                    'source_record_id' => (int) $row->source_record_id,
                    'source_tenant_id' => (string) $row->source_tenant_id,
                    'tenant_market_id' => $row->tenant_market_id !== null ? (string) $row->tenant_market_id : null,
                    'location_id' => $row->location_id !== null ? (string) $row->location_id : null,
                    'country_code' => $countryCode,
                    'locale_code' => $localeCode,
                    'currency_code' => $currencyCode,
                    'timezone_name' => $timezoneName,
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
                        $row->location_name,
                    ])),
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
                            'evidence_key' => 'market:'.$row->tenant_market_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'tenant_markets',
                            'source_identifier' => $row->tenant_market_id !== null ? (string) $row->tenant_market_id : null,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Primary tenant market',
                            'facts' => ['country_code' => $countryCode, 'currency_code' => $currencyCode],
                        ] : null,
                        $row->location_id !== null ? [
                            'evidence_type' => 'location',
                            'evidence_key' => 'location:'.$row->location_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'locations',
                            'source_identifier' => (string) $row->location_id,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Primary service location',
                            'facts' => ['location_name' => $row->location_name, 'country_code' => $countryCode],
                        ] : null,
                        $availabilityStatus !== 'unknown' ? [
                            'evidence_type' => 'availability_rule',
                            'evidence_key' => 'service-availability:'.$row->source_record_id,
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'availability_rules',
                            'source_identifier' => (string) $row->source_record_id,
                            'freshness_at' => $row->source_updated_at,
                            'summary' => 'Active availability rule exists',
                            'facts' => ['availability_status' => $availabilityStatus, 'timezone_name' => $timezoneName],
                        ] : null,
                    ])),
                ];
            })
            ->all();
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
}
