<?php

declare(strict_types=1);

namespace App\Application\BusinessGraph;

use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class BusinessGraphBackfill
{
    private int $factsWritten = 0;

    private int $edgesWritten = 0;

    /** @var array<string, int> */
    private array $sourceTypeCounts = [];

    /** @var list<string> */
    private array $skippedSources = [];

    public function __construct(
        private readonly BusinessGraphRepositoryInterface $repository,
    ) {}

    public function run(?string $tenantId = null, bool $dryRun = false): BusinessGraphBackfillReport
    {
        $tenantId = $tenantId === null ? null : trim($tenantId);

        if ($tenantId === '') {
            throw new InvalidArgumentException('Business graph backfill tenantId must not be empty.');
        }

        $this->resetCounters();

        $operation = function () use ($tenantId, $dryRun): void {
            if (! Schema::hasTable('tenants')) {
                if ($tenantId !== null) {
                    throw new RuntimeException('Cannot scope a business graph backfill without the tenants table.');
                }

                $this->skip('tenants');

                return;
            }

            $query = DB::table('tenants')->select('*');

            if (Schema::hasColumn('tenants', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($tenantId !== null) {
                $query->where('id', $tenantId);
            }

            $tenants = $query->orderBy('id')->get();

            if ($tenantId !== null && $tenants->isEmpty()) {
                throw new InvalidArgumentException("Tenant [{$tenantId}] was not found for business graph backfill.");
            }

            foreach ($tenants as $tenant) {
                $this->backfillTenant((string) $tenant->id, $tenant, $dryRun);
            }
        };

        if ($dryRun) {
            $operation();
        } else {
            DB::transaction($operation);
        }

        ksort($this->sourceTypeCounts);
        sort($this->skippedSources);

        return new BusinessGraphBackfillReport(
            factsWritten: $this->factsWritten,
            edgesWritten: $this->edgesWritten,
            dryRun: $dryRun,
            tenantId: $tenantId,
            sourceTypeCounts: $this->sourceTypeCounts,
            skippedSources: array_values(array_unique($this->skippedSources)),
        );
    }

    private function resetCounters(): void
    {
        $this->factsWritten = 0;
        $this->edgesWritten = 0;
        $this->sourceTypeCounts = [];
        $this->skippedSources = [];
    }

    private function backfillTenant(string $tenantId, object $tenant, bool $dryRun): void
    {
        $this->fact(
            tenantId: $tenantId,
            subjectType: 'tenant',
            subjectId: $tenantId,
            factKey: 'business.name',
            value: ['value' => $this->rowValue($tenant, 'name')],
            sourceType: 'tenant',
            sourceId: 'tenant:'.$tenantId,
            dryRun: $dryRun,
        );

        foreach (['slug', 'plan', 'is_active'] as $field) {
            if (! property_exists($tenant, $field)) {
                continue;
            }

            $this->fact(
                tenantId: $tenantId,
                subjectType: 'tenant',
                subjectId: $tenantId,
                factKey: 'business.'.$field,
                value: ['value' => $this->rowValue($tenant, $field)],
                sourceType: 'tenant',
                sourceId: 'tenant:'.$tenantId,
                dryRun: $dryRun,
            );
        }

        $this->backfillTenantRows('locations', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'location:'.$id;
            $this->fact($tenantId, 'location', $id, 'location.profile', [
                'name' => $this->rowValue($row, 'name'),
                'countryCode' => $this->rowValue($row, 'country_code'),
                'localeCode' => $this->rowValue($row, 'locale_code'),
                'currencyCode' => $this->rowValue($row, 'currency_code'),
                'timezone' => $this->rowValue($row, 'timezone_name'),
                'isActive' => $this->rowValue($row, 'is_active'),
            ], 'location', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'operates_location', 'location', $id, 'location', $sourceId, $dryRun);
        });

        $this->backfillTenantRows('products', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'product:'.$id;
            $this->fact($tenantId, 'product', $id, 'catalog.name', ['value' => $this->rowValue($row, 'name')], 'product', $sourceId, $dryRun);
            $this->fact($tenantId, 'product', $id, 'catalog.profile', [
                'description' => $this->rowValue($row, 'description'),
                'price' => $this->rowValue($row, 'price'),
                'currency' => $this->rowValue($row, 'currency'),
                'isActive' => $this->rowValue($row, 'is_active'),
                'isVisible' => $this->rowValue($row, 'is_visible'),
            ], 'product', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'owns', 'product', $id, 'product', $sourceId, $dryRun);
        });

        $this->backfillTenantRows('services', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'service:'.$id;
            $this->fact($tenantId, 'service', $id, 'catalog.name', ['value' => $this->rowValue($row, 'name')], 'service', $sourceId, $dryRun);
            $this->fact($tenantId, 'service', $id, 'catalog.profile', [
                'description' => $this->rowValue($row, 'description'),
                'durationMinutes' => $this->rowValue($row, 'duration_minutes'),
                'price' => $this->rowValue($row, 'price'),
                'currency' => $this->rowValue($row, 'currency'),
                'isActive' => $this->rowValue($row, 'is_active'),
            ], 'service', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'owns', 'service', $id, 'service', $sourceId, $dryRun);
        });

        $this->backfillTenantRows('opening_hours', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'opening_hours:'.$id;
            $this->fact($tenantId, 'tenant', $tenantId, 'availability.opening_hours', [
                'dayOfWeek' => $this->rowValue($row, 'day_of_week'),
                'openingTime' => $this->rowValue($row, 'opening_time'),
                'closingTime' => $this->rowValue($row, 'closing_time'),
            ], 'opening_hours', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'has_availability', 'opening_hours', $id, 'opening_hours', $sourceId, $dryRun);
        });

        $this->backfillTenantRows('leads', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'lead:'.$id;
            $this->fact($tenantId, 'lead', $id, 'crm.lead', [
                'name' => $this->rowValue($row, 'name'),
                'email' => $this->rowValue($row, 'email'),
                'status' => $this->rowValue($row, 'status'),
            ], 'lead', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'has_lead', 'lead', $id, 'lead', $sourceId, $dryRun);
        });

        $this->backfillTenantRows('crm_deals', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'crm_deal:'.$id;
            $this->fact($tenantId, 'opportunity', $id, 'crm.opportunity', [
                'title' => $this->rowValue($row, 'title'),
                'status' => $this->rowValue($row, 'status'),
                'value' => $this->rowValue($row, 'value'),
                'currency' => $this->rowValue($row, 'currency'),
            ], 'crm_deal', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'has_opportunity', 'opportunity', $id, 'crm_deal', $sourceId, $dryRun);

            $leadId = $this->rowIdValue($row, 'lead_id');
            if ($leadId !== null) {
                $this->edge($tenantId, 'lead', $leadId, 'has_opportunity', 'opportunity', $id, 'crm_deal', $sourceId, $dryRun);
            }

            $customerId = $this->rowIdValue($row, 'customer_profile_id');
            if ($customerId !== null) {
                $this->edge($tenantId, 'customer', $customerId, 'has_opportunity', 'opportunity', $id, 'crm_deal', $sourceId, $dryRun);
            }
        });

        $this->backfillTenantRows('appointments', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'appointment:'.$id;
            $this->fact($tenantId, 'appointment', $id, 'operations.appointment', [
                'scheduledAt' => $this->rowValue($row, 'scheduled_at'),
                'status' => $this->rowValue($row, 'status'),
                'serviceId' => $this->rowIdValue($row, 'service_id'),
                'locationId' => $this->rowIdValue($row, 'location_id'),
            ], 'appointment', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'has_appointment', 'appointment', $id, 'appointment', $sourceId, $dryRun);

            $serviceId = $this->rowIdValue($row, 'service_id');
            if ($serviceId !== null) {
                $this->edge($tenantId, 'service', $serviceId, 'scheduled_appointment', 'appointment', $id, 'appointment', $sourceId, $dryRun);
            }

            $customerId = $this->rowIdValue($row, 'user_id');
            if ($customerId !== null) {
                $this->edge($tenantId, 'customer', $customerId, 'has_appointment', 'appointment', $id, 'appointment', $sourceId, $dryRun);
            }
        });

        $this->backfillTenantRows('quotes', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'quote:'.$id;
            $this->fact($tenantId, 'quote', $id, 'commercial.quote', [
                'status' => $this->rowValue($row, 'status'),
                'currency' => $this->rowValue($row, 'currency'),
                'totalMinor' => $this->rowValue($row, 'total_minor'),
                'issuedAt' => $this->rowValue($row, 'issued_at'),
            ], 'quote', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'has_quote', 'quote', $id, 'quote', $sourceId, $dryRun);

            $dealId = $this->rowIdValue($row, 'crm_deal_id');
            if ($dealId !== null) {
                $this->edge($tenantId, 'opportunity', $dealId, 'has_quote', 'quote', $id, 'quote', $sourceId, $dryRun);
            }
        });

        $this->backfillTenantRows('orders', $tenantId, $dryRun, function (object $row) use ($tenantId, $dryRun): void {
            $id = $this->rowId($row);
            if ($id === null) {
                return;
            }

            $sourceId = 'order:'.$id;
            $this->fact($tenantId, 'order', $id, 'commercial.order', [
                'orderNumber' => $this->rowValue($row, 'order_number'),
                'status' => $this->rowValue($row, 'status'),
                'total' => $this->rowValue($row, 'total'),
                'paymentStatus' => $this->rowValue($row, 'payment_status'),
            ], 'order', $sourceId, $dryRun);
            $this->edge($tenantId, 'tenant', $tenantId, 'has_order', 'order', $id, 'order', $sourceId, $dryRun);

            $customerId = $this->rowIdValue($row, 'user_id');
            if ($customerId !== null) {
                $this->edge($tenantId, 'customer', $customerId, 'has_order', 'order', $id, 'order', $sourceId, $dryRun);
            }
        });

        $this->backfillTenantMarkets($tenantId, $dryRun);
    }

    private function backfillTenantMarkets(string $tenantId, bool $dryRun): void
    {
        if (! Schema::hasTable('tenant_markets')) {
            $this->skip('tenant_markets');

            return;
        }

        if (! Schema::hasColumn('tenant_markets', 'tenant_id')) {
            $this->skip('tenant_markets.tenant_id');

            return;
        }

        $rows = DB::table('tenant_markets')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $id = $this->rowId($row);
            if ($id === null) {
                continue;
            }

            $sourceId = 'tenant_market:'.$id;
            $this->fact($tenantId, 'tenant', $tenantId, 'market.profile', [
                'countryCode' => $this->rowValue($row, 'country_code'),
                'localeCode' => $this->rowValue($row, 'locale_code'),
                'currencyCode' => $this->rowValue($row, 'currency_code'),
                'timezone' => $this->rowValue($row, 'timezone_name'),
                'isPrimary' => $this->rowValue($row, 'is_primary'),
            ], 'tenant_market', $sourceId, $dryRun);

            $versionId = $this->rowIdValue($row, 'market_pack_version_id');
            if ($versionId === null) {
                continue;
            }

            $packId = null;
            if (Schema::hasTable('market_pack_versions')) {
                $version = DB::table('market_pack_versions')->where('id', $versionId)->first();
                $packId = $version === null ? null : $this->rowIdValue($version, 'market_pack_id');
            } else {
                $this->skip('market_pack_versions');
            }

            if ($packId !== null && Schema::hasTable('market_packs')) {
                $pack = DB::table('market_packs')->where('id', $packId)->first();
                if ($pack !== null) {
                    $packSourceId = 'market_pack:'.$packId;
                    $this->fact($tenantId, 'market_pack', $packId, 'market.pack', [
                        'key' => $this->rowValue($pack, 'key'),
                        'name' => $this->rowValue($pack, 'name'),
                        'countryCode' => $this->rowValue($pack, 'country_code'),
                        'isActive' => $this->rowValue($pack, 'is_active'),
                    ], 'market_pack', $packSourceId, $dryRun);
                    $this->edge($tenantId, 'tenant', $tenantId, 'uses_market_pack', 'market_pack', $packId, 'tenant_market', $sourceId, $dryRun);
                }
            } elseif (! Schema::hasTable('market_packs')) {
                $this->skip('market_packs');
            }
        }
    }

    /**
     * @param callable(object): void $handler
     */
    private function backfillTenantRows(string $table, string $tenantId, bool $dryRun, callable $handler): void
    {
        if (! Schema::hasTable($table)) {
            $this->skip($table);

            return;
        }

        if (! Schema::hasColumn($table, 'tenant_id')) {
            $this->skip($table.'.tenant_id');

            return;
        }

        $query = DB::table($table)->where('tenant_id', $tenantId);
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }

        foreach ($query->get() as $row) {
            $handler($row);
        }
    }

    /** @param array<string, mixed> $value */
    private function fact(
        string $tenantId,
        string $subjectType,
        string $subjectId,
        string $factKey,
        array $value,
        string $sourceType,
        string $sourceId,
        bool $dryRun,
    ): void {
        $fact = BusinessGraphFact::fromSource(
            tenantId: $tenantId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            factKey: $factKey,
            value: $value,
            sourceType: $sourceType,
            sourceId: $sourceId,
            confidence: 1.0,
        );

        ++$this->factsWritten;
        $this->incrementSource($sourceType);

        if (! $dryRun) {
            $this->repository->upsertFact($fact);
        }
    }

    private function edge(
        string $tenantId,
        string $fromType,
        string $fromId,
        string $relation,
        string $toType,
        string $toId,
        string $sourceType,
        string $sourceId,
        bool $dryRun,
    ): void {
        $edge = BusinessGraphEdge::fromSource(
            tenantId: $tenantId,
            fromType: $fromType,
            fromId: $fromId,
            relation: $relation,
            toType: $toType,
            toId: $toId,
            sourceType: $sourceType,
            sourceId: $sourceId,
        );

        ++$this->edgesWritten;
        $this->incrementSource($sourceType);

        if (! $dryRun) {
            $this->repository->upsertEdge($edge);
        }
    }

    private function incrementSource(string $sourceType): void
    {
        $this->sourceTypeCounts[$sourceType] = ($this->sourceTypeCounts[$sourceType] ?? 0) + 1;
    }

    private function skip(string $source): void
    {
        if (! in_array($source, $this->skippedSources, true)) {
            $this->skippedSources[] = $source;
        }
    }

    private function rowId(object $row): ?string
    {
        $id = $this->rowIdValue($row, 'id');

        return $id === '' ? null : $id;
    }

    private function rowIdValue(object $row, string $field): ?string
    {
        if (! property_exists($row, $field) || $row->{$field} === null) {
            return null;
        }

        $value = $row->{$field};

        return trim((string) $value) === '' ? null : (string) $value;
    }

    private function rowValue(object $row, string $field): mixed
    {
        if (! property_exists($row, $field)) {
            return null;
        }

        $value = $row->{$field};
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_scalar($value) || $value === null || is_array($value)) {
            return $value;
        }

        return (string) $value;
    }
}
