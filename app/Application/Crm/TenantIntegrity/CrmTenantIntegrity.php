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

    public const STATUS_EXPAND_FAILED = 'EXPAND_FAILED';

    public const STATUS_RECOVERY_REQUIRED_PARTIAL_WRITE = 'RECOVERY_REQUIRED_PARTIAL_WRITE';

    public const STATUS_EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED = 'EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED';

    public const STATUS_BLOCKED_PARTIAL_WRITE = 'RECOVERY_REQUIRED_PARTIAL_WRITE';

    public const STATUS_BLOCKED_NOT_IMPLEMENTED = 'BLOCKED_NOT_IMPLEMENTED';

    public const STATUS_BLOCKED_EXTERNAL = 'BLOCKED_EXTERNAL';

    public const STATUS_BACKFILLED = 'BACKFILLED';

    public const STATUS_BACKFILLED_NOOP = 'BACKFILLED_NOOP';

    public const STATUS_BACKFILL_DRY_RUN = 'BACKFILL_DRY_RUN';

    public const STATUS_BLOCKED_CHECKPOINT = 'BLOCKED_CHECKPOINT';

    public const STATUS_BLOCKED_SOURCE_DRIFT = 'BLOCKED_SOURCE_DRIFT';

    public const STATUS_CHECKPOINT_WRITE_FAILED = 'CHECKPOINT_WRITE_FAILED';

    private const MIGRATION = '2026_08_22_000001_expand_crm_tenant_integrity.php';

    private const BACKFILL_COMMAND = 'crm:reconcile-tenant-integrity';

    private const BACKFILL_CHECKPOINT_SCHEMA = 1;

    private const BACKFILL_CHECKPOINT_VERSION = 1;

    /** @var list<string> */
    private const BACKFILL_REPAIRABLE_ANOMALIES = [
        'null_pivot_tenant_contact_tag',
        'null_pivot_tenant_deal_tag',
    ];

    /** @var list<array{pivot:string,left:string,left_table:string,right:string,right_table:string}> */
    private const BACKFILL_PIVOTS = [
        [
            'pivot' => 'contact_tag',
            'left' => 'contact_id',
            'left_table' => 'crm_contacts',
            'right' => 'tag_id',
            'right_table' => 'tags',
        ],
        [
            'pivot' => 'deal_tag',
            'left' => 'deal_id',
            'left_table' => 'crm_deals',
            'right' => 'tag_id',
            'right_table' => 'tags',
        ],
    ];

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

    /** @var array<string, array{table: string, columns: list<string>}> */
    private const TRAVERSAL_INDEXES = [
        'contact_tag_tenant_contact_idx' => ['table' => 'contact_tag', 'columns' => ['tenant_id', 'contact_id']],
        'contact_tag_tenant_tag_idx' => ['table' => 'contact_tag', 'columns' => ['tenant_id', 'tag_id']],
        'deal_tag_tenant_deal_idx' => ['table' => 'deal_tag', 'columns' => ['tenant_id', 'deal_id']],
        'deal_tag_tenant_tag_idx' => ['table' => 'deal_tag', 'columns' => ['tenant_id', 'tag_id']],
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

    public function run(
        string $phase = 'preflight',
        ?string $fingerprint = null,
        int $limit = 500,
        ?string $checkpoint = null,
        bool $resume = false,
        bool $dryRun = false,
    ): CrmTenantIntegrityReport {
        return match (strtolower(trim($phase))) {
            'preflight', 'validate' => $this->preflight(strtolower(trim($phase))),
            'expand' => $this->expand($fingerprint),
            'backfill' => $this->backfill($limit, $checkpoint, $resume, $dryRun, $fingerprint),
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
                    'query_bounds' => [
                        'aggregate_counts' => 'exact',
                        'output_memory' => 'bounded',
                        'anomaly_hashes_max' => 20,
                        'row_boundary' => 'first_and_last_ids_only',
                        'scan_cost' => 'database-dependent',
                        'staging_plan_proof' => 'EXTERNAL_BLOCKED',
                    ],
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
        if ($this->engineRequiresExternalProof()) {
            return $this->copyReport($preflight, self::STATUS_EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED,
                blockingIssues: ['EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED'],
                details: [...$preflight->details, 'engine' => DB::getDriverName(), 'writes_performed' => false]);
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
                return $this->copyReport($preflight, self::STATUS_RECOVERY_REQUIRED_PARTIAL_WRITE,
                    writesPerformed: true,
                    blockingIssues: ['expand_failed', 'schema_fingerprint_unavailable_after_failure'],
                    details: [...$preflight->details, 'error_code' => get_class($exception), 'writes_state' => 'unknown']);
            }
            if ($afterFingerprint !== '' && hash_equals($before, $afterFingerprint)) {
                return $this->copyReport($preflight, self::STATUS_EXPAND_FAILED,
                    blockingIssues: ['expand_failed'],
                    details: [...$preflight->details, 'error_code' => get_class($exception), 'writes_performed' => false]);
            }

            return $this->copyReport($afterFailure, self::STATUS_RECOVERY_REQUIRED_PARTIAL_WRITE,
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

    public function backfill(
        int $limit = 500,
        ?string $checkpoint = null,
        bool $resume = false,
        bool $dryRun = false,
        ?string $expectedFingerprint = null,
    ): CrmTenantIntegrityReport {
        if ($limit < 1 || $limit > 2000) {
            throw new InvalidArgumentException('CRM tenant-integrity backfill limit must be between 1 and 2000.');
        }

        // The local contract deliberately proves only SQLite.  In particular,
        // do not treat a configured MySQL connection as a fake success seam.
        if (DB::getDriverName() !== 'sqlite') {
            return $this->report(
                'backfill',
                self::STATUS_EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED,
                self::SHAPE_UNKNOWN,
                '',
                writesPerformed: false,
                blockingIssues: ['EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED'],
                details: [
                    'engine' => DB::getDriverName(),
                    'limit' => $limit,
                    'writes_performed' => false,
                ],
            );
        }

        $pdo = DB::connection()->getPdo();
        $savepoint = false;
        try {
            $outerTransaction = DB::transactionLevel() > 0 || $pdo->inTransaction();
            if ($outerTransaction) {
                if (! app()->environment('testing') || ! $pdo->inTransaction()) {
                    return $this->report(
                        'backfill',
                        self::STATUS_BLOCKED_METADATA_FAILURE,
                        self::SHAPE_UNKNOWN,
                        '',
                        writesPerformed: false,
                        blockingIssues: ['sqlite_outer_transaction_active'],
                        details: [
                            'error_code' => $pdo->inTransaction()
                                ? 'outer_transaction_active'
                                : 'transaction_level_without_pdo_transaction',
                            'writes_performed' => false,
                        ],
                    );
                }

                // RefreshDatabase owns the outer test transaction.  The test-only
                // savepoint keeps service rollback scoped while the zero-row DML
                // upgrades SQLite to a write snapshot for the structural race proof.
                $pdo->exec('UPDATE "contact_tag" SET "tenant_id" = "tenant_id" WHERE 0');
                $pdo->exec('SAVEPOINT crm_tenant_integrity_backfill');
                $savepoint = true;
            } else {
                $pdo->exec('BEGIN IMMEDIATE');
            }
        } catch (Throwable $exception) {
            return $this->report(
                'backfill',
                self::STATUS_BLOCKED_METADATA_FAILURE,
                self::SHAPE_UNKNOWN,
                '',
                writesPerformed: false,
                blockingIssues: ['sqlite_write_snapshot_failed'],
                details: ['error_code' => get_class($exception), 'writes_performed' => false],
            );
        }

        $preflight = null;
        $failureReport = null;
        $transactionFailure = null;
        $sourceHashBefore = '';
        $sourceHashAfter = '';
        $checkpointState = null;
        $counts = [
            'selected' => 0,
            'updated' => 0,
            'contact_tag_updated' => 0,
            'deal_tag_updated' => 0,
        ];
        $hasMore = false;
        $dryRunRolledBack = false;
        try {
            $preflight = $this->preflight('backfill');
            if ($expectedFingerprint !== null
                && trim($expectedFingerprint) !== ''
                && ! hash_equals($preflight->schemaFingerprint, trim($expectedFingerprint))) {
                $failureReport = $this->copyReport($preflight, self::STATUS_BLOCKED_FINGERPRINT,
                    blockingIssues: ['schema_fingerprint_mismatch'],
                    details: [...$preflight->details, 'writes_performed' => false]);
                throw new RuntimeException('backfill_gate_abort');
            }
            if ($preflight->shape !== self::SHAPE_CANONICAL
                || $preflight->schemaFingerprint === ''
                || $preflight->missingRequiredTables !== []
                || $preflight->missingRequiredColumns !== []) {
                $failureReport = $this->copyReport($preflight, $preflight->status,
                    blockingIssues: $preflight->blockingIssues,
                    details: [...$preflight->details, 'writes_performed' => false]);
                throw new RuntimeException('backfill_gate_abort');
            }
            foreach (self::BACKFILL_PIVOTS as $definition) {
                if (! Schema::hasColumn($definition['pivot'], 'tenant_id')) {
                    $failureReport = $this->copyReport($preflight, self::STATUS_BLOCKED_REQUIRED_SCHEMA,
                        blockingIssues: ['missing_required_column:'.$definition['pivot'].'.tenant_id'],
                        details: [...$preflight->details, 'writes_performed' => false]);
                    throw new RuntimeException('backfill_gate_abort');
                }
            }

            $sourceHashBefore = $this->backfillSourceFingerprint();
            if ($resume) {
                [$checkpointState, $checkpointError] = $this->readBackfillCheckpoint($checkpoint);
                if ($checkpointError !== null) {
                    $failureReport = $this->copyReport($preflight, self::STATUS_BLOCKED_CHECKPOINT,
                        blockingIssues: ['checkpoint_invalid'],
                        details: [...$preflight->details, 'checkpoint_error' => $checkpointError, 'writes_performed' => false]);
                    throw new RuntimeException('backfill_gate_abort');
                }
                if (! hash_equals((string) ($checkpointState['schema_hash'] ?? ''), $preflight->schemaFingerprint)
                    || ! hash_equals((string) ($checkpointState['data_hash'] ?? ''), $preflight->dataFingerprint)
                    || ! hash_equals((string) ($checkpointState['source_hash'] ?? ''), $sourceHashBefore)) {
                    $failureReport = $this->copyReport($preflight, self::STATUS_BLOCKED_SOURCE_DRIFT,
                        blockingIssues: ['checkpoint_lineage_drift'],
                        details: [
                            ...$preflight->details,
                            'checkpoint_schema_hash' => (string) ($checkpointState['schema_hash'] ?? ''),
                            'checkpoint_data_hash' => (string) ($checkpointState['data_hash'] ?? ''),
                            'checkpoint_source_hash' => (string) ($checkpointState['source_hash'] ?? ''),
                            'source_hash' => $sourceHashBefore,
                            'writes_performed' => false,
                        ]);
                    throw new RuntimeException('backfill_gate_abort');
                }
            }

            // A resumed lineage drift takes precedence over any anomaly it may
            // introduce; matching lineage still reaches the complete anomaly gate.
            $blockingAnomalies = $this->backfillBlockingAnomalies($preflight->anomalies);
            if ($blockingAnomalies !== []) {
                $failureReport = $this->copyReport($preflight, self::STATUS_BLOCKED_ANOMALIES,
                    blockingIssues: $blockingAnomalies,
                    details: [
                        ...$preflight->details,
                        'backfill_gate' => 'only_null_pivot_provenance_is_repairable',
                        'writes_performed' => false,
                    ]);
                throw new RuntimeException('backfill_gate_abort');
            }

            $rows = $this->selectBackfillRows($limit);
            $recheck = $this->preflight('backfill');
            $recheckBlockingAnomalies = $this->backfillBlockingAnomalies($recheck->anomalies);
            if ($recheck->schemaFingerprint !== $preflight->schemaFingerprint) {
                $failureReport = $this->copyReport($recheck, self::STATUS_BLOCKED_SOURCE_DRIFT,
                    blockingIssues: ['schema_fingerprint_changed_in_transaction'],
                    details: [...$recheck->details, 'writes_performed' => false]);
                throw new RuntimeException('backfill_gate_abort');
            }
            if ($recheckBlockingAnomalies !== []) {
                $failureReport = $this->copyReport($recheck, self::STATUS_BLOCKED_ANOMALIES,
                    blockingIssues: $recheckBlockingAnomalies,
                    details: [
                        ...$recheck->details,
                        'backfill_gate' => 'rechecked_before_compare_and_set',
                        'writes_performed' => false,
                    ]);
                throw new RuntimeException('backfill_gate_abort');
            }

            foreach ($rows as $row) {
                $counts['selected']++;
                $leftTenant = DB::table($row['left_table'])->where('id', $row['left_id'])->value('tenant_id');
                $rightTenant = DB::table($row['right_table'])->where('id', $row['right_id'])->value('tenant_id');
                if ($leftTenant === null
                    || $rightTenant === null
                    || (string) $leftTenant !== (string) $rightTenant
                    || (string) $row['left_tenant_id'] !== (string) $leftTenant
                    || (string) $row['right_tenant_id'] !== (string) $rightTenant) {
                    throw new RuntimeException('backfill_changed_endpoint');
                }
                $affected = DB::table($row['pivot'])
                    ->where($row['left'], $row['left_id'])
                    ->where($row['right'], $row['right_id'])
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $leftTenant]);
                if ($affected !== 1) {
                    throw new RuntimeException('backfill_compare_and_set_failed');
                }
                $counts['updated']++;
                $counts[$row['pivot'].'_updated']++;
            }
            $hasMore = $this->backfillRemaining();
            $sourceHashAfter = $this->backfillSourceFingerprint();
            if ($dryRun) {
                if ($savepoint) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT crm_tenant_integrity_backfill');
                    $pdo->exec('RELEASE SAVEPOINT crm_tenant_integrity_backfill');
                } else {
                    $pdo->rollBack();
                }
                $dryRunRolledBack = true;
            } elseif ($savepoint) {
                $pdo->exec('RELEASE SAVEPOINT crm_tenant_integrity_backfill');
            } else {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            $transactionFailure = $exception;
            if ($savepoint) {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT crm_tenant_integrity_backfill');
                    $pdo->exec('RELEASE SAVEPOINT crm_tenant_integrity_backfill');
                } catch (Throwable) {
                    // Preserve the original failure report; the outer test transaction owns cleanup.
                }
            } elseif ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        if ($transactionFailure !== null) {
            if ($failureReport !== null) {
                return $failureReport;
            }
            $status = str_contains($transactionFailure->getMessage(), 'changed_endpoint')
                ? self::STATUS_BLOCKED_SOURCE_DRIFT
                : self::STATUS_BLOCKED_ANOMALIES;
            $baseReport = $preflight ?? $this->report('backfill', self::STATUS_BLOCKED_METADATA_FAILURE, self::SHAPE_UNKNOWN, '');

            return $this->copyReport($baseReport, $status,
                blockingIssues: [$status === self::STATUS_BLOCKED_SOURCE_DRIFT ? 'endpoint_changed_during_batch' : 'compare_and_set_failed'],
                details: [
                    ...$baseReport->details,
                    'counts' => $counts,
                    'source_hash' => $sourceHashBefore,
                    'error_code' => get_class($transactionFailure),
                    'writes_performed' => false,
                ]);
        }

        $status = $dryRunRolledBack
            ? self::STATUS_BACKFILL_DRY_RUN
            : ($counts['updated'] === 0 ? self::STATUS_BACKFILLED_NOOP : self::STATUS_BACKFILLED);
        $checkpointPayload = null;
        $lineageHash = $dryRunRolledBack ? $sourceHashBefore : $sourceHashAfter;
        if (! $dryRunRolledBack && $checkpoint !== null && trim($checkpoint) !== '') {
            $checkpointPayload = $this->buildBackfillCheckpoint(
                $preflight,
                $lineageHash,
                $counts,
                $hasMore,
                $checkpointState,
            );
            if (! $this->writeBackfillCheckpoint($checkpoint, $checkpointPayload)) {
                return $this->copyReport($preflight, self::STATUS_CHECKPOINT_WRITE_FAILED,
                    writesPerformed: $counts['updated'] > 0,
                    blockingIssues: ['checkpoint_write_failed'],
                    details: [
                        ...$preflight->details,
                        'counts' => $counts,
                        'has_more' => $hasMore,
                        'checkpoint' => ['written' => false],
                        'writes_performed' => $counts['updated'] > 0,
                    ]);
            }
        }

        return $this->copyReport($preflight, $status,
            writesPerformed: ! $dryRunRolledBack && $counts['updated'] > 0,
            blockingIssues: [],
            details: [
                ...$preflight->details,
                'limit' => $limit,
                'counts' => $counts,
                'has_more' => $hasMore,
                'source_hash' => $lineageHash,
                'checkpoint' => $checkpointPayload === null
                    ? ['written' => false]
                    : ['written' => true, 'sequence' => $checkpointPayload['sequence']],
                'dry_run' => $dryRun,
                'writes_performed' => ! $dryRunRolledBack && $counts['updated'] > 0,
            ]);
    }

    /** @param list<string> $blockingIssues */
    private function blockedPhase(string $phase, string $status, array $blockingIssues): CrmTenantIntegrityReport
    {
        $preflight = $this->preflight($phase);

        return $this->copyReport($preflight, $status, blockingIssues: $blockingIssues,
            details: [...$preflight->details, 'writes_performed' => false]);
    }

    /** @return list<array<string,mixed>> */
    private function selectBackfillRows(int $limit): array
    {
        $rows = [];
        $remaining = $limit;
        foreach (self::BACKFILL_PIVOTS as $definition) {
            if ($remaining < 1) {
                break;
            }
            $selected = DB::table($definition['pivot'].' as pivot')
                ->join($definition['left_table'].' as left_endpoint', 'left_endpoint.id', '=', 'pivot.'.$definition['left'])
                ->join($definition['right_table'].' as right_endpoint', 'right_endpoint.id', '=', 'pivot.'.$definition['right'])
                ->whereNull('pivot.tenant_id')
                ->orderBy('pivot.'.$definition['left'])
                ->orderBy('pivot.'.$definition['right'])
                ->limit($remaining)
                ->lockForUpdate()
                ->get([
                    'pivot.'.$definition['left'].' as left_id',
                    'pivot.'.$definition['right'].' as right_id',
                    'left_endpoint.tenant_id as left_tenant_id',
                    'right_endpoint.tenant_id as right_tenant_id',
                ]);
            foreach ($selected as $row) {
                $rows[] = [
                    ...$definition,
                    'left_id' => $row->left_id,
                    'right_id' => $row->right_id,
                    'left_tenant_id' => $row->left_tenant_id,
                    'right_tenant_id' => $row->right_tenant_id,
                ];
            }
            $remaining -= $selected->count();
        }

        return $rows;
    }

    private function backfillRemaining(): bool
    {
        foreach (self::BACKFILL_PIVOTS as $definition) {
            if (DB::table($definition['pivot'])->whereNull('tenant_id')->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,int> $anomalies
     * @return list<string>
     */
    private function backfillBlockingAnomalies(array $anomalies): array
    {
        $blocking = array_values(array_filter(
            array_keys(array_filter($anomalies, static fn (int $count): bool => $count > 0)),
            static fn (string $code): bool => ! in_array($code, self::BACKFILL_REPAIRABLE_ANOMALIES, true),
        ));
        sort($blocking);

        return $blocking;
    }

    private function backfillSourceFingerprint(): string
    {
        $context = hash_init('sha256');
        $tables = [];
        foreach (self::BACKFILL_PIVOTS as $definition) {
            $tables[] = $definition['left_table'];
            $tables[] = $definition['right_table'];
        }
        $tables = array_values(array_unique($tables));
        sort($tables);

        foreach ($tables as $table) {
            hash_update($context, "endpoint:{$table}\n");
            foreach (DB::table($table)->select(['id', 'tenant_id'])->orderBy('id')->cursor() as $row) {
                hash_update($context, json_encode([
                    'id' => $this->opaqueValue($row->id ?? null),
                    'tenant' => $this->opaqueValue($row->tenant_id ?? null),
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
            }
        }

        foreach (self::BACKFILL_PIVOTS as $definition) {
            hash_update($context, "pivot:{$definition['pivot']}\n");
            foreach (DB::table($definition['pivot'])
                ->select([$definition['left'], $definition['right'], 'tenant_id'])
                ->orderBy($definition['left'])
                ->orderBy($definition['right'])
                ->orderBy('tenant_id')
                ->cursor() as $row) {
                hash_update($context, json_encode([
                    'left' => $this->opaqueValue($row->{$definition['left']} ?? null),
                    'right' => $this->opaqueValue($row->{$definition['right']} ?? null),
                    'tenant' => $this->opaqueValue($row->tenant_id ?? null),
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
            }
        }

        return hash_final($context);
    }

    /** @return array{0: array<string,mixed>|null, 1: string|null} */
    private function readBackfillCheckpoint(?string $path): array
    {
        if ($path === null || trim($path) === '') {
            return [null, 'resume_requires_checkpoint'];
        }
        $resolved = $this->resolveBackfillPath($path);
        if (! is_file($resolved)) {
            return [null, 'checkpoint_missing'];
        }
        $contents = file_get_contents($resolved);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;
        if (! is_array($decoded)) {
            return [null, 'checkpoint_invalid_json'];
        }
        if (($decoded['schema'] ?? null) !== self::BACKFILL_CHECKPOINT_SCHEMA
            || ($decoded['version'] ?? null) !== self::BACKFILL_CHECKPOINT_VERSION
            || ($decoded['command'] ?? null) !== self::BACKFILL_COMMAND
            || ($decoded['phase'] ?? null) !== 'backfill'
            || ! in_array($decoded['status'] ?? null, ['partial', 'exhausted'], true)
            || ! is_int($decoded['sequence'] ?? null)
            || $decoded['sequence'] < 1
            || ! is_bool($decoded['has_more'] ?? null)
            || ! is_array($decoded['counts'] ?? null)
            || ! is_string($decoded['schema_hash'] ?? null)
            || ! is_string($decoded['data_hash'] ?? null)
            || ! is_string($decoded['source_hash'] ?? null)
            || ! is_string($decoded['validation_chain'] ?? null)) {
            return [null, 'checkpoint_lineage_invalid'];
        }
        foreach (['schema_hash', 'data_hash', 'source_hash', 'validation_chain'] as $hash) {
            if (preg_match('/^[a-f0-9]{64}$/', (string) $decoded[$hash]) !== 1) {
                return [null, 'checkpoint_hash_invalid'];
            }
        }
        foreach ($decoded['counts'] as $value) {
            if (! is_int($value) || $value < 0) {
                return [null, 'checkpoint_counts_invalid'];
            }
        }
        $checksum = $decoded['checksum'] ?? ($decoded['checkpoint_checksum'] ?? ($decoded['checkpoint_sha256'] ?? null));
        if (! is_string($checksum)
            || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1
            || ! hash_equals($checksum, $this->backfillCheckpointChecksum($decoded))) {
            return [null, 'checkpoint_checksum_invalid'];
        }
        if ($decoded['status'] === 'partial' && ! $decoded['has_more']) {
            return [null, 'checkpoint_status_invalid'];
        }

        return [$decoded, null];
    }

    /** @param array<string,mixed>|null $previous @return array<string,mixed> */
    private function buildBackfillCheckpoint(
        CrmTenantIntegrityReport $preflight,
        string $sourceHash,
        array $counts,
        bool $hasMore,
        ?array $previous,
    ): array {
        $previousCounts = is_array($previous['counts'] ?? null) ? $previous['counts'] : [];
        $cumulative = $counts;
        foreach ($counts as $key => $value) {
            $cumulative[$key] = (int) ($previousCounts[$key] ?? 0) + (int) $value;
        }
        $sequence = ((int) ($previous['sequence'] ?? 0)) + 1;
        $status = $hasMore ? 'partial' : 'exhausted';
        $previousChain = (string) ($previous['validation_chain'] ?? hash('sha256', self::BACKFILL_COMMAND.'|backfill|genesis'));
        $validationChain = hash('sha256', implode('|', [
            $previousChain,
            (string) $sequence,
            $preflight->schemaFingerprint,
            $preflight->dataFingerprint,
            $sourceHash,
            $status,
            json_encode($cumulative, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]));
        $payload = [
            'schema' => self::BACKFILL_CHECKPOINT_SCHEMA,
            'version' => self::BACKFILL_CHECKPOINT_VERSION,
            'command' => self::BACKFILL_COMMAND,
            'phase' => 'backfill',
            'sequence' => $sequence,
            'counts' => $cumulative,
            'has_more' => $hasMore,
            'status' => $status,
            'schema_hash' => $preflight->schemaFingerprint,
            'data_hash' => $preflight->dataFingerprint,
            'source_hash' => $sourceHash,
            'validation_chain' => $validationChain,
        ];
        $checksum = $this->backfillCheckpointChecksum($payload);
        $payload['checksum'] = $checksum;
        $payload['checkpoint_checksum'] = $checksum;
        $payload['checkpoint_sha256'] = $checksum;

        return $payload;
    }

    /** @param array<string,mixed> $payload */
    private function backfillCheckpointChecksum(array $payload): string
    {
        unset($payload['checksum'], $payload['checkpoint_checksum'], $payload['checkpoint_sha256']);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string,mixed> $payload */
    private function writeBackfillCheckpoint(string $path, array $payload): bool
    {
        $resolved = $this->resolveBackfillPath($path);
        $directory = dirname($resolved);
        if (! is_dir($directory)) {
            return false;
        }
        $temporary = $resolved.'.'.bin2hex(random_bytes(6)).'.tmp';
        try {
            $written = file_put_contents($temporary, json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ), LOCK_EX);
            if ($written === false || ! rename($temporary, $resolved)) {
                @unlink($temporary);

                return false;
            }

            return true;
        } catch (Throwable) {
            @unlink($temporary);

            return false;
        }
    }

    private function resolveBackfillPath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return base_path($path);
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
    private function copyReport(CrmTenantIntegrityReport $report, string $status, bool $writesPerformed = false, ?array $blockingIssues = null, ?array $details = null): CrmTenantIntegrityReport
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
            blockingIssues: $blockingIssues ?? $report->blockingIssues,
            details: $details ?? $report->details,
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function inventory(): array
    {
        $tables = [...self::REQUIRED_TABLES, ...array_values(array_filter(self::OPTIONAL_TABLES, static fn (string $table): bool => Schema::hasTable($table)))];
        $inventory = [];
        foreach ($tables as $table) {
            $inventory[$table] = ['row_count' => $this->aggregateCount($table), 'row_boundary' => $this->rowBoundary($table)];
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
                $this->addAnomaly($counts, 'null_endpoint_tenant_'.$pivot, $this->pivotNullEndpointTenant($pivot, $leftColumn, $leftTable, $rightColumn, $rightTable));
            } else {
                $this->addAnomaly($counts, 'cross_tenant_'.$pivot, $this->pivotEndpointMismatch($pivot, $leftColumn, $leftTable, $rightColumn, $rightTable));
            }
            $this->addAnomaly($counts, 'orphan_'.$pivot.'_'.$leftColumn, $this->pivotOrphans($pivot, $leftColumn, $leftTable));
            $this->addAnomaly($counts, 'orphan_'.$pivot.'_'.$rightColumn, $this->pivotOrphans($pivot, $rightColumn, $rightTable));
            $this->addAnomaly($counts, 'duplicate_pivot_'.$pivot, $this->duplicatePivot($pivot, $leftColumn, $rightColumn));
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
            'crm_deals' => ['id' => ['integer', 'bigint', 'bigint unsigned']],
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
        foreach (self::TRAVERSAL_INDEXES as $name => $definition) {
            $table = $definition['table'];
            $columns = $definition['columns'];
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($this->indexes($table) as $index) {
                if ((string) ($index['name'] ?? '') !== $name) {
                    continue;
                }
                $actual = array_values(array_map('strval', is_array($index['columns'] ?? null) ? $index['columns'] : []));
                if ($actual !== $columns || (bool) ($index['unique'] ?? false) || (bool) ($index['primary'] ?? false)) {
                    $this->addAnomaly($counts, 'incompatible_traversal_index_'.$name, 1);
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

    private function pivotNullEndpointTenant(string $pivot, string $leftColumn, string $leftTable, string $rightColumn, string $rightTable): int
    {
        if (! $this->hasColumns($pivot, [$leftColumn, $rightColumn, 'tenant_id'])
            || ! $this->hasColumns($leftTable, ['id', 'tenant_id'])
            || ! $this->hasColumns($rightTable, ['id', 'tenant_id'])) {
            return 0;
        }

        return (int) DB::table($pivot.' as pivot')
            ->join($leftTable.' as left_endpoint', 'left_endpoint.id', '=', 'pivot.'.$leftColumn)
            ->join($rightTable.' as right_endpoint', 'right_endpoint.id', '=', 'pivot.'.$rightColumn)
            ->where(function ($query): void {
                $query->whereNull('left_endpoint.tenant_id')->orWhereNull('right_endpoint.tenant_id');
            })->count();
    }

    private function duplicatePivot(string $pivot, string $leftColumn, string $rightColumn): int
    {
        if (! $this->hasColumns($pivot, [$leftColumn, $rightColumn])) {
            return 0;
        }

        return (int) DB::table($pivot)
            ->select([$leftColumn, $rightColumn])
            ->groupBy([$leftColumn, $rightColumn])
            ->havingRaw('COUNT(*) > 1')
            ->count();
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
        if (in_array($idType, ['bigint', 'bigint unsigned'], true)) {
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

    private function engineRequiresExternalProof(): bool
    {
        return ! in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true);
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

    private function aggregateCount(string $table): int
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
            'count' => $this->aggregateCount($table),
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
        foreach (Schema::getColumns($table) as $definition) {
            if ((string) ($definition['name'] ?? '') === $column) {
                return strtolower((string) ($definition['type_name'] ?? $definition['type'] ?? ''));
            }
        }

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
