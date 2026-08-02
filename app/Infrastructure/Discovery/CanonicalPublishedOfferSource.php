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
            ->leftJoin('tenant_markets as tm', function ($join): void {
                $join->on('tm.tenant_id', '=', 't.id')
                    ->where('tm.is_primary', '=', 1);
            })
            ->leftJoin('market_pack_versions as mpv', 'mpv.id', '=', 'tm.market_pack_version_id')
            ->leftJoin('market_packs as mp', 'mp.id', '=', 'mpv.market_pack_id')
            ->leftJoin('countries as mc', 'mc.code', '=', 'mp.country_code')
            ->leftJoin('locations as l', function ($join): void {
                $join->on('l.tenant_id', '=', 't.id')
                    ->where('l.is_primary', '=', 1)
                    ->where('l.is_active', '=', 1);
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

        return collect($query->get())
            ->map(function (object $row): array {
                $market = $this->validMarketContext($row);
                $marketContextExplicit = $market !== null;
                $countryCode = $market['country_code'] ?? null;
                $localeCode = $market['locale_code'] ?? null;
                $currencyCode = $row->currency ?: ($marketContextExplicit ? $row->market_currency_code : null);
                $timezoneName = $market['timezone_name'] ?? null;
                $marketCurrencyCompatible = $marketContextExplicit
                    && ($row->market_currency_code === null
                        || strtoupper((string) $currencyCode) === strtoupper((string) $row->market_currency_code));
                $priceMinor = $row->price_minor !== null
                    ? (int) $row->price_minor
                    : (int) round(((float) $row->price) * 100);
                $availabilityStatus = $this->productAvailabilityStatus($row);
                $agentEvidence = $this->agentCapabilityEvidence(
                    (string) $row->source_tenant_id,
                    $row->source_updated_at,
                );

                return [
                    'projection_key' => 'product:'.$row->source_record_id,
                    'source_type' => 'product',
                    'source_record_id' => (int) $row->source_record_id,
                    'source_tenant_id' => (string) $row->source_tenant_id,
                    'tenant_market_id' => $market['id'] ?? null,
                    'location_id' => $row->location_id !== null ? (string) $row->location_id : null,
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
                            'evidence_key' => 'market:'.$market['id'],
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'tenant_markets',
                            'source_identifier' => $market['id'],
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
            ->leftJoin('service_locations as sl', function ($join): void {
                $join->on('sl.service_id', '=', 's.id')
                    ->on('sl.tenant_id', '=', 's.tenant_id');
            })
            ->leftJoin('locations as l', 'l.id', '=', 'sl.location_id')
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

        return collect($query->get())
            ->map(function (object $row): array {
                $market = $this->validMarketContext($row);
                $marketContextExplicit = $market !== null;
                $countryCode = $market['country_code'] ?? null;
                $localeCode = $market['locale_code'] ?? null;
                $currencyCode = $row->currency ?: ($marketContextExplicit ? $row->market_currency_code : null);
                $timezoneName = $market['timezone_name'] ?? null;
                $marketCurrencyCompatible = $marketContextExplicit
                    && ($row->market_currency_code === null
                        || strtoupper((string) $currencyCode) === strtoupper((string) $row->market_currency_code));
                $priceMinor = $row->price_minor !== null
                    ? (int) $row->price_minor
                    : (int) round(((float) $row->price) * 100);
                $availabilityStatus = $this->availability->projectableStatus(
                    (string) $row->source_tenant_id,
                    $row->location_id !== null ? (string) $row->location_id : null,
                    (int) $row->source_record_id,
                );
                $agentEvidence = $this->agentCapabilityEvidence(
                    (string) $row->source_tenant_id,
                    $row->source_updated_at,
                );

                return [
                    'projection_key' => 'service:'.$row->source_record_id,
                    'source_type' => 'service',
                    'source_record_id' => (int) $row->source_record_id,
                    'source_tenant_id' => (string) $row->source_tenant_id,
                    'tenant_market_id' => $market['id'] ?? null,
                    'location_id' => $row->location_id !== null ? (string) $row->location_id : null,
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
                            'evidence_key' => 'market:'.$market['id'],
                            'source_tenant_id' => (string) $row->source_tenant_id,
                            'source_table' => 'tenant_markets',
                            'source_identifier' => $market['id'],
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
                        ...$agentEvidence,
                    ])),
                ];
            })
            ->all();
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
