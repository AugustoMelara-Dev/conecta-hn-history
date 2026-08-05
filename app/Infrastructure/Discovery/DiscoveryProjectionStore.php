<?php

declare(strict_types=1);

namespace App\Infrastructure\Discovery;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DiscoveryProjectionStore
{
    /** @param array<string, mixed> $projection */
    public function upsertProjection(array $projection, array $evidence, array $integrityRecords): string
    {
        return DB::transaction(function () use ($projection, $evidence, $integrityRecords): string {
            $existing = DB::table('discovery_published_offers')
                ->where('projection_key', $projection['projection_key'])
                ->first();

            $projectionId = $existing?->id ?? (string) Str::uuid();
            $now = now();

            $payload = [
                'projection_key' => $projection['projection_key'],
                'source_type' => $projection['source_type'],
                'source_record_id' => $projection['source_record_id'],
                'source_tenant_id' => $projection['source_tenant_id'],
                'business_category_id' => $projection['business_category_id'] ?? null,
                'business_category_slug' => $projection['business_category_slug'] ?? null,
                'tenant_market_id' => $projection['tenant_market_id'],
                'location_id' => $projection['location_id'],
                'country_code' => $projection['country_code'],
                'locale_code' => $projection['locale_code'],
                'currency_code' => $projection['currency_code'],
                'timezone_name' => $projection['timezone_name'],
                'business_name' => $projection['business_name'],
                'business_slug' => $projection['business_slug'],
                'business_url' => $projection['business_url'],
                'offer_name' => $projection['offer_name'],
                'offer_description' => $projection['offer_description'],
                'search_document' => $projection['search_document'],
                'price_minor' => $projection['price_minor'],
                'price_display' => $projection['price_display'],
                'availability_status' => $projection['availability_status'],
                'freshness_score' => $projection['freshness_score'],
                'completeness_score' => $projection['completeness_score'],
                'verification_level' => $projection['verification_level'],
                'verification_score' => $projection['verification_score'],
                'moderation_status' => $projection['moderation_status'],
                'moderation_score' => $projection['moderation_score'],
                'evidence_snapshot' => json_encode($projection['evidence_snapshot'], JSON_THROW_ON_ERROR),
                'published_at' => $projection['published_at'],
                'source_updated_at' => $projection['source_updated_at'],
                'projected_at' => $projection['projected_at'],
                'is_published' => $projection['is_published'],
                'is_current' => $projection['is_current'],
                'is_eligible' => $projection['is_eligible'],
                'prohibited_offer' => $projection['prohibited_offer'],
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $payload['id'] = $projectionId;
                $payload['created_at'] = $now;
                DB::table('discovery_published_offers')->insert($payload);
            } else {
                DB::table('discovery_published_offers')
                    ->where('id', $projectionId)
                    ->update($payload);
            }

            DB::table('discovery_offer_evidence')
                ->where('projection_id', $projectionId)
                ->delete();

            if ($evidence !== []) {
                DB::table('discovery_offer_evidence')->insert(array_map(
                    static fn (array $record): array => [
                        'id' => (string) Str::uuid(),
                        'projection_id' => $projectionId,
                        'evidence_type' => $record['evidence_type'],
                        'evidence_key' => $record['evidence_key'],
                        'source_tenant_id' => $record['source_tenant_id'] ?? null,
                        'source_table' => $record['source_table'] ?? null,
                        'source_identifier' => $record['source_identifier'] ?? null,
                        'freshness_at' => $record['freshness_at'] ?? null,
                        'is_current' => (bool) $projection['is_current'],
                        'visibility' => (bool) $projection['is_published'] ? 'public' : 'revoked',
                        'summary' => $record['summary'] ?? null,
                        'facts' => isset($record['facts']) ? json_encode($record['facts'], JSON_THROW_ON_ERROR) : null,
                        'metadata' => isset($record['metadata']) ? json_encode($record['metadata'], JSON_THROW_ON_ERROR) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    $evidence,
                ));
            }

            $this->replaceOfferLocations(
                $projectionId,
                (string) ($projection['source_tenant_id'] ?? ''),
                is_array($projection['locations'] ?? null) ? $projection['locations'] : [],
            );

            DB::table('discovery_marketplace_integrity_records')
                ->where('projection_id', $projectionId)
                ->whereIn('record_type', ['freshness', 'completeness', 'verification', 'moderation'])
                ->delete();

            if ($integrityRecords !== []) {
                DB::table('discovery_marketplace_integrity_records')->insert(array_map(
                    static fn (array $record): array => [
                        'id' => (string) Str::uuid(),
                        'projection_id' => $projectionId,
                        'record_type' => $record['record_type'],
                        'status' => $record['status'],
                        'score' => $record['score'],
                        'reason' => $record['reason'] ?? null,
                        'actor_scope' => $record['actor_scope'] ?? 'system',
                        'actor_reference' => $record['actor_reference'] ?? 'projection',
                        'details' => isset($record['details']) ? json_encode($record['details'], JSON_THROW_ON_ERROR) : null,
                        'happened_at' => $record['happened_at'] ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    $integrityRecords,
                ));
            }

            return $projectionId;
        });
    }

    public function findProjection(string $projectionId): ?object
    {
        return DB::table('discovery_published_offers')->where('id', $projectionId)->first();
    }

    /** @param list<string> $sourceProjectionKeys */
    public function reconcileMissingProjections(array $sourceProjectionKeys): void
    {
        DB::transaction(function () use ($sourceProjectionKeys): void {
            $query = DB::table('discovery_published_offers')
                ->where(function ($query): void {
                    $query->where('is_published', true)
                        ->orWhere('is_current', true)
                        ->orWhere('is_eligible', true);
                });

            if ($sourceProjectionKeys !== []) {
                $query->whereNotIn('projection_key', $sourceProjectionKeys);
            }

            $revoked = $query->get(['id']);
            if ($revoked->isEmpty()) {
                return;
            }

            $now = now();
            $projectionIds = $revoked->pluck('id')->all();

            DB::table('discovery_published_offers')
                ->whereIn('id', $projectionIds)
                ->update([
                    'is_published' => false,
                    'is_current' => false,
                    'is_eligible' => false,
                    'moderation_status' => 'revoked',
                    'moderation_score' => 0,
                    'prohibited_offer' => false,
                    'updated_at' => $now,
                ]);

            DB::table('discovery_offer_evidence')
                ->whereIn('projection_id', $projectionIds)
                ->update([
                    'is_current' => false,
                    'visibility' => 'revoked',
                    'updated_at' => $now,
                ]);

            DB::table('discovery_offer_locations')
                ->whereIn('projection_id', $projectionIds)
                ->delete();

            DB::table('discovery_marketplace_integrity_records')->insert(
                $revoked->map(static fn (object $row): array => [
                    'id' => (string) Str::uuid(),
                    'projection_id' => $row->id,
                    'record_type' => 'projection',
                    'status' => 'revoked',
                    'score' => 0,
                    'reason' => 'missing_canonical_source',
                    'actor_scope' => 'system',
                    'actor_reference' => 'projection_reconciler',
                    'details' => null,
                    'happened_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });
    }

    /** @return list<array<string, mixed>> */
    public function evidenceForProjection(string $projectionId): array
    {
        return DB::table('discovery_offer_evidence')
            ->where('projection_id', $projectionId)
            ->orderBy('evidence_key')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function searchEligible(
        string $countryCode,
        ?string $currencyCode,
        ?int $budgetMinor,
        ?string $offerType,
        ?string $localitySlug = null,
        ?string $businessCategorySlug = null,
    ): array {
        $query = DB::table('discovery_published_offers')
            ->where('country_code', $countryCode)
            ->where('is_published', true)
            ->where('is_current', true)
            ->where('is_eligible', true)
            ->where('prohibited_offer', false);

        if ($currencyCode !== null) {
            $query->where('currency_code', $currencyCode);
        }

        if ($budgetMinor !== null) {
            $query->where('price_minor', '<=', $budgetMinor);
        }

        if ($offerType !== null) {
            $query->where('source_type', $offerType);
        }

        if ($businessCategorySlug !== null) {
            $query
                ->whereNotNull('business_category_id')
                ->where('business_category_slug', $businessCategorySlug);
        }

        if ($localitySlug !== null) {
            $query->whereExists(function ($subquery) use ($countryCode, $localitySlug): void {
                $subquery
                    ->selectRaw('1')
                    ->from('discovery_offer_locations as dol')
                    ->whereColumn('dol.projection_id', 'discovery_published_offers.id')
                    ->where('dol.country_code', $countryCode)
                    ->whereNotNull('dol.locality_id')
                    ->where('dol.locality_slug', $localitySlug)
                    ->whereNotIn('dol.availability_status', ['unknown', 'unavailable']);
            });
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $locations = DB::table('discovery_offer_locations')
            ->whereIn('projection_id', $rows->pluck('id')->all())
            ->where('country_code', $countryCode)
            ->when($localitySlug !== null, function ($q) use ($localitySlug): void {
                $q->whereNotNull('locality_id')
                    ->where('locality_slug', $localitySlug)
                    ->whereNotIn('availability_status', ['unknown', 'unavailable']);
            })
            ->orderBy('location_id')
            ->get()
            ->groupBy('projection_id');

        $hydrated = $rows->map(static function (object $row) use ($locations): array {
            $value = (array) $row;
            $value['projected_locations'] = $locations->get($row->id, collect())
                ->map(static fn (object $location): array => (array) $location)
                ->values()
                ->all();

            return $value;
        });

        if ($localitySlug !== null) {
            $hydrated = $hydrated->filter(static fn (array $row): bool => $row['projected_locations'] !== []);
        }

        return $hydrated->values()->all();
    }

    /** @param list<string> $projectionIds @return array<string, list<string>> */
    public function evidenceKeysForProjectionIds(array $projectionIds): array
    {
        if ($projectionIds === []) {
            return [];
        }

        return DB::table('discovery_offer_evidence')
            ->whereIn('projection_id', $projectionIds)
            ->orderBy('evidence_key')
            ->get()
            ->groupBy('projection_id')
            ->map(static fn (Collection $rows): array => $rows->pluck('evidence_key')->unique()->values()->all())
            ->all();
    }

    /** @param list<array<string, mixed>> $locations */
    private function replaceOfferLocations(string $projectionId, string $projectionTenantId, array $locations): void
    {
        DB::table('discovery_offer_locations')
            ->where('projection_id', $projectionId)
            ->delete();

        if ($locations === []) {
            return;
        }

        $rows = [];
        $seen = [];
        foreach ($locations as $location) {
            $locationId = (string) ($location['location_id'] ?? '');
            if ($locationId === '' || isset($seen[$locationId])) {
                continue;
            }

            $seen[$locationId] = true;
            $rows[] = [
                'id' => (string) Str::uuid(),
                'projection_id' => $projectionId,
                'source_tenant_id' => (string) ($location['source_tenant_id'] ?? $projectionTenantId),
                'location_id' => $locationId,
                'country_code' => strtoupper((string) ($location['country_code'] ?? '')),
                'locality_id' => $location['locality_id'] ?? null,
                'locality_slug' => $location['locality_slug'] ?? null,
                'availability_status' => (string) ($location['availability_status'] ?? 'unknown'),
                'is_primary' => (bool) ($location['is_primary'] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('discovery_offer_locations')->insert($rows);
        }
    }

    public function updateModeration(string $projectionId, string $status, ?string $reason): void
    {
        $projection = $this->findProjection($projectionId);
        if ($projection === null) {
            return;
        }

        $prohibited = $reason !== null && str_contains(strtolower($reason), 'prohibited');
        $isEligible = (bool) $projection->is_published
            && (bool) $projection->is_current
            && ! $prohibited
            && ! in_array((string) $projection->availability_status, ['unknown', 'unavailable'], true)
            && $status !== 'rejected';

        DB::table('discovery_published_offers')
            ->where('id', $projectionId)
            ->update([
                'moderation_status' => $status,
                'moderation_score' => $status === 'rejected' ? 0 : 100,
                'prohibited_offer' => $prohibited,
                'is_eligible' => $isEligible,
                'updated_at' => now(),
            ]);
    }

    /** @param array<string, mixed> $record */
    public function insertIntegrityRecord(array $record): string
    {
        $id = (string) Str::uuid();

        DB::table('discovery_marketplace_integrity_records')->insert([
            'id' => $id,
            'projection_id' => $record['projection_id'],
            'record_type' => $record['record_type'],
            'status' => $record['status'],
            'score' => $record['score'] ?? null,
            'reason' => $record['reason'] ?? null,
            'actor_scope' => $record['actor_scope'] ?? null,
            'actor_reference' => $record['actor_reference'] ?? null,
            'details' => isset($record['details']) ? json_encode($record['details'], JSON_THROW_ON_ERROR) : null,
            'happened_at' => $record['happened_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $lineage */
    public function recordActionPreparation(
        string $projectionId,
        string $sourceTenantId,
        string $actionType,
        string $status,
        ?string $reasonCode,
        ?string $requestedLocalDate,
        ?string $currencyCode,
        ?int $quotedTotalMinor,
        string $availabilityStatus,
        bool $confirmationRequired,
        array $payload,
        array $lineage,
    ): string {
        $id = (string) Str::uuid();

        DB::table('discovery_buyer_action_preparations')->insert([
            'id' => $id,
            'projection_id' => $projectionId,
            'source_tenant_id' => $sourceTenantId,
            'action_type' => $actionType,
            'status' => $status,
            'reason_code' => $reasonCode,
            'requested_local_date' => $requestedLocalDate,
            'currency_code' => $currencyCode,
            'quoted_total_minor' => $quotedTotalMinor,
            'availability_status' => $availabilityStatus,
            'confirmation_required' => $confirmationRequired,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'lineage' => json_encode($lineage, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
