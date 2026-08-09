<?php

declare(strict_types=1);

namespace App\Application\Crm\TenantIntegrity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/** Read-only CRM tenant-integrity preflight plus additive Batch A expansion. */
final class CrmTenantIntegrity
{
    public const SHAPE_CANONICAL = 'canonical_bigint';
    public const SHAPE_LEGACY = 'legacy_uuid';
    public const SHAPE_UNKNOWN = 'mixed_or_unknown';

    public const STATUS_READY = 'READY';
    public const STATUS_BLOCKED_ANOMALIES = 'BLOCKED_ANOMALIES';
    public const STATUS_BLOCKED_MISSING_REQUIRED_TABLE = 'BLOCKED_MISSING_REQUIRED_TABLE';
    public const STATUS_BLOCKED_LEGACY = 'BLOCKED_LEGACY_UUID_CONTRACT';
    public const STATUS_BLOCKED_UNKNOWN = 'BLOCKED_UNKNOWN_SHAPE';
    public const STATUS_EXPANDED = 'EXPANDED';
    public const STATUS_EXPANDED_NOOP = 'EXPANDED_NOOP';
    public const STATUS_BLOCKED_FINGERPRINT = 'BLOCKED_FINGERPRINT_MISMATCH';
    public const STATUS_BLOCKED_FINGERPRINT_REQUIRED = 'BLOCKED_FINGERPRINT_REQUIRED';
    public const STATUS_BLOCKED_REQUIRED_SCHEMA = 'BLOCKED_REQUIRED_SCHEMA';
    public const STATUS_BLOCKED_METADATA_FAILURE = 'BLOCKED_METADATA_FAILURE';
    public const STATUS_BLOCKED_PARTIAL_WRITE = 'BLOCKED_PARTIAL_WRITE';
    public const STATUS_BLOCKED_NOT_IMPLEMENTED = 'BLOCKED_NOT_IMPLEMENTED';
    public const STATUS_BLOCKED_EXTERNAL = 'BLOCKED_EXTERNAL';

    private const MIGRATION = '2026_08_22_000001_expand_crm_tenant_integrity.php';

    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'tenants', 'crm_pipelines', 'crm_stages', 'crm_deals', 'crm_companies',
        'crm_contacts', 'crm_activities', 'tags', 'contact_tag', 'deal_tag',
        'leads', 'locations', 'customer_profiles', 'customer_notes',
        'customer_tasks', 'quotes', 'tenant_memberships',
    ];

    /** @var list<string> */
    private const OPTIONAL_TABLES = ['crm_custom_fields'];

    /** @var array<string, list<string>> */
    private const CANDIDATE_KEYS = [
        'crm_pipelines_tenant_id_unique' => ['crm_pipelines', 'tenant_id', 'id'],
        'crm_stages_tenant_pipeline_id_unique' => ['crm_stages', 'tenant_id', 'crm_pipeline_id', 'id'],
        'crm_deals_tenant_id_unique' => ['crm_deals', 'tenant_id', 'id'],
        'crm_companies_tenant_id_unique' => ['crm_companies', 'tenant_id', 'id'],
        'crm_contacts_tenant_id_unique' => ['crm_contacts', 'tenant_id', 'id'],
        'tags_tenant_id_unique' => ['tags', 'tenant_id', 'id'],
        'leads_tenant_id_unique' => ['leads', 'tenant_id', 'id'],
        'locations_tenant_id_unique' => ['locations', 'tenant_id', 'id'],
        'customer_profiles_tenant_id_unique' => ['customer_profiles', 'tenant_id', 'id'],
        'customer_notes_tenant_id_unique' => ['customer_notes', 'tenant_id', 'id'],
        'customer_tasks_tenant_id_unique' => ['customer_tasks', 'tenant_id', 'id'],
        'quotes_tenant_id_unique' => ['quotes', 'tenant_id', 'id'],
        'tenant_memberships_tenant_user_id_unique' => ['tenant_memberships', 'tenant_id', 'user_id', 'id'],
    ];

    /** @var array<string, list<string>> */
    private const REQUIRED_COLUMNS = [
        'tenants' => ['id'],
        'crm_pipelines' => ['id', 'tenant_id'],
        'crm_stages' => ['id', 'tenant_id', 'crm_pipeline_id'],
        'crm_deals' => ['id', 'tenant_id', 'crm_pipeline_id', 'crm_stage_id', 'title', 'lead_id', 'location_id', 'customer_profile_id', 'owner_user_id'],
        'crm_companies' => ['id', 'tenant_id'],
        'crm_contacts' => ['id', 'tenant_id', 'company_id', 'owner_id'],
        'crm_activities' => ['id', 'tenant_id', 'contact_id', 'deal_id', 'company_id', 'owner_id'],
        'tags' => ['id', 'tenant_id'],
        'contact_tag' => ['contact_id', 'tag_id'],
        'deal_tag' => ['deal_id', 'tag_id'],
        'leads' => ['id', 'tenant_id'],
        'locations' => ['id', 'tenant_id'],
        'customer_profiles' => ['id', 'tenant_id'],
        'customer_notes' => ['id', 'tenant_id', 'crm_deal_id'],
        'customer_tasks' => ['id', 'tenant_id', 'crm_deal_id'],
        'quotes' => ['id', 'tenant_id', 'crm_deal_id'],
        'tenant_memberships' => ['id', 'tenant_id', 'user_id', 'accepted_at'],
    ];

    /** @var array<string, list<string>> */
    private const DUPLICATE_GROUPS = [
        'crm_pipelines' => ['tenant_id', 'key'],
        'crm_stages' => ['tenant_id', 'crm_pipeline_id', 'key'],
        'crm_deals' => ['tenant_id', 'id'],
        'crm_companies' => ['tenant_id', 'id'],
        'crm_contacts' => ['tenant_id', 'id'],
        'tags' => ['tenant_id', 'slug'],
        'leads' => ['tenant_id', 'id'],
        'locations' => ['tenant_id', 'id'],
        'customer_profiles' => ['tenant_id', 'id'],
        'customer_notes' => ['tenant_id', 'id'],
        'customer_tasks' => ['tenant_id', 'id'],
        'quotes' => ['tenant_id', 'id'],
        'tenant_memberships' => ['tenant_id', 'user_id'],
    ];

    public function run(string $phase = 'preflight', ?string $fingerprint = null): CrmTenantIntegrityReport
    {
        return match (strtolower(trim($phase))) {
            'preflight', 'validate' => $this->preflight(strtolower(trim($phase))),
            'expand' => $this->expand($fingerprint),
            'backfill' => $this->blockedPhase('backfill', self::STATUS_BLOCKED_NOT_IMPLEMENTED, ['TASK_2_BACKFILL_NOT_AUTHORIZED']),
            'contract' => $this->blockedPhase('contract', self::STATUS_BLOCKED_EXTERNAL, ['TASK_3_CONTRACT_NOT_AUTHORIZED']),
            default => throw new InvalidArgumentException('CRM tenant-integrity phase must be preflight, expand, backfill, validate, or contract.'),
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
        $idType = $this->normalizedType('crm_deals', 'id');
        $isBigint = $this->isCanonicalBigintId($idType);
        $isUuid = in_array($idType, ['string', 'varchar', 'char', 'text', 'uuid'], true);
        $legacyVocabulary = $this->hasAny($columns, [
            'pipeline_id', 'stage_id', 'name', 'contact_id', 'company_id', 'owner_id',
            'probability', 'expected_close_date', 'won_at', 'lost_at', 'description', 'custom_fields',
        ]);
        $canonicalVocabulary = $this->hasAll($columns, ['crm_pipeline_id', 'crm_stage_id', 'title']);
        $canonicalResidue = $this->hasAny($columns, ['crm_pipeline_id', 'crm_stage_id', 'title']);
        if ($canonicalVocabulary && $isBigint && ! $legacyVocabulary) {
            return self::SHAPE_CANONICAL;
        }
        if ($legacyVocabulary && $isUuid && ! $canonicalResidue) {
            return self::SHAPE_LEGACY;
        }

        return self::SHAPE_UNKNOWN;
    }

    public function preflight(string $phase = 'preflight'): CrmTenantIntegrityReport
    {
        $shape = self::SHAPE_UNKNOWN;
        $schemaFingerprint = '';
        try {
            $shape = $this->shape();
            $schemaFingerprint = $this->schemaFingerprint();
            if (! Schema::hasTable('crm_deals')) {
                return $this->report($phase, self::STATUS_BLOCKED_MISSING_REQUIRED_TABLE, $shape, $schemaFingerprint,
                    missingRequiredTables: ['crm_deals'], blockingIssues: ['missing_required_table:crm_deals']);
            }
            if ($shape === self::SHAPE_LEGACY) {
                return $this->report($phase, self::STATUS_BLOCKED_LEGACY, $shape, $schemaFingerprint, blockingIssues: ['BLOCKED_LEGACY_UUID_CONTRACT']);
            }
            if ($shape === self::SHAPE_UNKNOWN) {
                return $this->report($phase, self::STATUS_BLOCKED_UNKNOWN, $shape, $schemaFingerprint, blockingIssues: ['BLOCKED_UNKNOWN_SHAPE']);
            }

            $missing = array_values(array_filter(self::REQUIRED_TABLES, static fn (string $table): bool => ! Schema::hasTable($table)));
            if ($missing !== []) {
                return $this->report($phase, self::STATUS_BLOCKED_MISSING_REQUIRED_TABLE, $shape, $schemaFingerprint,
                    missingRequiredTables: $missing,
                    blockingIssues: array_map(static fn (string $table): string => 'missing_required_table:'.$table, $missing));
            }

            $missingColumns = $this->missingRequiredColumns();
            if ($missingColumns !== []) {
                return $this->report($phase, self::STATUS_BLOCKED_REQUIRED_SCHEMA, $shape, $schemaFingerprint,
                    missingRequiredColumns: $missingColumns,
                    blockingIssues: array_map(static fn (string $column): string => 'missing_required_column:'.$column, $missingColumns));
            }

            $inventory = $this->inventory();
            $anomalies = $this->anomalies();
            $blocking = array_keys(array_filter($anomalies, static fn (int $count): bool => $count > 0));
            sort($blocking);

            return $this->report($phase, $blocking === [] ? self::STATUS_READY : self::STATUS_BLOCKED_ANOMALIES,
                $shape, $schemaFingerprint, dataFingerprint: $this->dataFingerprint($inventory),
                anomalies: $anomalies, inventory: $inventory, blockingIssues: $blocking,
                details: [
                    'required_tables' => self::REQUIRED_TABLES,
                    'optional_tables' => self::OPTIONAL_TABLES,
                    'candidate_keys' => $this->candidateKeyDetails(),
                    'anomaly_hashes' => $this->anomalyHashes($anomalies),
                    'query_bounds' => ['max_anomaly_hashes' => 20, 'row_boundary' => 'first_and_last_ids_only'],
                    'writes_performed' => false,
                ]);
        } catch (Throwable $exception) {
            return $this->report($phase, self::STATUS_BLOCKED_METADATA_FAILURE, $shape, $schemaFingerprint,
                blockingIssues: ['metadata_introspection_failed'],
                details: ['error_code' => get_class($exception), 'writes_performed' => false]);
        }
    }

    public function expand(?string $expectedFingerprint = null): CrmTenantIntegrityReport
    {
        $preflight = $this->preflight('expand');
        if ($preflight->status !== self::STATUS_READY) {
            return $preflight;
        }
        if ($expectedFingerprint === null || trim($expectedFingerprint) === '') {
            return $this->copyReport($preflight, self::STATUS_BLOCKED_FINGERPRINT_REQUIRED,
                blockingIssues: ['schema_fingerprint_required'],
                details: [...$preflight->details, 'writes_performed' => false]);
        }
        if (! hash_equals($preflight->schemaFingerprint, trim($expectedFingerprint))) {
            return $this->copyReport($preflight, self::STATUS_BLOCKED_FINGERPRINT,
                blockingIssues: ['schema_fingerprint_mismatch'],
                details: ['expected_fingerprint_hash' => hash('sha256', trim($expectedFingerprint))]);
        }

        $before = $preflight->schemaFingerprint;
        try {
            $migration = require database_path('migrations/'.self::MIGRATION);
            DB::transaction(static function () use ($migration, $before): void {
                $migration->up($before);
            });
        } catch (Throwable $exception) {
            try {
                $afterFailure = $this->preflight('expand');
                $afterFingerprint = $afterFailure->schemaFingerprint;
            } catch (Throwable) {
                return $this->copyReport($preflight, self::STATUS_BLOCKED_PARTIAL_WRITE,
                    writesPerformed: true,
                    blockingIssues: ['expand_failed', 'schema_fingerprint_unavailable_after_failure'],
                    details: [...$preflight->details, 'error_code' => get_class($exception), 'writes_state' => 'unknown']);
            }
            if ($afterFingerprint !== '' && hash_equals($before, $afterFingerprint)) {
                return $this->copyReport($preflight, self::STATUS_BLOCKED_ANOMALIES,
                    blockingIssues: ['expand_failed'],
                    details: [...$preflight->details, 'error_code' => get_class($exception), 'writes_performed' => false]);
            }
            return $this->copyReport($afterFailure, self::STATUS_BLOCKED_PARTIAL_WRITE,
                writesPerformed: true,
                blockingIssues: ['expand_failed', 'schema_fingerprint_changed_after_failure'],
                details: [
                    ...$afterFailure->details,
                    'error_code' => get_class($exception),
                    'before_schema_fingerprint' => $before,
                    'after_schema_fingerprint' => $afterFingerprint,
                    'writes_state' => 'changed',
                ]);
        }
        $after = $this->preflight('expand');
        $changed = ! hash_equals($before, $after->schemaFingerprint);

        return $this->copyReport($after, $changed ? self::STATUS_EXPANDED : self::STATUS_EXPANDED_NOOP,
            writesPerformed: $changed,
            details: [
                ...$after->details,
                'before_schema_fingerprint' => $before,
                'after_schema_fingerprint' => $after->schemaFingerprint,
            ]);
    }

    /** @param list<string> $blockingIssues */
    private function blockedPhase(string $phase, string $status, array $blockingIssues): CrmTenantIntegrityReport
    {
        $preflight = $this->preflight($phase);

        return $this->copyReport($preflight, $status, blockingIssues: $blockingIssues,
            details: [...$preflight->details, 'writes_performed' => false]);
    }

    /** @param list<string> $missingRequiredTables @param list<string> $missingRequiredColumns @param array<string,int> $anomalies @param array<string,array<string,mixed>> $inventory @param list<string> $blockingIssues @param array<string,mixed> $details */
    private function report(
        string $phase,
        string $status,
        string $shape,
        string $schemaFingerprint,
        string $dataFingerprint = '',
        bool $writesPerformed = false,
        array $missingRequiredTables = [],
        array $missingRequiredColumns = [],
        array $anomalies = [],
        array $inventory = [],
        array $blockingIssues = [],
        array $details = [],
    ): CrmTenantIntegrityReport {
        return new CrmTenantIntegrityReport(
            phase: $phase,
            status: $status,
            shape: $shape,
            schemaFingerprint: $schemaFingerprint,
            dataFingerprint: $dataFingerprint,
            writesPerformed: $writesPerformed,
            missingRequiredTables: $missingRequiredTables,
            missingRequiredColumns: $missingRequiredColumns,
            anomalies: $anomalies,
            inventory: $inventory,
            blockingIssues: $blockingIssues,
            details: $details,
        );
    }

    /** @param list<string> $blockingIssues @param array<string,mixed> $details */
    private function copyReport(CrmTenantIntegrityReport $report, string $status, bool $writesPerformed = false, array $blockingIssues = [], array $details = []): CrmTenantIntegrityReport
    {
        return new CrmTenantIntegrityReport(
            phase: $report->phase,
            status: $status,
            shape: $report->shape,
            schemaFingerprint: $report->schemaFingerprint,
            dataFingerprint: $report->dataFingerprint,
            writesPerformed: $writesPerformed,
            missingRequiredTables: $report->missingRequiredTables,
            missingRequiredColumns: $report->missingRequiredColumns,
            anomalies: $report->anomalies,
            inventory: $report->inventory,
            blockingIssues: $blockingIssues === [] ? $report->blockingIssues : $blockingIssues,
            details: $details === [] ? $report->details : $details,
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function inventory(): array
    {
        $tables = [...self::REQUIRED_TABLES, ...array_values(array_filter(self::OPTIONAL_TABLES, static fn (string $table): bool => Schema::hasTable($table)))];
        $inventory = [];
        foreach ($tables as $table) {
            $inventory[$table] = ['row_count' => $this->boundedCount($table), 'row_boundary' => $this->rowBoundary($table)];
        }
        ksort($inventory);

        return $inventory;
    }

    /** @return array<string,int> */
    private function anomalies(): array
    {
        $counts = [];
        $this->addAnomaly($counts, 'cross_tenant_stage_pipeline', $this->edgeMismatch('crm_stages', 'crm_pipeline_id', 'crm_pipelines'));
        $this->addAnomaly($counts, 'orphan_stage_pipeline', $this->edgeOrphans('crm_stages', 'crm_pipeline_id', 'crm_pipelines'));
        $this->addAnomaly($counts, 'cross_tenant_deal_pipeline', $this->edgeMismatch('crm_deals', 'crm_pipeline_id', 'crm_pipelines'));
        $this->addAnomaly($counts, 'orphan_deal_pipeline', $this->edgeOrphans('crm_deals', 'crm_pipeline_id', 'crm_pipelines'));
        $this->addAnomaly($counts, 'cross_tenant_deal_stage', $this->edgeMismatch('crm_deals', 'crm_stage_id', 'crm_stages'));
        $this->addAnomaly($counts, 'orphan_deal_stage', $this->edgeOrphans('crm_deals', 'crm_stage_id', 'crm_stages'));
        $this->addAnomaly($counts, 'deal_stage_pipeline_mismatch', $this->dealStagePipelineMismatch());
        $this->addAnomaly($counts, 'orphan_deal_lead', $this->edgeOrphans('crm_deals', 'lead_id', 'leads'));
        $this->addAnomaly($counts, 'cross_tenant_deal_lead', $this->edgeMismatch('crm_deals', 'lead_id', 'leads'));
        $this->addAnomaly($counts, 'orphan_deal_location', $this->edgeOrphans('crm_deals', 'location_id', 'locations'));
        $this->addAnomaly($counts, 'cross_tenant_deal_location', $this->edgeMismatch('crm_deals', 'location_id', 'locations'));
        $this->addAnomaly($counts, 'orphan_deal_customer_profile', $this->edgeOrphans('crm_deals', 'customer_profile_id', 'customer_profiles'));
        $this->addAnomaly($counts, 'cross_tenant_deal_customer_profile', $this->edgeMismatch('crm_deals', 'customer_profile_id', 'customer_profiles'));

        foreach ([['customer_notes', 'customer_note_deal'], ['customer_tasks', 'customer_task_deal'], ['quotes', 'quote_deal']] as [$table, $label]) {
            $this->addAnomaly($counts, 'orphan_'.$label, $this->edgeOrphans($table, 'crm_deal_id', 'crm_deals'));
            $this->addAnomaly($counts, 'cross_tenant_'.$label, $this->edgeMismatch($table, 'crm_deal_id', 'crm_deals'));
        }
        $this->addAnomaly($counts, 'orphan_contact_company', $this->edgeOrphans('crm_contacts', 'company_id', 'crm_companies'));
        $this->addAnomaly($counts, 'cross_tenant_contact_company', $this->edgeMismatch('crm_contacts', 'company_id', 'crm_companies'));
        foreach ([['contact_id', 'crm_contacts', 'activity_contact'], ['deal_id', 'crm_deals', 'activity_deal'], ['company_id', 'crm_companies', 'activity_company']] as [$column, $parent, $label]) {
            $this->addAnomaly($counts, 'orphan_'.$label, $this->edgeOrphans('crm_activities', $column, $parent));
            $this->addAnomaly($counts, 'cross_tenant_'.$label, $this->edgeMismatch('crm_activities', $column, $parent));
        }
        foreach ([['contact_tag', 'contact_id', 'crm_contacts', 'tag_id', 'tags'], ['deal_tag', 'deal_id', 'crm_deals', 'tag_id', 'tags']] as [$pivot, $leftColumn, $leftTable, $rightColumn, $rightTable]) {
            if (! Schema::hasTable($pivot)) {
                continue;
            }
            if (Schema::hasColumn($pivot, 'tenant_id')) {
                $this->addAnomaly($counts, 'null_pivot_tenant_'.$pivot, (int) DB::table($pivot)->whereNull('tenant_id')->count());
                $this->addAnomaly($counts, 'cross_tenant_'.$pivot, $this->pivotMismatch($pivot, $leftColumn, $leftTable, $rightColumn, $rightTable));
                $this->addAnomaly($counts, 'pivot_tenant_left_mismatch_'.$pivot, $this->pivotTenantMismatch($pivot, $leftColumn, $leftTable));
                $this->addAnomaly($counts, 'pivot_tenant_right_mismatch_'.$pivot, $this->pivotTenantMismatch($pivot, $rightColumn, $rightTable));
            } else {
                $this->addAnomaly($counts, 'cross_tenant_'.$pivot, $this->pivotEndpointMismatch($pivot, $leftColumn, $leftTable, $rightColumn, $rightTable));
            }
            $this->addAnomaly($counts, 'orphan_'.$pivot.'_'.$leftColumn, $this->pivotOrphans($pivot, $leftColumn, $leftTable));
            $this->addAnomaly($counts, 'orphan_'.$pivot.'_'.$rightColumn, $this->pivotOrphans($pivot, $rightColumn, $rightTable));
        }
        $this->ownerAnomalies($counts);
        $this->duplicateCandidateAnomalies($counts);
        $this->unexpectedIdTypeAnomalies($counts);
        $this->incompatibleConstraintAnomalies($counts);
        ksort($counts);

        return $counts;
    }

    /** @param array<string,int> $counts */
    private function ownerAnomalies(array &$counts): void
    {
        if (! Schema::hasTable('tenant_memberships')) {
            return;
        }
        $totals = [
            'owner_without_membership' => 0,
            'owner_ambiguous_membership' => 0,
            'owner_inactive_membership' => 0,
            'owner_pending_membership' => 0,
            'owner_suspended_membership' => 0,
            'owner_revoked_membership' => 0,
        ];
        foreach ([['crm_deals', 'owner_user_id'], ['crm_contacts', 'owner_id'], ['crm_activities', 'owner_id']] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            $base = DB::table($table.' as owner_row')->whereNotNull('owner_row.'.$column)->leftJoin('tenant_memberships as membership', function ($join) use ($column): void {
                $join->on('membership.tenant_id', '=', 'owner_row.tenant_id')->on('membership.user_id', '=', 'owner_row.'.$column);
            });
            $totals['owner_without_membership'] += (int) (clone $base)->whereNull('membership.id')->count();
            $ambiguous = DB::table('tenant_memberships')->select('tenant_id', 'user_id')->groupBy('tenant_id', 'user_id')->havingRaw('COUNT(*) > 1');
            $ambiguousCount = (int) DB::query()->fromSub($ambiguous, 'ambiguous_memberships')
                ->join($table.' as ambiguous_owner', function ($join) use ($column): void {
                    $join->on('ambiguous_owner.tenant_id', '=', 'ambiguous_memberships.tenant_id')
                        ->on('ambiguous_owner.'.$column, '=', 'ambiguous_memberships.user_id');
                })->whereNotNull('ambiguous_owner.'.$column)->count();
            $totals['owner_ambiguous_membership'] += $ambiguousCount;
            if (Schema::hasColumn('tenant_memberships', 'status')) {
                $totals['owner_inactive_membership'] += (int) (clone $base)->where('membership.status', '<>', 'active')->count();
            }
            if (Schema::hasColumn('tenant_memberships', 'accepted_at')) {
                $totals['owner_pending_membership'] += (int) (clone $base)->whereNull('membership.accepted_at')->count();
            }
            if (Schema::hasColumn('tenant_memberships', 'suspended_at')) {
                $totals['owner_suspended_membership'] += (int) (clone $base)->whereNotNull('membership.suspended_at')->count();
            }
            if (Schema::hasColumn('tenant_memberships', 'revoked_at')) {
                $totals['owner_revoked_membership'] += (int) (clone $base)->whereNotNull('membership.revoked_at')->count();
            }
        }
        foreach ($totals as $code => $count) {
            $this->addAnomaly($counts, $code, $count);
        }
    }

    /** @param array<string,int> $counts */
    private function duplicateCandidateAnomalies(array &$counts): void
    {
        foreach (self::DUPLICATE_GROUPS as $table => $columns) {
            if (! Schema::hasTable($table) || ! $this->hasColumns($table, $columns)) {
                continue;
            }
            $duplicate = DB::table($table)->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1')->count();
            $this->addAnomaly($counts, 'duplicate_parent_candidate_'.$table, (int) $duplicate);
        }
    }

    /** @param array<string,int> $counts */
    private function unexpectedIdTypeAnomalies(array &$counts): void
    {
        $expected = [
            'tenants' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'crm_pipelines' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'crm_stages' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'crm_deals' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'crm_companies' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'crm_contacts' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'crm_activities' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'tags' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'leads' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'locations' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'customer_profiles' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'customer_notes' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'customer_tasks' => ['id' => ['integer', 'bigint', 'int', 'int unsigned']],
            'quotes' => ['id' => ['string', 'uuid', 'char', 'varchar']],
            'tenant_memberships' => ['id' => ['string', 'uuid', 'char', 'varchar']],
        ];
        foreach ($expected as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $allowed) {
                if (Schema::hasColumn($table, $column) && ! in_array($this->normalizedType($table, $column), $allowed, true)) {
                    $this->addAnomaly($counts, 'unexpected_id_type_'.$table.'.'.$column, 1);
                }
            }
        }
    }

    /** @param array<string,int> $counts */
    private function incompatibleConstraintAnomalies(array &$counts): void
    {
        foreach (self::CANDIDATE_KEYS as $name => $definition) {
            $table = (string) $definition[0];
            $columns = array_slice($definition, 1);
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($this->indexes($table) as $index) {
                if ((string) ($index['name'] ?? '') !== $name) {
                    continue;
                }
                $actual = array_values(array_map('strval', is_array($index['columns'] ?? null) ? $index['columns'] : []));
                if ($actual !== $columns || ! (bool) ($index['unique'] ?? false)) {
                    $this->addAnomaly($counts, 'incompatible_existing_constraint_'.$name, 1);
                }
            }
        }
    }

    private function edgeMismatch(string $childTable, string $foreignColumn, string $parentTable): int
    {
        if (! $this->relationAvailable($childTable, $foreignColumn, $parentTable)) {
            return 0;
        }
        return (int) DB::table($childTable.' as child')->join($parentTable.' as parent', 'parent.id', '=', 'child.'.$foreignColumn)
            ->whereNotNull('child.'.$foreignColumn)->whereColumn('child.tenant_id', '<>', 'parent.tenant_id')->count();
    }

    private function edgeOrphans(string $childTable, string $foreignColumn, string $parentTable): int
    {
        if (! $this->relationAvailable($childTable, $foreignColumn, $parentTable)) {
            return 0;
        }
        return (int) DB::table($childTable.' as child')->leftJoin($parentTable.' as parent', 'parent.id', '=', 'child.'.$foreignColumn)
            ->whereNotNull('child.'.$foreignColumn)->whereNull('parent.id')->count();
    }

    private function dealStagePipelineMismatch(): int
    {
        if (! $this->hasColumns('crm_deals', ['crm_pipeline_id', 'crm_stage_id']) || ! $this->hasColumns('crm_stages', ['id', 'crm_pipeline_id'])) {
            return 0;
        }
        return (int) DB::table('crm_deals as deal')->join('crm_stages as stage', 'stage.id', '=', 'deal.crm_stage_id')
            ->whereColumn('deal.crm_pipeline_id', '<>', 'stage.crm_pipeline_id')->count();
    }

    private function pivotMismatch(string $pivot, string $leftColumn, string $leftTable, string $rightColumn, string $rightTable): int
    {
        if (! $this->hasColumns($pivot, [$leftColumn, $rightColumn, 'tenant_id'])) {
            return 0;
        }
        return (int) DB::table($pivot.' as pivot')
            ->join($leftTable.' as left_endpoint', 'left_endpoint.id', '=', 'pivot.'.$leftColumn)
            ->join($rightTable.' as right_endpoint', 'right_endpoint.id', '=', 'pivot.'.$rightColumn)
            ->where(function ($query): void {
                $query->whereColumn('left_endpoint.tenant_id', '<>', 'right_endpoint.tenant_id')
                    ->orWhereColumn('pivot.tenant_id', '<>', 'left_endpoint.tenant_id')
                    ->orWhereColumn('pivot.tenant_id', '<>', 'right_endpoint.tenant_id');
            })->count();
    }

    private function pivotEndpointMismatch(string $pivot, string $leftColumn, string $leftTable, string $rightColumn, string $rightTable): int
    {
        if (! $this->hasColumns($pivot, [$leftColumn, $rightColumn])) {
            return 0;
        }
        return (int) DB::table($pivot.' as pivot')
            ->join($leftTable.' as left_endpoint', 'left_endpoint.id', '=', 'pivot.'.$leftColumn)
            ->join($rightTable.' as right_endpoint', 'right_endpoint.id', '=', 'pivot.'.$rightColumn)
            ->whereColumn('left_endpoint.tenant_id', '<>', 'right_endpoint.tenant_id')->count();
    }

    private function pivotTenantMismatch(string $pivot, string $column, string $parentTable): int
    {
        if (! $this->hasColumns($pivot, [$column, 'tenant_id'])) {
            return 0;
        }
        return (int) DB::table($pivot.' as pivot')->join($parentTable.' as parent', 'parent.id', '=', 'pivot.'.$column)
            ->whereNotNull('pivot.tenant_id')->whereColumn('pivot.tenant_id', '<>', 'parent.tenant_id')->count();
    }

    private function pivotOrphans(string $pivot, string $column, string $parentTable): int
    {
        if (! $this->hasColumns($pivot, [$column])) {
            return 0;
        }
        return (int) DB::table($pivot.' as pivot')->leftJoin($parentTable.' as parent', 'parent.id', '=', 'pivot.'.$column)
            ->whereNull('parent.id')->count();
    }

    private function relationAvailable(string $childTable, string $foreignColumn, string $parentTable): bool
    {
        return Schema::hasTable($childTable) && Schema::hasTable($parentTable)
            && $this->hasColumns($childTable, [$foreignColumn, 'tenant_id'])
            && $this->hasColumns($parentTable, ['id', 'tenant_id']);
    }

    /** @param list<string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $columns */
    private function hasAll(array $haystack, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! in_array($column, $haystack, true)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $haystack @param list<string> $columns */
    private function hasAny(array $haystack, array $columns): bool
    {
        foreach ($columns as $column) {
            if (in_array($column, $haystack, true)) {
                return true;
            }
        }

        return false;
    }

    private function isCanonicalBigintId(string $idType): bool
    {
        if (in_array($idType, ['bigint', 'bigint unsigned', 'int unsigned', 'unsigned bigint'], true)) {
            return true;
        }
        if ($idType !== 'integer' || DB::getDriverName() !== 'sqlite') {
            return false;
        }

        $definition = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            ['crm_deals'],
        );
        $sql = strtolower((string) ($definition->sql ?? ''));

        return str_contains($sql, 'primary key autoincrement');
    }

    /** @return list<string> */
    private function missingRequiredColumns(): array
    {
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = $table.'.'.$column;
                }
            }
        }
        sort($missing);

        return $missing;
    }

    private function boundedCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }

    /** @return array<string,mixed> */
    private function rowBoundary(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $idColumn = in_array('id', $columns, true) ? 'id' : ($columns[0] ?? null);
        if ($idColumn === null) {
            return ['count' => 0, 'hash' => hash('sha256', $table)];
        }
        $query = DB::table($table)->select($idColumn)->orderBy($idColumn);
        $first = $query->first();
        $last = (clone $query)->reorder($idColumn, 'desc')->first();
        return [
            'count' => $this->boundedCount($table),
            'hash' => hash('sha256', json_encode([
                'table' => $table,
                'first' => $this->opaqueValue($first === null ? null : ($first->{$idColumn} ?? null)),
                'last' => $this->opaqueValue($last === null ? null : ($last->{$idColumn} ?? null)),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    private function opaqueValue(mixed $value): ?string
    {
        return $value === null ? null : hash('sha256', (string) $value);
    }

    /** @param array<string,array<string,mixed>> $inventory */
    private function dataFingerprint(array $inventory): string
    {
        return hash('sha256', json_encode($inventory, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function schemaFingerprint(): string
    {
        $catalog = [];
        foreach (Schema::getTables() as $table) {
            $name = is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $catalog[$name] = ['columns' => $this->columns($name), 'indexes' => $this->indexes($name), 'foreign_keys' => $this->foreignKeys($name)];
        }
        ksort($catalog);
        return hash('sha256', json_encode($catalog, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return list<array<string,mixed>> */
    private function columns(string $table): array
    {
        $columns = Schema::getColumns($table);
        usort($columns, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        return array_map(static fn (array $column): array => [
            'name' => (string) ($column['name'] ?? ''),
            'type' => strtolower((string) ($column['type_name'] ?? $column['type'] ?? '')),
            'nullable' => (bool) ($column['nullable'] ?? false),
            'default' => $column['default'] ?? null,
            'auto_increment' => (bool) ($column['auto_increment'] ?? false),
        ], $columns);
    }

    /** @return list<array<string,mixed>> */
    private function indexes(string $table): array
    {
        $indexes = Schema::getIndexes($table);
        usort($indexes, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        return array_map(static fn (array $index): array => [
            'name' => (string) ($index['name'] ?? ''),
            'columns' => array_values(array_map('strval', is_array($index['columns'] ?? null) ? $index['columns'] : [])),
            'unique' => (bool) ($index['unique'] ?? false),
            'primary' => (bool) ($index['primary'] ?? false),
        ], $indexes);
    }

    /** @return list<array<string,mixed>> */
    private function foreignKeys(string $table): array
    {
        $keys = Schema::getForeignKeys($table);
        usort($keys, static fn (array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
        return array_map(static fn (array $key): array => [
            'name' => (string) ($key['name'] ?? ''),
            'columns' => array_values(array_map('strval', is_array($key['columns'] ?? null) ? $key['columns'] : [])),
            'foreign_table' => (string) ($key['foreign_table'] ?? ''),
            'foreign_columns' => array_values(array_map('strval', is_array($key['foreign_columns'] ?? null) ? $key['foreign_columns'] : [])),
            'on_delete' => strtolower((string) ($key['on_delete'] ?? '')),
            'on_update' => strtolower((string) ($key['on_update'] ?? '')),
        ], $keys);
    }

    private function normalizedType(string $table, string $column): string
    {
        return strtolower((string) Schema::getColumnType($table, $column));
    }

    /** @param array<string,int> $counts */
    private function addAnomaly(array &$counts, string $code, int $count): void
    {
        $counts[$code] = max(0, $count);
    }

    /** @return array<string,mixed> */
    private function candidateKeyDetails(): array
    {
        $details = [];
        foreach (self::CANDIDATE_KEYS as $name => $definition) {
            $table = (string) $definition[0];
            $columns = array_slice($definition, 1);
            $details[$name] = ['table' => $table, 'columns' => $columns, 'present' => Schema::hasTable($table) && $this->hasCompatibleIndex($table, $columns)];
        }
        ksort($details);
        return $details;
    }

    /** @param list<string> $columns */
    private function hasCompatibleIndex(string $table, array $columns): bool
    {
        foreach ($this->indexes($table) as $index) {
            if ((bool) ($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,int> $anomalies @return array<string,list<string>> */
    private function anomalyHashes(array $anomalies): array
    {
        $hashes = [];
        $remaining = 20;
        foreach ($anomalies as $code => $count) {
            if ($count > 0) {
                $hashes[$code] = [hash('sha256', $code.':'.(string) $count)];
                $remaining--;
                if ($remaining === 0) {
                    break;
                }
            }
        }
        ksort($hashes);
        return $hashes;
    }
}
