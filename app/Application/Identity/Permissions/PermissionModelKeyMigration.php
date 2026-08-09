<?php

declare(strict_types=1);

namespace App\Application\Identity\Permissions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PermissionModelKeyMigration
{
    public const COMMAND_VERSION = 'spatie-uuid-model-key-v1';

    public const SHAPE_CANONICAL = 'canonical_uuid';

    public const SHAPE_EMPTY_LEGACY = 'legacy_bigint_empty';

    public const SHAPE_SQLITE_UUID_TEXT = 'legacy_sqlite_uuid_text';

    public const SHAPE_LEGACY_UNRESOLVED = 'legacy_numeric_or_unresolved';

    public const SHAPE_MIXED = 'mixed_or_unknown';

    public const SHAPE_MISSING = 'missing_required_table';

    private const SOURCE_PERMISSION = 'model_has_permissions';

    private const SOURCE_ROLE = 'model_has_roles';

    private const SHADOW_PERMISSION = 'model_has_permissions_uuid_shadow';

    private const SHADOW_ROLE = 'model_has_roles_uuid_shadow';

    private const BACKUP_PERMISSION = 'model_has_permissions_bigint_legacy';

    private const BACKUP_ROLE = 'model_has_roles_bigint_legacy';

    private const OWNERSHIP = 'spatie_uuid_permission_pivot_ownership';

    private const OWNERSHIP_KEY = 'spatie-uuid-shadow-owner-v1';

    private const MIGRATION = '2026_08_23_000001_expand_spatie_uuid_permission_pivots.php';

    private const MAX_BATCH_SIZE = 10000;

    /**
     * @throws InvalidArgumentException
     */
    public function run(
        string $phase = 'preflight',
        int $batchSize = 100,
        ?string $checkpointPath = null,
        ?string $artifactPath = null,
        bool $dryRun = false,
        ?string $expectedHead = null,
        ?string $expectedTree = null,
    ): PermissionModelKeyMigrationReport {
        return match (strtolower(trim($phase))) {
            'preflight' => $this->preflight(),
            'expand' => $this->expand(),
            'backfill' => $this->backfill($batchSize, $checkpointPath, $dryRun),
            'validate' => $this->validate($artifactPath, $expectedHead, $expectedTree),
            'contract' => $this->blockedContract(),
            default => throw new InvalidArgumentException('Permission model-key phase must be preflight, expand, backfill, or validate.'),
        };
    }

    public function preflight(): PermissionModelKeyMigrationReport
    {
        $driver = DB::getDriverName();
        $scope = $this->verificationScope($driver);
        $missing = $this->missingRequiredTables();
        $anomalies = [];
        $blocking = [];

        if ($missing !== []) {
            $anomalies['MISSING_REQUIRED_TABLE'] = count($missing);

            return $this->report(
                phase: 'preflight',
                status: 'blocked',
                shape: self::SHAPE_MISSING,
                driver: $driver,
                scope: $scope,
                anomalies: $anomalies,
                blockingIssues: ['MISSING_REQUIRED_TABLE'],
                details: ['missing_tables' => $missing],
            );
        }

        if ((bool) config('permission.teams', false)) {
            $anomalies['TEAMS_SHAPE_UNSUPPORTED'] = 1;
            $blocking[] = 'TEAMS_SHAPE_UNSUPPORTED';
        }

        if ($this->configurationDrift()) {
            $anomalies['CONFIG_TABLE_OR_COLUMN_DRIFT'] = 1;
            $blocking[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
        }

        $sourceSchemaIssues = $this->sourceSchemaIssues();
        foreach ($sourceSchemaIssues as $issue) {
            $anomalies[$issue] = ($anomalies[$issue] ?? 0) + 1;
            $blocking[] = $issue;
        }

        $cutoverIssues = $this->cutoverStateIssues();
        foreach ($cutoverIssues as $issue) {
            $anomalies[$issue] = ($anomalies[$issue] ?? 0) + 1;
            $blocking[] = $issue;
        }

        $sourceSnapshots = $this->sourceSnapshots();
        $source = $this->sourceRows();

        $scanned = $this->scanSourceAnomaliesStreaming();
        foreach ($scanned as $code => $count) {
            $anomalies[$code] = ($anomalies[$code] ?? 0) + $count;
            $blocking[] = $code;
        }

        $shape = $this->classifyShape($source, $sourceSchemaIssues, $cutoverIssues);
        if ($shape === self::SHAPE_MIXED) {
            $anomalies['MIXED_OR_UNKNOWN_SHAPE'] = ($anomalies['MIXED_OR_UNKNOWN_SHAPE'] ?? 0) + 1;
            $blocking[] = 'MIXED_OR_UNKNOWN_SHAPE';
        }

        $shadow = $this->shadowSnapshots();
        $schema = $this->schemaPayload();
        $status = $blocking === [] && in_array($shape, [
            self::SHAPE_CANONICAL,
            self::SHAPE_EMPTY_LEGACY,
            self::SHAPE_SQLITE_UUID_TEXT,
        ], true) ? 'ready' : 'blocked';

        return $this->report(
            phase: 'preflight',
            status: $status,
            shape: $shape,
            driver: $driver,
            scope: $scope,
            source: $sourceSnapshots,
            shadow: $shadow,
            anomalies: $anomalies,
            blockingIssues: $blocking,
            schema: $schema,
            details: [
                'command_version' => self::COMMAND_VERSION,
                'config_fingerprint' => $this->configurationFingerprint(),
                'source_schema_fingerprint' => $this->sourceSchemaFingerprint(),
            ],
        );
    }

    public function expand(): PermissionModelKeyMigrationReport
    {
        if (DB::getDriverName() !== 'sqlite') {
            return $this->sqliteOnlyBlocked('expand');
        }
        $preflight = $this->preflight();
        if ($preflight->status !== 'ready') {
            return $this->phaseReport($preflight, 'expand');
        }

        if ($preflight->shape === self::SHAPE_CANONICAL) {
            return $this->phaseReport($preflight, 'expand', 'canonical_noop');
        }

        $shadowsExist = Schema::hasTable(self::SHADOW_PERMISSION) && Schema::hasTable(self::SHADOW_ROLE);
        if ($shadowsExist && $this->shadowSchemaIssues() === []) {
            return $this->phaseReport($preflight, 'expand', 'already_expanded');
        }

        try {
            $migration = require database_path('migrations/'.self::MIGRATION);
            $migration->up();
        } catch (Throwable $exception) {
            return $this->phaseReport(
                $preflight,
                'expand',
                'blocked',
                ['EXPANSION_FAILED'],
                ['error_class' => $exception::class],
            );
        }

        $post = $this->preflight();
        $issues = $this->shadowSchemaIssues();
        if ($post->status === 'ready' && $issues === []) {
            return $this->phaseReport($post, 'expand', 'expanded', [], ['writes_performed' => true], writesPerformed: true);
        }

        return $this->phaseReport($post, 'expand', 'blocked', ['SHADOW_SCHEMA_DRIFT']);
    }

    /**
     * Authorize the additive migration before it is allowed to create any table.
     *
     * The migration file is intentionally a thin DDL adapter.  Keeping this
     * gate here means a direct `migrate` invocation cannot bypass the same
     * preflight, ownership, and schema fingerprint checks used by the command.
     */
    public function authorizeExpansionMigration(): bool
    {
        $this->assertLocalEngine();

        $preflight = $this->preflight();
        if ($preflight->status !== 'ready') {
            $issue = $preflight->blockingIssues[0] ?? 'PREFLIGHT_BLOCKED';

            throw new RuntimeException('EXPANSION_PRECHECK_BLOCKED:'.$issue);
        }
        if ($preflight->shape === self::SHAPE_CANONICAL) {
            return false;
        }

        if (Schema::hasTable(self::BACKUP_PERMISSION) || Schema::hasTable(self::BACKUP_ROLE)) {
            throw new RuntimeException('EXPANSION_BACKUP_OCCUPIED');
        }

        $permissionShadow = Schema::hasTable(self::SHADOW_PERMISSION);
        $roleShadow = Schema::hasTable(self::SHADOW_ROLE);
        if ($permissionShadow xor $roleShadow) {
            throw new RuntimeException('PARTIAL_SHADOW_EXPANSION');
        }
        if ($permissionShadow && $this->shadowSchemaIssues() !== []) {
            throw new RuntimeException('SHADOW_SCHEMA_DRIFT');
        }
        if ($permissionShadow && $roleShadow) {
            return false;
        }

        $expectedFingerprint = (string) ($preflight->schema['fingerprint'] ?? '');
        if ($expectedFingerprint === '' || ! hash_equals($expectedFingerprint, $this->schemaFingerprint())) {
            throw new RuntimeException('SOURCE_CHANGED_SINCE_PREFLIGHT');
        }

        return true;
    }

    /**
     * Authorize a direct migration rollback.  Only exact, empty shadows that
     * this service created may be removed; legacy backups or populated tables
     * make rollback fail closed.
     */
    public function authorizeRollbackMigration(): bool
    {
        $this->assertLocalEngine();

        if (Schema::hasTable(self::BACKUP_PERMISSION) || Schema::hasTable(self::BACKUP_ROLE)) {
            throw new RuntimeException('ROLLBACK_BACKUP_OCCUPIED');
        }

        $permissionShadow = Schema::hasTable(self::SHADOW_PERMISSION);
        $roleShadow = Schema::hasTable(self::SHADOW_ROLE);
        if (! $permissionShadow && ! $roleShadow) {
            return false;
        }
        if ($permissionShadow xor $roleShadow) {
            throw new RuntimeException('PARTIAL_SHADOW_EXPANSION');
        }
        if ($this->shadowSchemaIssues() !== []) {
            throw new RuntimeException('SHADOW_SCHEMA_DRIFT');
        }
        if (! $this->migrationOwnsShadows()) {
            throw new RuntimeException('ROLLBACK_SHADOW_OWNERSHIP_UNPROVEN');
        }
        if (DB::table(self::SHADOW_PERMISSION)->count() > 0 || DB::table(self::SHADOW_ROLE)->count() > 0) {
            throw new RuntimeException('ROLLBACK_SHADOW_NOT_EMPTY');
        }

        $preflight = $this->preflight();
        if ($preflight->status !== 'ready') {
            $issue = $preflight->blockingIssues[0] ?? 'PREFLIGHT_BLOCKED';

            throw new RuntimeException('ROLLBACK_PRECHECK_BLOCKED:'.$issue);
        }
        $expectedFingerprint = (string) ($preflight->schema['fingerprint'] ?? '');
        if ($expectedFingerprint === '' || ! hash_equals($expectedFingerprint, $this->schemaFingerprint())) {
            throw new RuntimeException('SOURCE_CHANGED_SINCE_PREFLIGHT');
        }

        return true;
    }

    private function migrationOwnsShadows(): bool
    {
        if (! Schema::hasTable(self::OWNERSHIP)
            || Schema::getColumnListing(self::OWNERSHIP) !== [
                'owner_key',
                'command_version',
                'permission_schema_sha256',
                'role_schema_sha256',
            ]
            || ! $this->hasPrimaryKey(self::OWNERSHIP, ['owner_key'])) {
            return false;
        }

        $rows = DB::table(self::OWNERSHIP)->get();
        if ($rows->count() !== 1) {
            return false;
        }
        $row = $rows->first();

        return $row !== null
            && hash_equals(self::OWNERSHIP_KEY, (string) ($row->owner_key ?? ''))
            && hash_equals(self::COMMAND_VERSION, (string) ($row->command_version ?? ''))
            && hash_equals($this->tableFingerprint(self::SHADOW_PERMISSION), (string) ($row->permission_schema_sha256 ?? ''))
            && hash_equals($this->tableFingerprint(self::SHADOW_ROLE), (string) ($row->role_schema_sha256 ?? ''));
    }

    public function recordExpansionOwnershipMigration(): void
    {
        $this->assertLocalEngine();
        if (Schema::hasTable(self::OWNERSHIP)) {
            throw new RuntimeException('EXPANSION_OWNERSHIP_OCCUPIED');
        }
        if ($this->shadowSchemaIssues() !== []) {
            throw new RuntimeException('SHADOW_SCHEMA_DRIFT');
        }
        Schema::create(self::OWNERSHIP, static function (Blueprint $table): void {
            $table->string('owner_key')->primary();
            $table->string('command_version');
            $table->char('permission_schema_sha256', 64);
            $table->char('role_schema_sha256', 64);
        });
        DB::table(self::OWNERSHIP)->insert([
            'owner_key' => self::OWNERSHIP_KEY,
            'command_version' => self::COMMAND_VERSION,
            'permission_schema_sha256' => $this->tableFingerprint(self::SHADOW_PERMISSION),
            'role_schema_sha256' => $this->tableFingerprint(self::SHADOW_ROLE),
        ]);
        if (! $this->migrationOwnsShadows()) {
            Schema::dropIfExists(self::OWNERSHIP);

            throw new RuntimeException('EXPANSION_OWNERSHIP_UNPROVEN');
        }
    }

    public function backfill(int $batchSize = 100, ?string $checkpointPath = null, bool $dryRun = false): PermissionModelKeyMigrationReport
    {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('Permission model-key batch size must be between 1 and '.self::MAX_BATCH_SIZE.'.');
        }
        if (DB::getDriverName() !== 'sqlite') {
            return $this->sqliteOnlyBlocked('backfill');
        }

        $preflight = $this->preflight();
        if ($preflight->status !== 'ready') {
            return $this->phaseReport($preflight, 'backfill');
        }
        if ($preflight->shape === self::SHAPE_CANONICAL) {
            return $this->phaseReport($preflight, 'backfill', 'canonical_noop');
        }
        if ($this->shadowSchemaIssues() !== []) {
            return $this->phaseReport($preflight, 'backfill', 'blocked', ['EXPAND_REQUIRED']);
        }

        $currentSnapshots = $this->sourceSnapshots();
        $schemaFingerprint = $this->schemaFingerprint();
        $checkpoint = $this->readCheckpoint($checkpointPath);
        if ($checkpoint['error'] !== null) {
            return $this->phaseReport($preflight, 'backfill', 'blocked', [$checkpoint['error']]);
        }
        /** @var array<string, mixed>|null $checkpointPayload */
        $checkpointPayload = $checkpoint['payload'];
        $cursors = [self::SOURCE_PERMISSION => null, self::SOURCE_ROLE => null];
        $previousChain = hash('sha256', self::COMMAND_VERSION.'|genesis');
        $batchSequence = 0;
        if ($checkpointPayload !== null) {
            $checkpointError = $this->validateCheckpoint($checkpointPayload, $batchSize, $currentSnapshots, $schemaFingerprint);
            if ($checkpointError !== null) {
                return $this->phaseReport($preflight, 'backfill', 'blocked', [$checkpointError]);
            }
            $cursors = is_array($checkpointPayload['cursors'] ?? null) ? array_replace($cursors, $checkpointPayload['cursors']) : $cursors;
            $previousChain = (string) ($checkpointPayload['chain_sha256'] ?? $previousChain);
            $batchSequence = (int) ($checkpointPayload['batch_sequence'] ?? 0);
            if (($checkpointPayload['has_more'] ?? true) === false) {
                return $this->phaseReport($preflight, 'backfill', 'complete', [], ['checkpoint' => $this->checkpointSummary($checkpointPayload)]);
            }
        }

        $selectedByTable = [];
        $nextCursors = $cursors;
        $examined = 0;
        $batchIssues = [];
        $remainingBatch = $batchSize;
        foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
            [$selected, $nextCursor, $cursorError] = $this->selectBatchStreaming($table, $cursors[$table], $remainingBatch);
            if ($cursorError !== null) {
                return $this->phaseReport($preflight, 'backfill', 'blocked', [$cursorError]);
            }
            $selectedByTable[$table] = $selected;
            if ($nextCursor !== null) {
                $nextCursors[$table] = $nextCursor;
            }
            $examined += count($selected);
            $remainingBatch = max(0, $remainingBatch - count($selected));
            foreach ($selected as $row) {
                foreach ($this->rowIssues($row, $table) as $code) {
                    $batchIssues[$code] = ($batchIssues[$code] ?? 0) + 1;
                }
            }
        }

        if ($batchIssues !== []) {
            return $this->phaseReport($preflight, 'backfill', 'blocked', array_keys($batchIssues), [
                'batch_anomalies' => $batchIssues,
                'checkpoint' => $checkpointPayload === null ? [] : $this->checkpointSummary($checkpointPayload),
            ]);
        }

        $copied = 0;
        $alreadyPresent = 0;
        try {
            DB::beginTransaction();
            foreach ($selectedByTable as $table => $rows) {
                $shadow = $table === self::SOURCE_PERMISSION ? self::SHADOW_PERMISSION : self::SHADOW_ROLE;
                $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
                foreach ($rows as $row) {
                    $values = [
                        $pivot => (string) $row->{$pivot},
                        'model_type' => (string) $row->model_type,
                        'model_id' => (string) $row->model_id,
                    ];
                    $inserted = DB::table($shadow)->insertOrIgnore($values);
                    if ($inserted === 1) {
                        $copied++;
                    } else {
                        $alreadyPresent++;
                    }
                }
            }

            if ($this->sourceSnapshots() !== $currentSnapshots) {
                throw new RuntimeException('SOURCE_CHANGED_SINCE_CHECKPOINT');
            }

            if ($dryRun) {
                DB::rollBack();

                return $this->phaseReport($preflight, 'backfill', 'dry_run', [], [
                    'batch_size' => $batchSize,
                    'has_more' => $this->hasMoreStreaming($nextCursors),
                ], $examined, $copied, $alreadyPresent, true);
            }
            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $code = $exception->getMessage() === 'SOURCE_CHANGED_SINCE_CHECKPOINT'
                ? 'SOURCE_CHANGED_SINCE_CHECKPOINT'
                : 'BACKFILL_FAILED';

            return $this->phaseReport($preflight, 'backfill', 'blocked', [$code]);
        }

        $hasMore = $this->hasMoreStreaming($nextCursors);
        $batchSequence++;
        $checkpointSummary = [
            'command_version' => self::COMMAND_VERSION,
            'phase' => 'backfill',
            'driver' => DB::getDriverName(),
            'database_version' => $this->databaseVersion(),
            'cursor_version' => 1,
            'batch_size' => $batchSize,
            'batch_sequence' => $batchSequence,
            'source' => $currentSnapshots,
            'schema_fingerprint' => $schemaFingerprint,
            'source_schema_fingerprint' => $this->sourceSchemaFingerprint(),
            'config_fingerprint' => $this->configurationFingerprint(),
            'cursor_schema' => $this->cursorSchema(),
            'cursors' => $nextCursors,
            'rows_examined' => $examined,
            'rows_copied' => $copied,
            'rows_already_present' => $alreadyPresent,
            'has_more' => $hasMore,
            'anomaly_counts' => [],
            'previous_chain_sha256' => $previousChain,
        ];
        $checkpointSummary['batch_payload_sha256'] = $this->batchPayloadChecksum($checkpointSummary);
        $checkpointSummary['chain_sha256'] = $this->chainChecksum($checkpointSummary);
        $checkpointSummary['checkpoint_sha256'] = $this->checkpointChecksum($checkpointSummary);
        if ($checkpointPath !== null && trim($checkpointPath) !== '') {
            if (! $this->writeJsonAtomic($checkpointPath, $checkpointSummary)) {
                return $this->phaseReport($preflight, 'backfill', 'blocked', ['CHECKPOINT_WRITE_FAILED']);
            }
        }

        return $this->phaseReport($preflight, 'backfill', $hasMore ? 'pending' : 'complete', [], [
            'batch_size' => $batchSize,
            'has_more' => $hasMore,
            'checkpoint' => $this->checkpointSummary($checkpointSummary),
        ], $examined, $copied, $alreadyPresent, false, true);
    }

    public function validate(
        ?string $artifactPath = null,
        ?string $expectedHead = null,
        ?string $expectedTree = null,
    ): PermissionModelKeyMigrationReport {
        if (DB::getDriverName() !== 'sqlite') {
            return $this->sqliteOnlyBlocked('validate');
        }
        $preflight = $this->preflight();
        if ($preflight->status !== 'ready') {
            return $this->phaseReport($preflight, 'validate');
        }
        if ($this->shadowSchemaIssues() !== []) {
            return $this->phaseReport($preflight, 'validate', 'blocked', ['EXPAND_REQUIRED']);
        }

        $sourceSnapshots = $this->sourceSnapshots();
        $shadowSnapshots = $this->shadowSnapshots();
        $anomalies = $this->scanSourceAnomaliesStreaming();
        $blocking = array_keys($anomalies);

        foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
            $shadowTable = $table === self::SOURCE_PERMISSION ? self::SHADOW_PERMISSION : self::SHADOW_ROLE;
            $diff = $this->compareTupleStreams($table, $shadowTable);
            if ($diff['missing'] > 0) {
                $anomalies['SHADOW_ASSIGNMENT_MISSING'] = ($anomalies['SHADOW_ASSIGNMENT_MISSING'] ?? 0) + $diff['missing'];
            }
            if ($diff['extra'] > 0) {
                $anomalies['SHADOW_ASSIGNMENT_EXTRA'] = ($anomalies['SHADOW_ASSIGNMENT_EXTRA'] ?? 0) + $diff['extra'];
            }
        }
        $blocking = array_keys($anomalies);

        $replayProof = $this->measureReplay();
        if ($replayProof['error'] !== null) {
            $anomalies[$replayProof['error']] = ($anomalies[$replayProof['error']] ?? 0) + 1;
            $blocking[] = $replayProof['error'];
        } elseif ($replayProof['rows_changed'] > 0) {
            $anomalies['REPLAY_NOT_IDEMPOTENT'] = $replayProof['rows_changed'];
            $blocking[] = 'REPLAY_NOT_IDEMPOTENT';
        }

        $sourceAfter = $this->sourceSnapshots();
        $shadowAfter = $this->shadowSnapshots();
        if ($sourceAfter !== $sourceSnapshots) {
            $anomalies['SOURCE_CHANGED_SINCE_CHECKPOINT'] = 1;
            $blocking[] = 'SOURCE_CHANGED_SINCE_CHECKPOINT';
        }
        if ($shadowAfter !== $shadowSnapshots) {
            $anomalies['SHADOW_CHANGED_DURING_VALIDATION'] = 1;
            $blocking[] = 'SHADOW_CHANGED_DURING_VALIDATION';
        }
        $foreignKeyIssues = $this->foreignKeyIssues();
        if (in_array('FOREIGN_KEY_INTROSPECTION_UNAVAILABLE', $foreignKeyIssues, true)) {
            $anomalies['FOREIGN_KEY_INTROSPECTION_UNAVAILABLE'] = 1;
            $blocking[] = 'FOREIGN_KEY_INTROSPECTION_UNAVAILABLE';
        } elseif ($foreignKeyIssues !== []) {
            $anomalies['FOREIGN_KEY_INTEGRITY'] = count($foreignKeyIssues);
            $blocking[] = 'FOREIGN_KEY_INTEGRITY';
        }

        $identity = $this->resolveGitIdentity($expectedHead, $expectedTree);
        if ($identity['error'] !== null) {
            $anomalies[$identity['error']] = ($anomalies[$identity['error']] ?? 0) + 1;
            $blocking[] = $identity['error'];
        }

        $payload = [
            'command_version' => self::COMMAND_VERSION,
            'phase' => 'validate',
            'head' => $identity['head'],
            'tree' => $identity['tree'],
            'verification_scope' => $this->verificationScope(DB::getDriverName()),
            'driver' => DB::getDriverName(),
            'database_version' => $this->databaseVersion(),
            'expires_at' => now()->addHours(24)->utc()->toIso8601String(),
            'config_fingerprint' => $this->configurationFingerprint(),
            'source' => $sourceSnapshots,
            'shadow' => $shadowSnapshots,
            'schema_fingerprint' => $this->schemaFingerprint(),
            'anomaly_counts' => $anomalies,
            'blocking_issues' => array_values(array_unique($blocking)),
            'replay_proof' => [
                'measured' => $replayProof['measured'],
                'passes' => $replayProof['passes'],
                'idempotent' => $replayProof['error'] === null
                    && $replayProof['rows_changed'] === 0
                    && ($anomalies['SHADOW_ASSIGNMENT_MISSING'] ?? 0) === 0
                    && ($anomalies['SHADOW_ASSIGNMENT_EXTRA'] ?? 0) === 0,
                'rows_changed_on_replay' => $replayProof['rows_changed'],
                'strategy' => 'transactional_exact_key_insert_or_ignore_rollback',
            ],
        ];
        $artifact = $this->persistOrVerifyArtifact($artifactPath, $payload);
        if ($artifact['error'] !== null) {
            $anomalies[$artifact['error']] = ($anomalies[$artifact['error']] ?? 0) + 1;
            $blocking[] = $artifact['error'];
        }
        $status = $blocking === [] ? 'valid' : 'blocked';

        return $this->report(
            phase: 'validate',
            status: $status,
            shape: $preflight->shape,
            driver: DB::getDriverName(),
            scope: $this->verificationScope(DB::getDriverName()),
            source: $sourceSnapshots,
            shadow: $shadowSnapshots,
            anomalies: $anomalies,
            blockingIssues: $blocking,
            schema: ['fingerprint' => $this->schemaFingerprint()],
            artifact: $artifact['summary'],
            details: [
                'source_immutable' => true,
                'cutover' => 'EXTERNAL_BLOCKED',
                'replay_proof' => $payload['replay_proof'],
            ],
        );
    }

    public function blockedContract(): PermissionModelKeyMigrationReport
    {
        return $this->report(
            phase: 'contract',
            status: 'blocked',
            shape: $this->safeShape(),
            driver: DB::getDriverName(),
            scope: $this->verificationScope(DB::getDriverName()),
            anomalies: ['CONTRACT_EXTERNAL_BLOCKED' => 1],
            blockingIssues: ['CONTRACT_EXTERNAL_BLOCKED'],
            details: ['writes_performed' => false, 'reason' => 'Strict-engine proof and maintenance approval are required.'],
        );
    }

    private function sqliteOnlyBlocked(string $phase): PermissionModelKeyMigrationReport
    {
        return $this->report(
            phase: $phase,
            status: 'blocked',
            shape: $this->safeShape(),
            driver: DB::getDriverName(),
            scope: 'EXTERNAL_BLOCKED',
            anomalies: ['EXTERNAL_ENGINE_BLOCKED' => 1],
            blockingIssues: ['EXTERNAL_ENGINE_BLOCKED'],
            details: ['writes_performed' => false, 'reason' => 'Tasks 1-2 are authorized only for disposable SQLite verification.'],
        );
    }

    private function assertLocalEngine(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('EXTERNAL_ENGINE_BLOCKED');
        }
    }

    /** @param array<string, mixed> $base */
    private function phaseReport(
        PermissionModelKeyMigrationReport $base,
        string $phase,
        ?string $status = null,
        array $extraBlocking = [],
        array $details = [],
        int $examined = 0,
        int $copied = 0,
        int $alreadyPresent = 0,
        bool $dryRun = false,
        bool $writesPerformed = false,
    ): PermissionModelKeyMigrationReport {
        $anomalies = $base->anomalies;
        foreach ($extraBlocking as $code) {
            if (! array_key_exists($code, $anomalies)) {
                $anomalies[$code] = 1;
            }
        }

        return new PermissionModelKeyMigrationReport(
            phase: $phase,
            status: $status ?? $base->status,
            shape: $base->shape,
            verificationScope: $base->verificationScope,
            driver: $base->driver,
            databaseVersion: $base->databaseVersion,
            source: $base->source,
            shadow: $base->shadow,
            anomalies: $anomalies,
            blockingIssues: [...$base->blockingIssues, ...$extraBlocking],
            schema: $base->schema,
            checkpoint: $base->checkpoint,
            artifact: $base->artifact,
            examined: $examined,
            copied: $copied,
            alreadyPresent: $alreadyPresent,
            dryRun: $dryRun,
            writesPerformed: $writesPerformed,
            details: [...$base->details, ...$details],
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $source
     * @param  array<string, array<string, mixed>>  $shadow
     * @param  array<string, int>  $anomalies
     * @param  list<string>  $blockingIssues
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $checkpoint
     * @param  array<string, mixed>  $artifact
     * @param  array<string, mixed>  $details
     */
    private function report(
        string $phase,
        string $status,
        string $shape,
        string $driver,
        string $scope,
        array $source = [],
        array $shadow = [],
        array $anomalies = [],
        array $blockingIssues = [],
        array $schema = [],
        array $checkpoint = [],
        array $artifact = [],
        int $examined = 0,
        int $copied = 0,
        int $alreadyPresent = 0,
        bool $dryRun = false,
        bool $writesPerformed = false,
        array $details = [],
    ): PermissionModelKeyMigrationReport {
        return new PermissionModelKeyMigrationReport(
            phase: $phase,
            status: $status,
            shape: $shape,
            verificationScope: $scope,
            driver: $driver,
            databaseVersion: $this->databaseVersion(),
            source: $source,
            shadow: $shadow,
            anomalies: $anomalies,
            blockingIssues: $blockingIssues,
            schema: $schema,
            checkpoint: $checkpoint,
            artifact: $artifact,
            examined: $examined,
            copied: $copied,
            alreadyPresent: $alreadyPresent,
            dryRun: $dryRun,
            writesPerformed: $writesPerformed,
            details: $details,
        );
    }

    /** @return list<string> */
    private function missingRequiredTables(): array
    {
        $required = [
            'users',
            'permissions',
            'roles',
            'role_has_permissions',
            self::SOURCE_PERMISSION,
            self::SOURCE_ROLE,
        ];

        return array_values(array_filter($required, static fn (string $table): bool => ! Schema::hasTable($table)));
    }

    private function configurationDrift(): bool
    {
        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);

        return ! is_array($tableNames)
            || ! is_array($columnNames)
            || ($tableNames['permissions'] ?? null) !== 'permissions'
            || ($tableNames['roles'] ?? null) !== 'roles'
            || ($tableNames['model_has_permissions'] ?? null) !== self::SOURCE_PERMISSION
            || ($tableNames['model_has_roles'] ?? null) !== self::SOURCE_ROLE
            || ($tableNames['role_has_permissions'] ?? null) !== 'role_has_permissions'
            || ($columnNames['model_morph_key'] ?? 'model_id') !== 'model_id'
            || (($columnNames['permission_pivot_key'] ?? null) !== null && ($columnNames['permission_pivot_key'] ?? null) !== 'permission_id')
            || (($columnNames['role_pivot_key'] ?? null) !== null && ($columnNames['role_pivot_key'] ?? null) !== 'role_id');
    }

    /** @return list<string> */
    private function sourceSchemaIssues(): array
    {
        $issues = [];

        if (! Schema::hasColumn('users', 'id')
            || ! $this->isUuidColumnExact('users', 'id')
            || ! $this->hasPrimaryKey('users', ['id'])) {
            $issues[] = 'USER_UUID_SCHEMA_DRIFT';
        }

        foreach ([self::SOURCE_PERMISSION => 'permission_id', self::SOURCE_ROLE => 'role_id'] as $table => $pivot) {
            $columns = Schema::getColumnListing($table);
            $expectedColumns = [$pivot, 'model_type', 'model_id'];
            if (array_diff($expectedColumns, $columns) !== [] || array_diff($columns, $expectedColumns) !== []) {
                $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
            }
            if (Schema::hasColumn($table, 'team_id')) {
                $issues[] = 'TEAMS_SHAPE_UNSUPPORTED';
            }
            if (Schema::hasColumn($table, 'model_type')
                && ! $this->sameColumnContract($table, 'model_type', self::SOURCE_PERMISSION, 'model_type')) {
                $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
            }
            $descriptor = $this->tableDescriptor($table);
            if (($descriptor['introspection']['foreign_keys'] ?? false) !== true) {
                $issues[] = 'FOREIGN_KEY_INTROSPECTION_UNAVAILABLE';
            }
            $lookupName = $table === self::SOURCE_PERMISSION
                ? 'model_has_permissions_model_id_model_type_index'
                : 'model_has_roles_model_id_model_type_index';
            $primaryName = $table === self::SOURCE_PERMISSION
                ? 'model_has_permissions_permission_model_type_primary'
                : 'model_has_roles_role_model_type_primary';
            $primaryColumns = [$pivot, 'model_id', 'model_type'];
            $lookupMatches = false;
            $primaryMatches = false;
            foreach ($descriptor['indexes'] ?? [] as $index) {
                $indexColumns = array_values(array_map('strval', $index['columns'] ?? []));
                if (($index['name'] ?? null) === $lookupName
                    && $indexColumns === ['model_id', 'model_type']
                    && ($index['unique'] ?? false) === false) {
                    $lookupMatches = true;
                }
                if (($index['name'] ?? null) === $primaryName
                    && $indexColumns === $primaryColumns
                    && ($index['primary'] ?? false) === true) {
                    $primaryMatches = true;
                }
                if (DB::getDriverName() === 'sqlite'
                    && ($index['primary'] ?? false) === true
                    && $indexColumns === $primaryColumns
                    && ($index['unique'] ?? true) === true) {
                    $primaryMatches = true;
                }
            }
            $parent = $table === self::SOURCE_PERMISSION ? 'permissions' : 'roles';
            $foreignKeyMatches = false;
            foreach ($descriptor['foreign_keys'] ?? [] as $foreignKey) {
                if (($foreignKey['columns'] ?? []) === [$pivot]
                    && ($foreignKey['foreign_table'] ?? null) === $parent
                    && ($foreignKey['foreign_columns'] ?? []) === ['id']
                    && strtolower((string) ($foreignKey['on_delete'] ?? '')) === 'cascade') {
                    $foreignKeyMatches = true;
                }
            }
            if (! $lookupMatches || ! $primaryMatches || ! $foreignKeyMatches
                || ! $this->sameColumnContract($table, $pivot, $parent, 'id')) {
                $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
            }
        }
        foreach (['permissions' => 'id', 'roles' => 'id'] as $table => $column) {
            if (! Schema::hasColumn($table, $column)
                || ! $this->isExactRolePermissionParent($table, $column)) {
                $issues[] = 'ROLE_PERMISSION_KEY_TYPE_DRIFT';
            }
        }
        $rolePermissionColumns = Schema::getColumnListing('role_has_permissions');
        if ($rolePermissionColumns !== ['permission_id', 'role_id']
            && array_values(array_diff($rolePermissionColumns, ['permission_id', 'role_id'])) !== []) {
            $issues[] = 'ROLE_PERMISSION_SCHEMA_DRIFT';
        }
        if (! $this->sameColumnContract('role_has_permissions', 'permission_id', 'permissions', 'id')
            || ! $this->sameColumnContract('role_has_permissions', 'role_id', 'roles', 'id')
            || ! $this->hasPrimaryKey('role_has_permissions', ['permission_id', 'role_id'])
            || ! $this->hasForeignKey('role_has_permissions', ['permission_id'], 'permissions', ['id'], 'cascade')
            || ! $this->hasForeignKey('role_has_permissions', ['role_id'], 'roles', ['id'], 'cascade')
            || Schema::hasColumn('role_has_permissions', 'team_id')) {
            $issues[] = 'ROLE_PERMISSION_SCHEMA_DRIFT';
        }

        return array_values(array_unique($issues));
    }

    private function isUuidColumnExact(string $table, string $column): bool
    {
        if (! Schema::hasColumn($table, $column)) {
            return false;
        }

        $contract = $this->columnContract($table, $column);
        $type = strtolower((string) ($contract['type_name'] ?? $contract['type'] ?? ''));
        $length = $contract['length'] ?? null;
        if (($contract['nullable'] ?? true) !== false) {
            return false;
        }
        if (DB::getDriverName() === 'pgsql') {
            return $type === 'uuid';
        }
        if (DB::getDriverName() === 'mysql') {
            return in_array($type, ['char', 'varchar'], true) && (int) $length === 36;
        }

        // SQLite only exposes an exact UUID width when the declared engine DDL
        // retains varchar(36); Blueprint's unconstrained varchar fails closed.
        return in_array($type, ['char', 'varchar'], true) && (int) $length === 36;
    }

    private function isExactRolePermissionParent(string $table, string $column): bool
    {
        $contract = $this->columnContract($table, $column);
        if (($contract['nullable'] ?? true) !== false
            || ($contract['auto_increment'] ?? false) !== true
            || ! $this->hasPrimaryKey($table, [$column])) {
            return false;
        }
        $storage = $this->integerStorageType($contract);

        return match (DB::getDriverName()) {
            'mysql' => $storage === 'bigint' && ($contract['unsigned'] ?? false) === true,
            'pgsql' => $storage === 'bigint' && ($contract['unsigned'] ?? false) === false,
            'sqlite' => $storage === 'integer',
            default => false,
        };
    }

    private function hasPrimaryKey(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        foreach ($this->tableDescriptor($table)['indexes'] ?? [] as $index) {
            if (($index['primary'] ?? false) === true
                && array_values($index['columns'] ?? []) === array_values($columns)) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKey(string $table, array $columns, string $parent, array $parentColumns, string $onDelete): bool
    {
        foreach ($this->tableDescriptor($table)['foreign_keys'] ?? [] as $foreignKey) {
            if (array_values($foreignKey['columns'] ?? []) === array_values($columns)
                && ($foreignKey['foreign_table'] ?? null) === $parent
                && array_values($foreignKey['foreign_columns'] ?? []) === array_values($parentColumns)
                && strtolower((string) ($foreignKey['on_delete'] ?? '')) === strtolower($onDelete)) {
                return true;
            }
        }

        return false;
    }

    private function sameColumnContract(string $leftTable, string $leftColumn, string $rightTable, string $rightColumn): bool
    {
        if (! Schema::hasColumn($leftTable, $leftColumn) || ! Schema::hasColumn($rightTable, $rightColumn)) {
            return false;
        }
        $left = $this->columnContract($leftTable, $leftColumn);
        $right = $this->columnContract($rightTable, $rightColumn);
        $leftType = strtolower((string) ($left['type'] ?? ''));
        $rightType = strtolower((string) ($right['type'] ?? ''));
        if ($this->isIntegerType($leftType) && $this->isIntegerType($rightType)) {
            if (DB::getDriverName() === 'sqlite') {
                return ($left['nullable'] ?? null) === ($right['nullable'] ?? null);
            }

            return $this->integerStorageType($left) === $this->integerStorageType($right)
                && ($left['unsigned'] ?? null) === ($right['unsigned'] ?? null)
                && ($left['nullable'] ?? null) === ($right['nullable'] ?? null);
        }

        return $leftType === $rightType
            && ($left['length'] ?? null) === ($right['length'] ?? null)
            && ($left['collation'] ?? null) === ($right['collation'] ?? null)
            && ($left['nullable'] ?? null) === ($right['nullable'] ?? null);
    }

    /** @param array<string, mixed> $column */
    private function integerStorageType(array $column): string
    {
        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));

        return trim(str_replace([' unsigned', ' signed'], '', $type));
    }

    /** @return array<string, mixed> */
    private function columnContract(string $table, string $column): array
    {
        foreach ($this->tableDescriptor($table)['columns'] ?? [] as $descriptor) {
            if (($descriptor['name'] ?? null) === $column) {
                return $descriptor;
            }
        }

        return [];
    }

    /** @return list<string> */
    private function cutoverStateIssues(): array
    {
        $issues = [];
        $permissionShadow = Schema::hasTable(self::SHADOW_PERMISSION);
        $roleShadow = Schema::hasTable(self::SHADOW_ROLE);
        $permissionBackup = Schema::hasTable(self::BACKUP_PERMISSION);
        $roleBackup = Schema::hasTable(self::BACKUP_ROLE);
        $ownership = Schema::hasTable(self::OWNERSHIP);

        if ($permissionShadow xor $roleShadow) {
            $issues[] = 'PARTIAL_CUTOVER_STATE';
        }
        if ($permissionBackup xor $roleBackup) {
            $issues[] = 'PARTIAL_CUTOVER_STATE';
        }
        if ($permissionBackup || $roleBackup) {
            $issues[] = 'PARTIAL_CUTOVER_STATE';
        }
        if (($permissionShadow && $roleShadow) && $this->shadowSchemaIssues() !== []) {
            $issues[] = 'SHADOW_SCHEMA_DRIFT';
        }
        if ($ownership && (! $permissionShadow || ! $roleShadow || ! $this->migrationOwnsShadows())) {
            $issues[] = 'SHADOW_OWNERSHIP_DRIFT';
        }

        return array_values(array_unique($issues));
    }

    /** @param array<string, array{has_rows: bool, has_invalid: bool}> $source */
    private function classifyShape(array $source, array $schemaIssues, array $cutoverIssues): string
    {
        if ($schemaIssues !== [] || $cutoverIssues !== []) {
            return self::SHAPE_MIXED;
        }
        $hasInvalid = false;
        $hasRows = false;
        foreach ($source as $summary) {
            $hasRows = $hasRows || (bool) ($summary['has_rows'] ?? false);
            $hasInvalid = $hasInvalid || (bool) ($summary['has_invalid'] ?? false);
        }

        $permissionType = Schema::getColumnType(self::SOURCE_PERMISSION, 'model_id');
        $roleType = Schema::getColumnType(self::SOURCE_ROLE, 'model_id');
        $numeric = $this->isIntegerType($permissionType) && $this->isIntegerType($roleType);
        $uuidCompatible = $this->isUuidType($permissionType) && $this->isUuidType($roleType);
        if (! $hasRows && $numeric) {
            return self::SHAPE_EMPTY_LEGACY;
        }
        if ($hasInvalid) {
            return self::SHAPE_LEGACY_UNRESOLVED;
        }
        if ($numeric && DB::getDriverName() === 'sqlite') {
            return self::SHAPE_SQLITE_UUID_TEXT;
        }
        if ($uuidCompatible) {
            return self::SHAPE_CANONICAL;
        }

        return self::SHAPE_MIXED;
    }

    /**
     * Return only the bounded shape facts needed by classifyShape.
     *
     * The source assignment tables can be arbitrarily large. Keep this
     * cursor-based and select only model_id; never materialize complete rows
     * just to decide whether the shape is UUID-compatible.
     *
     * @return array<string, array{has_rows: bool, has_invalid: bool}>
     */
    private function sourceRows(): array
    {
        $summary = [];
        foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
            $hasRows = false;
            $hasInvalid = false;
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'model_id')) {
                foreach (DB::table($table)->select(['model_id'])->cursor() as $row) {
                    $hasRows = true;
                    if (! $this->isUuid((string) ($row->model_id ?? ''))) {
                        $hasInvalid = true;

                        break;
                    }
                }
            }
            $summary[$table] = [
                'has_rows' => $hasRows,
                'has_invalid' => $hasInvalid,
            ];
        }

        return $summary;
    }

    /** @param array<string, list<object>>|null $source */
    private function sourceSnapshots(?array $source = null): array
    {
        if ($source === null) {
            $permission = $this->streamSnapshot(self::SOURCE_PERMISSION, 'permission_id');
            $role = $this->streamSnapshot(self::SOURCE_ROLE, 'role_id');

            return [
                self::SOURCE_PERMISSION => $permission,
                self::SOURCE_ROLE => $role,
                'aggregate_hash' => hash('sha256', self::canonicalJson([
                    self::SOURCE_PERMISSION => $permission,
                    self::SOURCE_ROLE => $role,
                ])),
            ];
        }

        $permission = $this->snapshot($source[self::SOURCE_PERMISSION] ?? [], 'permission_id');
        $role = $this->snapshot($source[self::SOURCE_ROLE] ?? [], 'role_id');

        return [
            self::SOURCE_PERMISSION => $permission,
            self::SOURCE_ROLE => $role,
            'aggregate_hash' => hash('sha256', self::canonicalJson([
                self::SOURCE_PERMISSION => $permission,
                self::SOURCE_ROLE => $role,
            ])),
        ];
    }

    /** @return array{count:int,hash:string} */
    private function streamSnapshot(string $table, string $pivot): array
    {
        if (! Schema::hasTable($table)) {
            return ['count' => 0, 'hash' => hash('sha256', self::canonicalJson([]))];
        }

        $context = hash_init('sha256');
        $count = 0;
        foreach ($this->streamRows($table, $pivot) as $row) {
            $tuple = [
                $pivot => (string) ($row->{$pivot} ?? ''),
                'model_type' => (string) ($row->model_type ?? ''),
                'model_id' => (string) ($row->model_id ?? ''),
            ];
            hash_update($context, self::canonicalJson($tuple)."\n");
            $count++;
        }

        return ['count' => $count, 'hash' => hash_final($context)];
    }

    /** @return iterable<object> */
    private function streamRows(string $table, string $pivot): iterable
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        yield from DB::table($table)
            ->orderBy($pivot)
            ->orderBy('model_type')
            ->orderBy('model_id')
            ->cursor();
    }

    /** @param list<object> $rows @return array{count:int,hash:string} */
    private function snapshot(array $rows, string $pivot): array
    {
        $tuples = array_map(static fn (object $row): array => [
            $pivot => (string) ($row->{$pivot} ?? ''),
            'model_type' => (string) ($row->model_type ?? ''),
            'model_id' => (string) ($row->model_id ?? ''),
        ], $rows);
        usort($tuples, static function (array $left, array $right) use ($pivot): int {
            foreach ([$pivot, 'model_type', 'model_id'] as $column) {
                $comparison = strcmp((string) ($left[$column] ?? ''), (string) ($right[$column] ?? ''));
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        $context = hash_init('sha256');
        foreach ($tuples as $tuple) {
            hash_update($context, self::canonicalJson($tuple)."\n");
        }

        return [
            'count' => count($tuples),
            'hash' => hash_final($context),
        ];
    }

    /** @return array<string, int> */
    private function scanSourceAnomaliesStreaming(): array
    {
        $anomalies = [];
        foreach ([self::SOURCE_PERMISSION => 'permission_id', self::SOURCE_ROLE => 'role_id'] as $table => $pivot) {
            try {
                $duplicateQuery = DB::table($table)
                    ->select($pivot, 'model_type', 'model_id')
                    ->groupBy($pivot, 'model_type', 'model_id')
                    ->havingRaw('COUNT(*) > 1');
                $duplicateCount = (int) DB::query()->fromSub($duplicateQuery, 'duplicate_assignments')->count();
                if ($duplicateCount > 0) {
                    $anomalies['DUPLICATE_ASSIGNMENT'] = ($anomalies['DUPLICATE_ASSIGNMENT'] ?? 0) + $duplicateCount;
                }
            } catch (Throwable) {
                // A source primary key normally makes duplicates impossible;
                // inability to prove that remains a stable blocking anomaly.
                $anomalies['DUPLICATE_ASSIGNMENT_SCAN_UNAVAILABLE'] = ($anomalies['DUPLICATE_ASSIGNMENT_SCAN_UNAVAILABLE'] ?? 0) + 1;
            }

            foreach ($this->streamRows($table, $pivot) as $row) {
                foreach ($this->rowIssues($row, $table) as $code) {
                    $anomalies[$code] = ($anomalies[$code] ?? 0) + 1;
                }
            }
        }

        return $anomalies;
    }

    /** @return array{missing:int,extra:int} */
    private function compareTupleStreams(string $sourceTable, string $shadowTable): array
    {
        $pivot = $sourceTable === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
        $source = $this->streamRows($sourceTable, $pivot);
        $shadow = $this->streamRows($shadowTable, $pivot);
        $source->rewind();
        $shadow->rewind();
        $missing = 0;
        $extra = 0;
        while ($source->valid() || $shadow->valid()) {
            if (! $source->valid()) {
                $extra++;
                $shadow->next();

                continue;
            }
            if (! $shadow->valid()) {
                $missing++;
                $source->next();

                continue;
            }
            $comparison = self::compareRows($source->current(), $shadow->current(), $sourceTable);
            if ($comparison < 0) {
                $missing++;
                $source->next();

                continue;
            }
            if ($comparison > 0) {
                $extra++;
                $shadow->next();

                continue;
            }
            $source->next();
            $shadow->next();
        }

        return ['missing' => $missing, 'extra' => $extra];
    }

    /** @return array{measured:bool,passes:int,rows_changed:int,error:?string} */
    private function measureReplay(): array
    {
        $passes = 0;
        $rowsChanged = 0;
        $baselineLevel = DB::transactionLevel();

        try {
            for ($pass = 0; $pass < 2; $pass++) {
                $startingLevel = DB::transactionLevel();
                DB::beginTransaction();
                try {
                    foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
                        $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
                        $shadow = $table === self::SOURCE_PERMISSION ? self::SHADOW_PERMISSION : self::SHADOW_ROLE;
                        foreach ($this->streamRows($table, $pivot) as $row) {
                            $rowsChanged += DB::table($shadow)->insertOrIgnore([
                                $pivot => (string) $row->{$pivot},
                                'model_type' => (string) $row->model_type,
                                'model_id' => (string) $row->model_id,
                            ]);
                        }
                    }
                    $passes++;
                } finally {
                    DB::rollBack($startingLevel);
                }
            }
        } catch (Throwable) {
            if (DB::transactionLevel() > $baselineLevel) {
                DB::rollBack($baselineLevel);
            }

            return [
                'measured' => false,
                'passes' => $passes,
                'rows_changed' => $rowsChanged,
                'error' => 'REPLAY_PROOF_UNAVAILABLE',
            ];
        }

        return [
            'measured' => true,
            'passes' => $passes,
            'rows_changed' => $rowsChanged,
            'error' => null,
        ];
    }

    /** @return list<string> */
    private function rowIssues(object $row, string $table): array
    {
        $issues = [];
        $modelType = (string) ($row->model_type ?? '');
        $modelId = (string) ($row->model_id ?? '');
        if ($modelType === '') {
            $issues[] = 'BLANK_MODEL_TYPE';
        }
        if ($modelType !== 'user') {
            $issues[] = 'NON_CANONICAL_MORPH_TYPE';
        }
        if (! $this->isUuid($modelId)) {
            $issues[] = 'NON_UUID_MODEL_ID';
        } elseif ($modelType === 'user' && ! DB::table('users')->where('id', $modelId)->exists()) {
            $issues[] = 'ORPHAN_CANONICAL_USER';
        }

        $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
        $parent = $table === self::SOURCE_PERMISSION ? 'permissions' : 'roles';
        $pivotValue = (string) ($row->{$pivot} ?? '');
        if (filter_var($pivotValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            || ! DB::table($parent)->where('id', (int) $pivotValue)->exists()) {
            $issues[] = $table === self::SOURCE_PERMISSION ? 'ORPHAN_PERMISSION' : 'ORPHAN_ROLE';
        }

        return array_values(array_unique($issues));
    }

    /** @return array<string, array<string, mixed>> */
    private function shadowSnapshots(?array $shadow = null): array
    {
        if ($shadow === null) {
            $permission = $this->streamSnapshot(self::SHADOW_PERMISSION, 'permission_id');
            $role = $this->streamSnapshot(self::SHADOW_ROLE, 'role_id');

            return [
                self::SHADOW_PERMISSION => $permission,
                self::SHADOW_ROLE => $role,
            ];
        }

        $permission = $this->snapshot($shadow[self::SHADOW_PERMISSION] ?? [], 'permission_id');
        $role = $this->snapshot($shadow[self::SHADOW_ROLE] ?? [], 'role_id');

        return [
            self::SHADOW_PERMISSION => $permission,
            self::SHADOW_ROLE => $role,
        ];
    }

    /**
     * Select one bounded batch directly from a sorted cursor.  The source is
     * never materialized or sorted in PHP; only the selected rows are kept.
     *
     * @return array{0:list<object>,1:?string,2:?string}
     */
    private function selectBatchStreaming(string $table, ?string $cursor, int $limit): array
    {
        if ($limit < 1) {
            return [[], $cursor, null];
        }

        $cursorFound = $cursor === null;
        $selected = [];
        $last = null;
        foreach ($this->streamRows($table, $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id') as $row) {
            $hash = hash('sha256', self::cursorKey($row, $table));
            if (! $cursorFound) {
                if (hash_equals($hash, $cursor)) {
                    $cursorFound = true;
                }

                continue;
            }
            if (count($selected) >= $limit) {
                break;
            }
            $selected[] = $row;
            $last = $row;
        }
        if (! $cursorFound) {
            return [[], null, 'CHECKPOINT_CURSOR_STALE'];
        }

        $next = $last === null ? $cursor : hash('sha256', self::cursorKey($last, $table));

        return [$selected, $next, null];
    }

    /** @param array<string, ?string> $cursors */
    private function hasMoreStreaming(array $cursors): bool
    {
        foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
            $cursor = $cursors[$table] ?? null;
            $found = $cursor === null;
            foreach ($this->streamRows($table, $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id') as $row) {
                if (! $found) {
                    $found = hash_equals(hash('sha256', self::cursorKey($row, $table)), $cursor);

                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private static function cursorKey(object $row, string $table): string
    {
        $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';

        return self::canonicalJson([
            'table' => $table,
            $pivot => (string) ($row->{$pivot} ?? ''),
            'model_type' => (string) ($row->model_type ?? ''),
            'model_id' => (string) ($row->model_id ?? ''),
        ]);
    }

    private static function compareRows(object $left, object $right, string $table): int
    {
        $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
        foreach ([$pivot, 'model_type', 'model_id'] as $column) {
            $comparison = strcmp((string) ($left->{$column} ?? ''), (string) ($right->{$column} ?? ''));
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    /** @return array{payload:?array<string,mixed>,error:?string} */
    private function readCheckpoint(?string $path): array
    {
        if ($path === null || trim($path) === '' || ! is_file($path) || filesize($path) === 0) {
            return ['payload' => null, 'error' => null];
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['payload' => null, 'error' => 'CHECKPOINT_TAMPERED'];
        }
        if (! is_array($decoded) || $this->containsUuid($decoded)) {
            return ['payload' => null, 'error' => 'CHECKPOINT_TAMPERED'];
        }

        return ['payload' => $decoded, 'error' => null];
    }

    /** @param array<string, mixed> $checkpoint @param array<string, mixed> $snapshots */
    private function validateCheckpoint(array $checkpoint, int $batchSize, array $snapshots, string $schemaFingerprint): ?string
    {
        $requiredKeys = ['command_version', 'phase', 'driver', 'database_version', 'cursor_version', 'batch_size', 'batch_sequence', 'source', 'schema_fingerprint', 'source_schema_fingerprint', 'config_fingerprint', 'cursor_schema', 'cursors', 'rows_examined', 'rows_copied', 'rows_already_present', 'has_more', 'anomaly_counts', 'previous_chain_sha256', 'batch_payload_sha256', 'chain_sha256', 'checkpoint_sha256'];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $checkpoint)) {
                return 'CHECKPOINT_TAMPERED';
            }
        }
        $actualKeys = array_keys($checkpoint);
        sort($actualKeys);
        sort($requiredKeys);
        if ($actualKeys !== $requiredKeys) {
            return 'CHECKPOINT_TAMPERED';
        }
        if (($checkpoint['command_version'] ?? null) !== self::COMMAND_VERSION
            || ($checkpoint['phase'] ?? null) !== 'backfill'
            || ($checkpoint['cursor_version'] ?? null) !== 1
            || ($checkpoint['driver'] ?? null) !== DB::getDriverName()
            || ($checkpoint['database_version'] ?? null) !== $this->databaseVersion()
            || ($checkpoint['batch_size'] ?? null) !== $batchSize
            || ! is_int($checkpoint['batch_sequence'] ?? null)
            || $checkpoint['batch_sequence'] < 1
            || ($checkpoint['source'] ?? null) !== $snapshots
            || ($checkpoint['schema_fingerprint'] ?? null) !== $schemaFingerprint
            || ($checkpoint['source_schema_fingerprint'] ?? null) !== $this->sourceSchemaFingerprint()
            || ($checkpoint['config_fingerprint'] ?? null) !== $this->configurationFingerprint()
            || ($checkpoint['cursor_schema'] ?? null) !== $this->cursorSchema()
            || ! is_bool($checkpoint['has_more'] ?? null)) {
            return 'SOURCE_CHANGED_SINCE_CHECKPOINT';
        }
        $cursors = $checkpoint['cursors'] ?? null;
        if (! is_array($cursors)
            || array_keys($cursors) !== [self::SOURCE_PERMISSION, self::SOURCE_ROLE]) {
            return 'CHECKPOINT_TAMPERED';
        }
        foreach ($cursors as $cursor) {
            if ($cursor !== null
                && (! is_string($cursor) || preg_match('/^[a-f0-9]{64}$/D', $cursor) !== 1)) {
                return 'CHECKPOINT_TAMPERED';
            }
        }
        foreach (['rows_examined', 'rows_copied', 'rows_already_present'] as $counter) {
            if (! is_int($checkpoint[$counter] ?? null) || $checkpoint[$counter] < 0) {
                return 'CHECKPOINT_TAMPERED';
            }
        }
        if ($checkpoint['rows_examined'] > $batchSize
            || $checkpoint['rows_examined'] !== $checkpoint['rows_copied'] + $checkpoint['rows_already_present']) {
            return 'CHECKPOINT_TAMPERED';
        }
        $anomalyCounts = $checkpoint['anomaly_counts'] ?? null;
        if (! is_array($anomalyCounts)) {
            return 'CHECKPOINT_TAMPERED';
        }
        foreach ($anomalyCounts as $code => $count) {
            if (! is_string($code) || $code === '' || ! is_int($count) || $count < 0) {
                return 'CHECKPOINT_TAMPERED';
            }
        }
        if (($checkpoint['checkpoint_sha256'] ?? null) !== $this->checkpointChecksum($checkpoint)
            || ! is_string($checkpoint['chain_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['chain_sha256']) !== 1
            || ! is_string($checkpoint['previous_chain_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['previous_chain_sha256']) !== 1
            || ! is_string($checkpoint['batch_payload_sha256'] ?? null)
            || ! hash_equals((string) $checkpoint['batch_payload_sha256'], $this->batchPayloadChecksum($checkpoint))
            || ! hash_equals((string) $checkpoint['chain_sha256'], $this->chainChecksum($checkpoint))) {
            return 'CHECKPOINT_TAMPERED';
        }
        $genesis = hash('sha256', self::COMMAND_VERSION.'|genesis');
        if (($checkpoint['batch_sequence'] === 1 && ! hash_equals($genesis, $checkpoint['previous_chain_sha256']))
            || ($checkpoint['batch_sequence'] > 1 && hash_equals($genesis, $checkpoint['previous_chain_sha256']))) {
            return 'CHECKPOINT_TAMPERED';
        }
        foreach ($cursors as $table => $cursor) {
            if ($cursor !== null && ! $this->cursorExistsStreaming($table, $cursor)) {
                return 'CHECKPOINT_CURSOR_STALE';
            }
        }
        if ($checkpoint['has_more'] !== $this->hasMoreStreaming($cursors)) {
            return 'CHECKPOINT_TAMPERED';
        }

        return null;
    }

    private function cursorExistsStreaming(string $table, string $cursor): bool
    {
        $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
        foreach ($this->streamRows($table, $pivot) as $row) {
            if (hash_equals(hash('sha256', self::cursorKey($row, $table)), $cursor)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $checkpoint @return array<string, mixed> */
    private function checkpointSummary(array $checkpoint): array
    {
        return [
            'batch_sequence' => (int) ($checkpoint['batch_sequence'] ?? 0),
            'rows_examined' => (int) ($checkpoint['rows_examined'] ?? 0),
            'rows_copied' => (int) ($checkpoint['rows_copied'] ?? 0),
            'rows_already_present' => (int) ($checkpoint['rows_already_present'] ?? 0),
            'has_more' => (bool) ($checkpoint['has_more'] ?? false),
            'anomaly_counts' => is_array($checkpoint['anomaly_counts'] ?? null) ? $checkpoint['anomaly_counts'] : [],
            'chain_sha256' => (string) ($checkpoint['chain_sha256'] ?? ''),
            'checkpoint_sha256' => (string) ($checkpoint['checkpoint_sha256'] ?? ''),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function cursorSchema(): array
    {
        $schema = [];
        foreach ([self::SOURCE_PERMISSION => 'permission_id', self::SOURCE_ROLE => 'role_id'] as $table => $pivot) {
            $schema[$table] = [
                'table' => $table,
                'key' => [$pivot, 'model_type', 'model_id'],
                'types' => [
                    $pivot => $this->columnContract($table, $pivot),
                    'model_type' => $this->columnContract($table, 'model_type'),
                    'model_id' => $this->columnContract($table, 'model_id'),
                ],
            ];
        }

        return $schema;
    }

    /** @param array<string, mixed> $checkpoint */
    private function chainChecksum(array $checkpoint): string
    {
        $previous = (string) ($checkpoint['previous_chain_sha256'] ?? '');
        $batch = $this->batchPayloadChecksum($checkpoint);

        return hash('sha256', $previous.'|'.$batch);
    }

    /** @param array<string, mixed> $checkpoint */
    private function batchPayloadChecksum(array $checkpoint): string
    {
        $payload = $checkpoint;
        unset($payload['batch_payload_sha256'], $payload['chain_sha256'], $payload['checkpoint_sha256']);

        return hash('sha256', self::canonicalJson($payload));
    }

    /** @param array<string, mixed> $checkpoint */
    private function checkpointChecksum(array $checkpoint): string
    {
        unset($checkpoint['checkpoint_sha256']);

        return hash('sha256', self::canonicalJson($checkpoint));
    }

    /** @param array<string, mixed> $payload */
    private function writeJsonAtomic(string $path, array $payload): bool
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            return false;
        }
        $temporary = tempnam($directory, '.dovra-permission-key-');
        if ($temporary === false) {
            return false;
        }
        try {
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json, LOCK_EX) === false || ! rename($temporary, $path)) {
                @unlink($temporary);

                return false;
            }

            return true;
        } catch (Throwable) {
            @unlink($temporary);

            return false;
        }
    }

    /** @param array<string, mixed> $payload @return array{summary:array<string,mixed>,error:?string} */
    private function persistOrVerifyArtifact(?string $path, array $payload): array
    {
        $canonical = self::canonicalJson($payload);
        $checksum = hash('sha256', $canonical);
        $summary = ['sha256' => $checksum, 'written' => false];
        if ($path === null || trim($path) === '') {
            return ['summary' => $summary, 'error' => null];
        }
        if (is_file($path) && filesize($path) > 0) {
            try {
                $existing = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return ['summary' => $summary, 'error' => 'VALIDATION_ARTIFACT_TAMPERED'];
            }
            if (! is_array($existing) || $this->containsUuid($existing)
                || ($existing['artifact_sha256'] ?? null) !== hash('sha256', self::canonicalJson($existing, ['artifact_sha256']))) {
                return ['summary' => $summary, 'error' => 'VALIDATION_ARTIFACT_TAMPERED'];
            }
            $existingPayload = $existing;
            unset($existingPayload['artifact_sha256']);
            $expiresAt = is_string($existingPayload['expires_at'] ?? null) ? $existingPayload['expires_at'] : '';
            if ($expiresAt === '' || strtotime($expiresAt) === false || strtotime($expiresAt) <= time()) {
                return ['summary' => $summary, 'error' => 'VALIDATION_ARTIFACT_EXPIRED'];
            }
            $payload['expires_at'] = $expiresAt;
            if ($existingPayload !== $payload) {
                return ['summary' => $summary, 'error' => 'VALIDATION_ARTIFACT_STALE'];
            }
            $summary['written'] = true;

            return ['summary' => $summary, 'error' => null];
        }
        $artifact = [...$payload, 'artifact_sha256' => $checksum];
        if (! $this->writeJsonAtomic($path, $artifact)) {
            return ['summary' => $summary, 'error' => 'VALIDATION_ARTIFACT_WRITE_FAILED'];
        }
        $summary['written'] = true;

        return ['summary' => $summary, 'error' => null];
    }

    /** @return array{head:?string,tree:?string,error:?string} */
    private function resolveGitIdentity(?string $expectedHead, ?string $expectedTree): array
    {
        $actualHead = $this->gitIdentity('rev-parse HEAD');
        $actualTree = $this->gitIdentity('write-tree');
        if ($actualHead === null || $actualTree === null) {
            return ['head' => null, 'tree' => null, 'error' => 'HEAD_TREE_IDENTITY_UNAVAILABLE'];
        }
        $head = trim((string) ($expectedHead ?? (getenv('DOVRA_GIT_HEAD') !== false ? getenv('DOVRA_GIT_HEAD') : $actualHead)));
        $tree = trim((string) ($expectedTree ?? (getenv('DOVRA_GIT_TREE') !== false ? getenv('DOVRA_GIT_TREE') : $actualTree)));

        if ($head === '' || $tree === '') {
            return ['head' => $head !== '' ? $head : null, 'tree' => $tree !== '' ? $tree : null, 'error' => 'HEAD_TREE_IDENTITY_MISSING'];
        }
        if (preg_match('/^[a-f0-9]{40,64}$/i', $head) !== 1 || preg_match('/^[a-f0-9]{40,64}$/i', $tree) !== 1) {
            return ['head' => null, 'tree' => null, 'error' => 'HEAD_TREE_IDENTITY_INVALID'];
        }
        if (($actualHead !== null && ! hash_equals(strtolower($actualHead), strtolower($head)))
            || ($actualTree !== null && ! hash_equals(strtolower($actualTree), strtolower($tree)))) {
            return ['head' => $head, 'tree' => $tree, 'error' => 'HEAD_TREE_IDENTITY_MISMATCH'];
        }

        return ['head' => strtolower($head), 'tree' => strtolower($tree), 'error' => null];
    }

    private function gitIdentity(string $command): ?string
    {
        $output = [];
        $status = 1;
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        @exec('git '.$command.' 2>'.$nullDevice, $output, $status);

        return $status === 0 && isset($output[0]) && trim((string) $output[0]) !== ''
            ? trim((string) $output[0])
            : null;
    }

    /** @param array<string, mixed> $value */
    private function containsUuid(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item) && $this->containsUuid($item)) {
                return true;
            }
            if (is_string($item) && $this->isUuid($item)) {
                return true;
            }
        }

        return false;
    }

    private function sourceSchemaFingerprint(): string
    {
        return hash('sha256', self::canonicalJson([
            self::SOURCE_PERMISSION => $this->tableDescriptor(self::SOURCE_PERMISSION),
            self::SOURCE_ROLE => $this->tableDescriptor(self::SOURCE_ROLE),
        ]));
    }

    private function schemaFingerprint(): string
    {
        $tables = [
            'users',
            'permissions',
            'roles',
            'role_has_permissions',
            self::SOURCE_PERMISSION,
            self::SOURCE_ROLE,
            self::SHADOW_PERMISSION,
            self::SHADOW_ROLE,
            self::OWNERSHIP,
        ];
        $descriptors = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $descriptors[$table] = $this->tableDescriptor($table);
            }
        }

        return hash('sha256', self::canonicalJson($descriptors));
    }

    /** @return array<string, mixed> */
    private function schemaPayload(): array
    {
        $tables = [];
        foreach (['users', 'permissions', 'roles', 'role_has_permissions', self::SOURCE_PERMISSION, self::SOURCE_ROLE, self::SHADOW_PERMISSION, self::SHADOW_ROLE, self::OWNERSHIP] as $table) {
            if (Schema::hasTable($table)) {
                $tables[$table] = $this->tableDescriptor($table);
            }
        }

        return ['fingerprint' => $this->schemaFingerprint(), 'tables' => $tables];
    }

    private function tableFingerprint(string $table): string
    {
        return hash('sha256', self::canonicalJson($this->tableDescriptor($table)));
    }

    /** @return array<string, mixed> */
    private function tableDescriptor(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return ['missing' => true];
        }
        $introspection = [
            'columns' => true,
            'indexes' => true,
            'foreign_keys' => true,
        ];
        $columns = [];
        try {
            foreach (Schema::getColumns($table) as $column) {
                $type = strtolower((string) ($column['type'] ?? ''));
                $typeName = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
                $length = isset($column['length']) && $column['length'] !== null
                    ? (int) $column['length']
                    : (preg_match('/\((\d+)\)/', $type, $matches) === 1 ? (int) $matches[1] : null);
                $columns[] = [
                    'name' => (string) ($column['name'] ?? ''),
                    'type' => $type,
                    'type_name' => $typeName,
                    'length' => $length,
                    'collation' => $column['collation'] ?? null,
                    'nullable' => (bool) ($column['nullable'] ?? false),
                    'default' => $column['default'] ?? null,
                    'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                    'unsigned' => (bool) ($column['unsigned'] ?? str_contains($type.' '.$typeName, 'unsigned')),
                ];
            }
        } catch (Throwable) {
            $introspection['columns'] = false;
            foreach (Schema::getColumnListing($table) as $name) {
                $columns[] = [
                    'name' => $name,
                    'type' => strtolower((string) Schema::getColumnType($table, $name)),
                    'type_name' => strtolower((string) Schema::getColumnType($table, $name)),
                    'length' => null,
                    'collation' => null,
                    'nullable' => null,
                    'auto_increment' => false,
                    'unsigned' => null,
                ];
            }
        }
        usort($columns, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));
        $indexes = [];
        try {
            foreach (Schema::getIndexes($table) as $index) {
                $indexes[] = [
                    'name' => (string) ($index['name'] ?? ''),
                    'columns' => array_values(array_map('strval', $index['columns'] ?? [])),
                    'unique' => (bool) ($index['unique'] ?? false),
                    'primary' => (bool) ($index['primary'] ?? false),
                ];
            }
        } catch (Throwable) {
            $introspection['indexes'] = false;
        }
        usort($indexes, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));
        $foreignKeys = [];
        try {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $foreignKeys[] = [
                    'name' => (string) ($foreignKey['name'] ?? ''),
                    'columns' => array_values(array_map('strval', $foreignKey['columns'] ?? [])),
                    'foreign_table' => (string) ($foreignKey['foreign_table'] ?? ''),
                    'foreign_columns' => array_values(array_map('strval', $foreignKey['foreign_columns'] ?? [])),
                    'on_delete' => strtolower((string) ($foreignKey['on_delete'] ?? '')),
                ];
            }
        } catch (Throwable) {
            $introspection['foreign_keys'] = false;
        }
        usort($foreignKeys, static fn (array $left, array $right): int => self::canonicalJson($left) <=> self::canonicalJson($right));

        return [
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'introspection' => $introspection,
        ];
    }

    /** @return list<string> */
    private function shadowSchemaIssues(): array
    {
        $issues = [];
        foreach ([self::SHADOW_PERMISSION => 'permission_id', self::SHADOW_ROLE => 'role_id'] as $table => $pivot) {
            if (! Schema::hasTable($table)) {
                $issues[] = 'SHADOW_SCHEMA_DRIFT';

                continue;
            }
            $columns = Schema::getColumnListing($table);
            $expectedColumns = [$pivot, 'model_type', 'model_id'];
            if (array_diff($expectedColumns, $columns) !== []
                || array_diff($columns, $expectedColumns) !== []
                || ! $this->sameColumnContract(
                    $table,
                    $pivot,
                    $table === self::SHADOW_PERMISSION ? 'permissions' : 'roles',
                    'id',
                )
                || ! $this->isUuidColumnExact($table, 'model_id')
                || ! $this->sameColumnContract($table, 'model_type', $table === self::SHADOW_PERMISSION ? self::SOURCE_PERMISSION : self::SOURCE_ROLE, 'model_type')
                || Schema::hasColumn($table, 'team_id')) {
                $issues[] = 'SHADOW_SCHEMA_DRIFT';
            }
            $descriptor = $this->tableDescriptor($table);
            if (($descriptor['introspection']['foreign_keys'] ?? false) !== true) {
                $issues[] = 'FOREIGN_KEY_INTROSPECTION_UNAVAILABLE';
            }
            $requiredIndex = $table === self::SHADOW_PERMISSION ? 'spatie_mhpu_model_lookup_idx' : 'spatie_mhru_model_lookup_idx';
            $requiredPrimary = $table === self::SHADOW_PERMISSION ? 'spatie_mhpu_assignment_pk' : 'spatie_mhru_assignment_pk';
            $lookupMatches = false;
            $primaryMatches = false;
            foreach ($descriptor['indexes'] ?? [] as $index) {
                $indexColumns = array_values(array_map('strval', $index['columns'] ?? []));
                if (($index['name'] ?? null) === $requiredIndex
                    && $indexColumns === ['model_id', 'model_type']) {
                    $lookupMatches = ($index['unique'] ?? false) === false;
                }
                if (($index['name'] ?? null) === $requiredPrimary
                    && $indexColumns === [$pivot, 'model_id', 'model_type']
                    && ($index['primary'] ?? false) === true
                    && ($index['unique'] ?? false) === true) {
                    $primaryMatches = true;
                }
                // SQLite synthesizes primary index names, so its column shape is authoritative.
                if (DB::getDriverName() === 'sqlite'
                    && ($index['primary'] ?? false) === true
                    && $indexColumns === [$pivot, 'model_id', 'model_type']
                    && ($index['unique'] ?? true) === true) {
                    $primaryMatches = true;
                }
            }
            $parent = $table === self::SHADOW_PERMISSION ? 'permissions' : 'roles';
            $foreignKeyMatches = false;
            foreach ($descriptor['foreign_keys'] ?? [] as $foreignKey) {
                if (($foreignKey['columns'] ?? []) === [$pivot]
                    && ($foreignKey['foreign_table'] ?? null) === $parent
                    && ($foreignKey['foreign_columns'] ?? []) === ['id']
                    && strtolower((string) ($foreignKey['on_delete'] ?? '')) === 'cascade'
                    && (DB::getDriverName() === 'sqlite'
                        || ($foreignKey['name'] ?? null) === ($table === self::SHADOW_PERMISSION ? 'spatie_mhpu_permission_fk' : 'spatie_mhru_role_fk'))) {
                    $foreignKeyMatches = true;
                }
            }
            if (! $lookupMatches || ! $primaryMatches || ! $foreignKeyMatches) {
                $issues[] = 'SHADOW_SCHEMA_DRIFT';
            }
        }

        return array_values(array_unique($issues));
    }

    /** @return list<string> */
    private function foreignKeyIssues(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }
        try {
            $rows = DB::select('PRAGMA foreign_key_check');

            return array_map(static fn (object $row): string => hash('sha256', self::canonicalJson(get_object_vars($row))), $rows);
        } catch (Throwable) {
            return ['FOREIGN_KEY_INTROSPECTION_UNAVAILABLE'];
        }
    }

    private function safeShape(): string
    {
        try {
            return $this->preflight()->shape;
        } catch (Throwable) {
            return self::SHAPE_MIXED;
        }
    }

    private function configurationFingerprint(): string
    {
        return hash('sha256', self::canonicalJson([
            'teams' => (bool) config('permission.teams', false),
            'table_names' => config('permission.table_names', []),
            'column_names' => config('permission.column_names', []),
        ]));
    }

    private function verificationScope(string $driver): string
    {
        return $driver === 'sqlite' ? 'LOCALLY_VERIFIED_SQLITE' : 'EXTERNAL_BLOCKED';
    }

    private function databaseVersion(): string
    {
        try {
            return match (DB::getDriverName()) {
                'sqlite' => (string) (DB::selectOne('select sqlite_version() as version')->version ?? ''),
                'mysql' => (string) (DB::selectOne('select version() as version')->version ?? ''),
                'pgsql' => (string) (DB::selectOne('select version() as version')->version ?? ''),
                default => '',
            };
        } catch (Throwable) {
            return '';
        }
    }

    private function isIntegerType(string $type): bool
    {
        $normalized = trim(str_replace([' unsigned', ' signed'], '', strtolower($type)));

        return in_array($normalized, ['integer', 'bigint', 'int', 'smallint', 'tinyint', 'mediumint'], true);
    }

    private function isUuidType(string $type): bool
    {
        return in_array(strtolower($type), ['uuid', 'string', 'char', 'varchar', 'text'], true);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $value) === 1;
    }

    private static function canonicalJson(mixed $value, array $without = []): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                $value = array_map(static fn (mixed $item): mixed => self::canonicalizeValue($item), $value);
            } else {
                foreach ($without as $key) {
                    unset($value[$key]);
                }
                ksort($value);
                foreach ($value as $key => $item) {
                    $value[$key] = self::canonicalizeValue($item);
                }
            }
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function canonicalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalizeValue($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalizeValue($item);
        }

        return $value;
    }
}
