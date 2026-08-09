<?php

declare(strict_types=1);

namespace App\Application\Crm\LegacyUuid;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class CrmLegacyUuidReconciliation
{
    public const SHAPE_LEGACY = 'legacy_uuid';
    public const SHAPE_CANONICAL = 'canonical_bigint';
    public const SHAPE_UNKNOWN = 'unknown';

    private const MIGRATION = '2026_08_21_000001_expand_crm_legacy_uuid_reconciliation.php';
    private const MAX_LIMIT = 10000;
    private const MAX_OFFSET = 1000000;

    /** @return CrmLegacyUuidReconciliationReport */
    public function run(string $phase = 'validate', int $limit = 100, int $offset = 0, bool $dryRun = false): CrmLegacyUuidReconciliationReport
    {
        return match (strtolower(trim($phase))) {
            'expand' => $this->expand(),
            'backfill' => $this->backfill($limit, $offset, $dryRun),
            'validate' => $this->validate(),
            'contract' => $this->contract(),
            default => throw new InvalidArgumentException('CRM reconciliation phase must be expand, backfill, validate, or contract.'),
        };
    }

    public function shape(): string
    {
        if (! Schema::hasTable('crm_deals')) {
            return self::SHAPE_UNKNOWN;
        }
        $columns = Schema::getColumnListing('crm_deals');
        if (! in_array('id', $columns, true)) {
            return self::SHAPE_UNKNOWN;
        }
        $idType = strtolower((string) Schema::getColumnType('crm_deals', 'id'));
        $canonicalId = in_array($idType, ['integer', 'bigint', 'int', 'int unsigned'], true);
        $legacyUuid = in_array($idType, ['string', 'varchar', 'char', 'text', 'uuid'], true);
        $legacy = $this->hasAll($columns, ['pipeline_id', 'stage_id', 'name']);
        $canonical = $this->hasAll($columns, ['crm_pipeline_id', 'crm_stage_id', 'title']);
        if ($canonical && $canonicalId && ! $legacy && ! in_array('pipeline_id', $columns, true)) {
            return self::SHAPE_CANONICAL;
        }
        if ($legacy && $legacyUuid && ! $canonical && ! in_array('crm_pipeline_id', $columns, true)) {
            return self::SHAPE_LEGACY;
        }

        return self::SHAPE_UNKNOWN;
    }

    public function expand(): CrmLegacyUuidReconciliationReport
    {
        $shape = $this->shape();
        if ($shape === self::SHAPE_CANONICAL) {
            return $this->report('expand', 'canonical_noop', $shape);
        }
        if ($shape !== self::SHAPE_LEGACY) {
            return $this->report('expand', 'unknown_shape', $shape, blockingIssues: ['unknown_or_mixed_crm_deals_shape']);
        }
        try {
            $migration = require database_path('migrations/'.self::MIGRATION);
            $migration->up();
        } catch (Throwable $e) {
            return $this->report('expand', 'blocked', $shape, blockingIssues: ['expand_failed'], details: ['error' => $e->getMessage()]);
        }
        $missing = $this->missingStructures();

        return $this->report('expand', $missing === [] ? 'legacy_ready' : 'blocked', $shape,
            blockingIssues: $missing === [] ? [] : ['expand_incomplete'], details: ['missing_structures' => $missing]);
    }

    public function backfill(int $limit = 100, int $offset = 0, bool $dryRun = false): CrmLegacyUuidReconciliationReport
    {
        $this->assertWindow($limit, $offset);
        $shape = $this->shape();
        if ($shape === self::SHAPE_CANONICAL) {
            return $this->report('backfill', 'canonical_noop', $shape, $dryRun, $limit, $offset);
        }
        if ($shape !== self::SHAPE_LEGACY) {
            return $this->report('backfill', 'unknown_shape', $shape, $dryRun, $limit, $offset,
                blockingIssues: ['unknown_or_mixed_crm_deals_shape']);
        }
        $missing = $this->missingStructures();
        if ($missing !== []) {
            return $this->report('backfill', 'blocked', $shape, $dryRun, $limit, $offset,
                blockingIssues: ['expand_required'], details: ['missing_structures' => $missing]);
        }

        $sourceCount = DB::table('crm_deals')->count();
        $rows = DB::table('crm_deals')->orderBy('tenant_id')->orderBy('id')->offset($offset)->limit($limit)->get();
        $mapped = 0;
        $quarantined = 0;
        $blocking = [];
        $codes = [];
        $child = ['gaps' => 0, 'types' => [], 'blocking' => []];
        $operation = function () use ($rows, &$mapped, &$quarantined, &$blocking, &$codes, &$child): void {
            foreach ($rows as $row) {
                $result = $this->backfillDeal($row, false);
                if ($result['state'] === 'mapped') {
                    ++$mapped;
                } elseif ($result['state'] === 'quarantined') {
                    ++$quarantined;
                    $codes[] = $result['code'] ?? 'quarantined';
                }
                if ($result['blocking'] !== null) {
                    $blocking[] = $result['blocking'];
                }
            }
            $child = $this->children(false);
        };
        if ($dryRun) {
            DB::beginTransaction();
            try {
                $operation();
            } finally {
                DB::rollBack();
            }
        } else {
            $operation();
        }
        $blocking = [...$blocking, ...$child['blocking']];
        $remaining = max(0, $sourceCount - $offset - $rows->count());
        $batches = $remaining === 0 ? 0 : (int) ceil($remaining / $limit);
        $status = $blocking !== [] ? 'blocked' : ($quarantined > 0 ? 'legacy_quarantined' : ($batches > 0 ? 'legacy_pending' : 'legacy_ready'));

        return $this->report('backfill', $status, $shape, $dryRun, $limit, $offset, $sourceCount, $rows->count(), $mapped,
            $quarantined, $batches, $child['gaps'], $blocking, $codes, $child['types'], ['next_offset' => $offset + $rows->count()]);
    }

    public function validate(): CrmLegacyUuidReconciliationReport
    {
        $shape = $this->shape();
        if ($shape === self::SHAPE_CANONICAL) {
            return $this->report('validate', 'canonical_noop', $shape);
        }
        if ($shape !== self::SHAPE_LEGACY) {
            return $this->report('validate', 'unknown_shape', $shape, blockingIssues: ['unknown_or_mixed_crm_deals_shape']);
        }
        $missing = $this->missingStructures();
        if ($missing !== []) {
            return $this->report('validate', 'blocked', $shape, blockingIssues: ['expand_required'], details: ['missing_structures' => $missing]);
        }

        $sourceCount = DB::table('crm_deals')->count();
        $maps = DB::table('crm_deal_legacy_id_map')->get();
        $blocking = [];
        $codes = [];
        $mapped = 0;
        $quarantined = 0;
        $mismatchFields = [];
        if ($maps->count() !== $sourceCount) {
            $blocking[] = 'count_mismatch';
        }
        if (DB::table('crm_deal_legacy_id_map')->whereNotNull('canonical_deal_id')->select('canonical_deal_id')->groupBy('canonical_deal_id')->havingRaw('COUNT(*) > 1')->exists()) {
            $blocking[] = 'non_bijective_mapping';
        }
        foreach (DB::table('crm_deals')->orderBy('tenant_id')->orderBy('id')->get() as $row) {
            $tenant = (string) ($row->tenant_id ?? '');
            $uuid = (string) ($row->id ?? '');
            $checksum = $this->checksum($row);
            $map = DB::table('crm_deal_legacy_id_map')->where('tenant_id', $tenant)->where('legacy_uuid', $uuid)->first();
            if ($map === null) {
                $blocking[] = 'unmapped_row';
                continue;
            }
            $payload = DB::table('crm_deal_legacy_payloads')->where('tenant_id', $tenant)->where('legacy_uuid', $uuid)->first();
            if (! hash_equals((string) $map->source_checksum, $checksum) || $payload === null || ! hash_equals((string) ($payload->source_checksum ?? ''), $checksum)) {
                $blocking[] = 'checksum_drift';
            }
            if ((string) $map->state === 'quarantined') {
                ++$quarantined;
                $codes[] = (string) ($map->error_code ?: 'quarantined');
                $blocking[] = 'quarantined_row';
                continue;
            }
            if ((string) $map->state !== 'mapped' || $map->canonical_deal_id === null) {
                $blocking[] = 'unmapped_row';
                continue;
            }
            ++$mapped;
            $source = $this->sourceValidation($row);
            $blocking = [...$blocking, ...$source['issues']];
            $shadow = DB::table('crm_deals_canonical_shadow')->where('id', $map->canonical_deal_id)->first();
            if ($shadow === null) {
                $blocking[] = 'shadow_row_missing';
            } elseif ($source['canonical'] !== null) {
                foreach (['tenant_id', 'crm_pipeline_id', 'crm_stage_id', 'owner_user_id', 'title', 'value', 'currency', 'status', 'expected_close_at', 'closed_at', 'notes'] as $field) {
                    $shadowValue = $field === 'value' ? $this->decimal($shadow->{$field} ?? null) : (string) ($shadow->{$field} ?? '');
                    $sourceValue = $field === 'value' ? $this->decimal($source['canonical'][$field] ?? null) : (string) ($source['canonical'][$field] ?? '');
                    if ($shadowValue !== $sourceValue) {
                        $mismatchFields[] = $field;
                        $blocking[] = in_array($field, ['crm_pipeline_id', 'crm_stage_id'], true) ? 'pipeline_stage_mismatch' : 'shadow_value_mismatch';
                        break;
                    }
                }
            }
        }
        $child = $this->children(true);
        $blocking = [...$blocking, ...$child['blocking']];
        $fk = $this->foreignKeyErrors();
        if ($fk !== []) {
            $blocking[] = 'foreign_key_integrity';
        }

        return $this->report('validate', $blocking === [] ? 'legacy_ready' : 'blocked', $shape,
            sourceRows: $sourceCount, processedRows: $sourceCount, mappedRows: $mapped, quarantinedRows: $quarantined,
            childGaps: $child['gaps'], blockingIssues: $blocking, quarantineCodes: $codes, childGapsByType: $child['types'],
            details: ['foreign_key_errors' => $fk, 'mismatch_fields' => array_values(array_unique($mismatchFields))]);
    }

    public function contract(): CrmLegacyUuidReconciliationReport
    {
        return $this->report('contract', 'blocked', $this->shape(), blockingIssues: ['contract_blocked'], details: [
            'reason' => 'Legacy UUID CRM cutover is not authorized by the recovery contract.',
            'required_unblock_evidence' => [
                'anonymized deployed catalog, migration ledger, counts, and anomaly sample',
                'successful full backfill and checksum validation on an anonymized copy',
                'directed MySQL and PostgreSQL proof in addition to SQLite',
                'consumer dual-read/alias strategy for persisted UUID URLs, events, audit, and business graph',
                'approved maintenance window or complete dual-write contract',
                'explicit child-table rebuild/FK/RLS plan and rollback rehearsal',
            ],
            'writes_performed' => false,
            'legacy_tables_changed' => false,
        ]);
    }

    /** @return array{state:string,code:?string,blocking:?string} */
    private function backfillDeal(object $row, bool $dryRun): array
    {
        $tenant = (string) ($row->tenant_id ?? '');
        $uuid = (string) ($row->id ?? '');
        $checksum = $this->checksum($row);
        $existing = DB::table('crm_deal_legacy_id_map')->where('tenant_id', $tenant)->where('legacy_uuid', $uuid)->first();
        if ($existing !== null) {
            return hash_equals((string) $existing->source_checksum, $checksum)
                ? ['state' => (string) $existing->state, 'code' => $existing->error_code === null ? null : (string) $existing->error_code, 'blocking' => null]
                : ['state' => (string) $existing->state, 'code' => null, 'blocking' => 'checksum_drift'];
        }
        $source = $this->sourceValidation($row);
        $payload = $this->payload($row);
        if ($source['issues'] !== []) {
            $code = $source['code'] ?? $source['issues'][0];
            if (! $dryRun) {
                DB::transaction(function () use ($tenant, $uuid, $checksum, $payload, $code): void {
                    $this->insertPayload($tenant, $uuid, $checksum, $payload);
                    DB::table('crm_deal_legacy_id_map')->insert(['tenant_id' => $tenant, 'legacy_uuid' => $uuid, 'canonical_deal_id' => null, 'state' => 'quarantined', 'source_checksum' => $checksum, 'error_code' => $code, 'created_at' => now(), 'updated_at' => now()]);
                });
            }

            return ['state' => 'quarantined', 'code' => $code, 'blocking' => null];
        }
        $canonical = $source['canonical'];
        if (! $dryRun) {
            DB::transaction(function () use ($tenant, $uuid, $checksum, $payload, $canonical): void {
                $shadowId = DB::table('crm_deals_canonical_shadow')->insertGetId(['tenant_id' => $tenant, 'legacy_uuid' => $uuid, ...$canonical]);
                $this->insertPayload($tenant, $uuid, $checksum, $payload);
                DB::table('crm_deal_legacy_id_map')->insert(['tenant_id' => $tenant, 'legacy_uuid' => $uuid, 'canonical_deal_id' => $shadowId, 'state' => 'mapped', 'source_checksum' => $checksum, 'error_code' => null, 'created_at' => now(), 'updated_at' => now()]);
            });
        }

        return ['state' => 'mapped', 'code' => null, 'blocking' => null];
    }

    /** @param array<string,mixed> $payload */
    private function insertPayload(string $tenant, string $uuid, string $checksum, array $payload): void
    {
        DB::table('crm_deal_legacy_payloads')->insert(['tenant_id' => $tenant, 'legacy_uuid' => $uuid, 'source_checksum' => $checksum, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{canonical:?array<string,mixed>,issues:list<string>,code:?string} */
    private function sourceValidation(object $row): array
    {
        $issues = [];
        $code = null;
        $tenantId = (string) ($row->tenant_id ?? '');
        $tenant = null;
        if (! Str::isUuid($tenantId) || ! Schema::hasTable('tenants') || ($tenant = DB::table('tenants')->where('id', $tenantId)->first()) === null) {
            $issues[] = 'tenant_missing';
            $code ??= 'tenant_missing';
        }
        $pipelineId = $this->positiveInteger($row->pipeline_id ?? null);
        $pipeline = null;
        if ($pipelineId === null) {
            $issues[] = 'pipeline_invalid';
            $code ??= 'pipeline_invalid';
        } elseif (! Schema::hasTable('crm_pipelines') || ($pipeline = DB::table('crm_pipelines')->where('id', $pipelineId)->first()) === null) {
            $issues[] = 'pipeline_missing';
            $code ??= 'pipeline_missing';
        } elseif ((string) ($pipeline->tenant_id ?? '') !== $tenantId) {
            $issues[] = 'pipeline_tenant_mismatch';
            $code ??= 'pipeline_tenant_mismatch';
        }
        $stageId = $this->positiveInteger($row->stage_id ?? null);
        $stage = null;
        if ($stageId === null) {
            $issues[] = 'stage_invalid';
            $code ??= 'stage_invalid';
        } elseif (! Schema::hasTable('crm_stages') || ($stage = DB::table('crm_stages')->where('id', $stageId)->first()) === null) {
            $issues[] = 'stage_missing';
            $code ??= 'stage_missing';
        } else {
            if ((string) ($stage->tenant_id ?? '') !== $tenantId) {
                $issues[] = 'stage_tenant_mismatch';
                $code ??= 'stage_tenant_mismatch';
            }
            if ($pipelineId !== null && (int) ($stage->crm_pipeline_id ?? $stage->pipeline_id ?? 0) !== $pipelineId) {
                $issues[] = 'stage_pipeline_mismatch';
                $code ??= 'stage_pipeline_mismatch';
            }
        }
        $owner = $row->owner_id ?? null;
        if ($owner !== null && $owner !== '') {
            if (! Str::isUuid((string) $owner) || ! Schema::hasTable('users') || ! DB::table('users')->where('id', (string) $owner)->exists()) {
                $issues[] = 'owner_invalid';
                $code ??= 'owner_invalid';
            } elseif (! $this->activeOwner($tenant, $tenantId, (string) $owner)) {
                $issues[] = 'owner_tenant_mismatch';
                $code ??= 'owner_tenant_mismatch';
            }
        }
        $title = (string) ($row->name ?? '');
        if (trim($title) === '') {
            $issues[] = 'blank_title';
            $code ??= 'blank_title';
        }
        $value = $this->decimal($row->value ?? null);
        if ($value === null) {
            $issues[] = 'value_overflow';
            $code ??= 'value_overflow';
        }
        $currency = strtoupper(trim((string) ($row->currency ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $issues[] = 'currency_invalid';
            $code ??= 'currency_invalid';
        }
        $status = (string) ($row->status ?? '');
        if (! in_array($status, ['open', 'won', 'lost'], true)) {
            $issues[] = 'status_invalid';
            $code ??= 'status_invalid';
        }
        $expected = $row->expected_close_date ?? null;
        if ($expected !== null && $expected !== '' && ! $this->validDate((string) $expected)) {
            $issues[] = 'expected_close_date_invalid';
            $code ??= 'expected_close_date_invalid';
        }
        $won = $row->won_at ?? null;
        $lost = $row->lost_at ?? null;
        $hasWon = $won !== null && $won !== '';
        $hasLost = $lost !== null && $lost !== '';
        if (($hasWon && ! $this->validTimestamp((string) $won)) || ($hasLost && ! $this->validTimestamp((string) $lost))) {
            $issues[] = 'close_timestamp_invalid';
            $code ??= 'close_timestamp_invalid';
        }
        $closeOk = match ($status) {
            'open' => ! $hasWon && ! $hasLost,
            'won' => $hasWon && ! $hasLost,
            'lost' => $hasLost && ! $hasWon,
            default => false,
        };
        if (! $closeOk) {
            $issues[] = 'ambiguous_close_state';
            $code ??= 'ambiguous_close_state';
        }
        if ($issues !== []) {
            return ['canonical' => null, 'issues' => array_values(array_unique($issues)), 'code' => $code];
        }

        return ['canonical' => [
            'tenant_id' => $tenantId,
            'crm_pipeline_id' => $pipelineId,
            'crm_stage_id' => $stageId,
            'customer_profile_id' => null,
            'lead_id' => null,
            'owner_user_id' => ($owner === null || $owner === '') ? null : (string) $owner,
            'location_id' => null,
            'title' => $title,
            'value' => $value,
            'currency' => $currency,
            'status' => $status,
            'source' => 'legacy_pr391',
            'expected_close_at' => $expected ?: null,
            'closed_at' => $status === 'won' ? $won : ($status === 'lost' ? $lost : null),
            'lost_reason' => $row->lost_reason ?? null,
            'notes' => $row->description ?? null,
            'write_idempotency_key' => $row->write_idempotency_key ?? null,
            'write_payload_hash' => $row->write_payload_hash ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ], 'issues' => [], 'code' => null];
    }

    private function activeOwner(?object $tenant, string $tenantId, string $ownerId): bool
    {
        if ($tenant !== null && property_exists($tenant, 'user_id') && (string) $tenant->user_id === $ownerId) {
            return true;
        }
        if (! Schema::hasTable('tenant_memberships')) {
            return false;
        }
        $query = DB::table('tenant_memberships')->where('tenant_id', $tenantId)->where('user_id', $ownerId)->where('status', 'active');
        if (Schema::hasColumn('tenant_memberships', 'accepted_at')) {
            $query->whereNotNull('accepted_at');
        }
        foreach (['suspended_at', 'revoked_at'] as $column) {
            if (Schema::hasColumn('tenant_memberships', $column)) {
                $query->whereNull($column);
            }
        }

        return $query->exists();
    }

    /** @return array{gaps:int,types:array<string,int>,blocking:list<string>} */
    private function children(bool $dryRun): array
    {
        $gaps = 0;
        $types = [];
        if (Schema::hasTable('crm_activities') && Schema::hasColumn('crm_activities', 'canonical_deal_id')) {
            foreach (DB::table('crm_activities')->whereNull('canonical_deal_id')->get() as $activity) {
                if ($activity->deal_id === null || $activity->deal_id === '') {
                    continue;
                }
                $map = DB::table('crm_deal_legacy_id_map')->where('tenant_id', (string) ($activity->tenant_id ?? ''))->where('legacy_uuid', (string) ($activity->deal_id ?? ''))->where('state', 'mapped')->first();
                if ($map === null) {
                    if ($dryRun && $this->prospectiveMapping((string) ($activity->tenant_id ?? ''), (string) ($activity->deal_id ?? ''))) {
                        continue;
                    }
                    ++$gaps;
                    $types['activities'] = ($types['activities'] ?? 0) + 1;
                } elseif (! $dryRun) {
                    DB::table('crm_activities')->where('id', $activity->id)->update(['canonical_deal_id' => $map->canonical_deal_id]);
                }
            }
        }
        if (Schema::hasTable('deal_tag') && Schema::hasColumn('deal_tag', 'canonical_deal_id')) {
            foreach (DB::table('deal_tag')->whereNull('canonical_deal_id')->get() as $tag) {
                if ($tag->deal_id === null || $tag->deal_id === '') {
                    continue;
                }
                $tenant = Schema::hasTable('tags') ? DB::table('tags')->where('id', $tag->tag_id)->value('tenant_id') : null;
                $map = $tenant === null ? null : DB::table('crm_deal_legacy_id_map')->where('tenant_id', (string) $tenant)->where('legacy_uuid', (string) ($tag->deal_id ?? ''))->where('state', 'mapped')->first();
                if ($map === null) {
                    if ($dryRun && $tenant !== null && $this->prospectiveMapping((string) $tenant, (string) ($tag->deal_id ?? ''))) {
                        continue;
                    }
                    ++$gaps;
                    $types['deal_tags'] = ($types['deal_tags'] ?? 0) + 1;
                } elseif (! $dryRun) {
                    DB::table('deal_tag')->where('deal_id', $tag->deal_id)->where('tag_id', $tag->tag_id)->update(['canonical_deal_id' => $map->canonical_deal_id]);
                }
            }
        }

        return ['gaps' => $gaps, 'types' => $types, 'blocking' => $gaps > 0 ? ['orphan_child_reference'] : []];
    }

    private function prospectiveMapping(string $tenantId, string $legacyUuid): bool
    {
        $row = DB::table('crm_deals')->where('tenant_id', $tenantId)->where('id', $legacyUuid)->first();

        return $row !== null && $this->sourceValidation($row)['issues'] === [];
    }

    /** @return list<string> */
    private function foreignKeyErrors(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }
        try {
            return array_map(static fn (object $row): string => implode(':', array_map('strval', get_object_vars($row))), DB::select('PRAGMA foreign_key_check'));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function missingStructures(): array
    {
        $required = ['crm_deals_canonical_shadow', 'crm_deal_legacy_id_map', 'crm_deal_legacy_payloads'];
        foreach (['crm_activities', 'deal_tag'] as $child) {
            if (Schema::hasTable($child)) {
                $required[] = $child.'.canonical_deal_id';
            }
        }

        return array_values(array_filter($required, static function (string $item): bool {
            if (str_contains($item, '.')) {
                [$table, $column] = explode('.', $item, 2);

                return ! Schema::hasColumn($table, $column);
            }

            return ! Schema::hasTable($item);
        }));
    }

    private function assertWindow(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('CRM reconciliation limit must be between 1 and '.self::MAX_LIMIT.'.');
        }
        if ($offset < 0 || $offset > self::MAX_OFFSET || $offset > self::MAX_OFFSET - $limit) {
            throw new InvalidArgumentException('CRM reconciliation offset is outside the bounded source window.');
        }
    }

    /** @param list<string> $columns @param list<string> $required */
    private function hasAll(array $columns, array $required): bool
    {
        foreach ($required as $column) {
            if (! in_array($column, $columns, true)) {
                return false;
            }
        }

        return true;
    }

    private function positiveInteger(mixed $value): ?int
    {
        $text = is_int($value) ? (string) $value : (is_string($value) ? trim($value) : '');
        if ($text === '' || preg_match('/^[1-9]\d*$/', $text) !== 1) {
            return null;
        }
        $integer = filter_var($text, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : (int) $integer;
    }

    private function decimal(mixed $value): ?string
    {
        $text = is_int($value) || is_float($value) || is_string($value) ? trim((string) $value) : '';
        if ($text === '' || preg_match('/^\d+(?:\.\d{1,2})?$/', $text) !== 1) {
            return null;
        }
        [$integer, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 10) {
            return null;
        }

        return $integer.'.'.str_pad($fraction, 2, '0');
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function validTimestamp(string $value): bool
    {
        foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:s.uP', 'Y-m-d\\TH:i:sP'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function payload(object $row): array
    {
        /** @var array<string,mixed> $payload */
        $payload = get_object_vars($row);
        ksort($payload);

        return $payload;
    }

    private function checksum(object $row): string
    {
        return hash('sha256', json_encode($this->payload($row), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param list<string> $blockingIssues
     * @param list<string> $quarantineCodes
     * @param array<string,int> $childGapsByType
     * @param array<string,mixed> $details
     */
    private function report(
        string $phase,
        string $status,
        string $shape,
        bool $dryRun = false,
        int $limit = 0,
        int $offset = 0,
        int $sourceRows = 0,
        int $processedRows = 0,
        int $mappedRows = 0,
        int $quarantinedRows = 0,
        int $remainingBatches = 0,
        int $childGaps = 0,
        array $blockingIssues = [],
        array $quarantineCodes = [],
        array $childGapsByType = [],
        array $details = [],
    ): CrmLegacyUuidReconciliationReport {
        sort($blockingIssues);
        sort($quarantineCodes);
        ksort($childGapsByType);

        return new CrmLegacyUuidReconciliationReport(
            phase: $phase,
            status: $status,
            shape: $shape,
            dryRun: $dryRun,
            limit: $limit,
            offset: $offset,
            sourceRows: $sourceRows,
            processedRows: $processedRows,
            mappedRows: $mappedRows,
            quarantinedRows: $quarantinedRows,
            remainingBatches: $remainingBatches,
            childGaps: $childGaps,
            blockingIssues: array_values(array_unique($blockingIssues)),
            quarantineCodes: array_values(array_unique($quarantineCodes)),
            childGapsByType: $childGapsByType,
            details: $details,
        );
    }
}
