<?php

declare(strict_types=1);

namespace App\Application\Identity\Permissions;

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
    ): PermissionModelKeyMigrationReport {
        return match (strtolower(trim($phase))) {
            'preflight' => $this->preflight(),
            'expand' => $this->expand(),
            'backfill' => $this->backfill($batchSize, $checkpointPath, $dryRun),
            'validate' => $this->validate($artifactPath),
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

        $source = $this->sourceRows();
        $sourceSnapshots = [];
        foreach ($source as $table => $rows) {
            $sourceSnapshots[$table] = $this->snapshot($rows, $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id');
        }

        $scanned = $this->scanSourceAnomalies($source);
        foreach ($scanned as $code => $count) {
            $anomalies[$code] = ($anomalies[$code] ?? 0) + $count;
            $blocking[] = $code;
        }

        $shape = $this->classifyShape($source, $sourceSchemaIssues, $cutoverIssues);
        if ($shape === self::SHAPE_MIXED) {
            $anomalies['SHADOW_SCHEMA_DRIFT'] = ($anomalies['SHADOW_SCHEMA_DRIFT'] ?? 0) + 0;
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

        $currentSource = $this->sourceRows();
        $currentSnapshots = $this->sourceSnapshots($currentSource);
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

        $rowsByTable = [];
        $selectedByTable = [];
        $nextCursors = $cursors;
        $examined = 0;
        $batchIssues = [];
        $remainingBatch = $batchSize;
        foreach ($currentSource as $table => $rows) {
            $rowsByTable[$table] = $rows;
            [$selected, $nextCursor, $cursorError] = $this->selectBatch($rows, $table, $cursors[$table], $remainingBatch);
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

            $afterSource = $this->sourceRows();
            if ($this->sourceSnapshots($afterSource) !== $currentSnapshots) {
                throw new RuntimeException('SOURCE_CHANGED_SINCE_CHECKPOINT');
            }

            if ($dryRun) {
                DB::rollBack();

                return $this->phaseReport($preflight, 'backfill', 'dry_run', [], [
                    'batch_size' => $batchSize,
                    'has_more' => $this->hasMore($rowsByTable, $nextCursors),
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

        $hasMore = $this->hasMore($rowsByTable, $nextCursors);
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
            'cursors' => $nextCursors,
            'rows_examined' => $examined,
            'rows_copied' => $copied,
            'rows_already_present' => $alreadyPresent,
            'has_more' => $hasMore,
            'anomaly_counts' => [],
            'previous_chain_sha256' => $previousChain,
        ];
        $checkpointSummary['chain_sha256'] = hash('sha256', $previousChain.'|'.self::canonicalJson($checkpointSummary));
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

    public function validate(?string $artifactPath = null): PermissionModelKeyMigrationReport
    {
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

        $source = $this->sourceRows();
        $sourceSnapshots = $this->sourceSnapshots($source);
        $shadow = $this->shadowRows();
        $shadowSnapshots = $this->shadowSnapshots($shadow);
        $anomalies = $this->scanSourceAnomalies($source);
        $blocking = array_keys($anomalies);

        foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
            $sourceKeys = $this->tupleSet($source[$table] ?? [], $table);
            $shadowTable = $table === self::SOURCE_PERMISSION ? self::SHADOW_PERMISSION : self::SHADOW_ROLE;
            $shadowKeys = $this->tupleSet($shadow[$shadowTable] ?? [], $table);
            foreach (array_diff_key($sourceKeys, $shadowKeys) as $_missing) {
                $anomalies['SHADOW_ASSIGNMENT_MISSING'] = ($anomalies['SHADOW_ASSIGNMENT_MISSING'] ?? 0) + 1;
            }
            foreach (array_diff_key($shadowKeys, $sourceKeys) as $_extra) {
                $anomalies['SHADOW_ASSIGNMENT_EXTRA'] = ($anomalies['SHADOW_ASSIGNMENT_EXTRA'] ?? 0) + 1;
            }
        }
        $blocking = array_keys($anomalies);

        $sourceAfter = $this->sourceSnapshots($this->sourceRows());
        if ($sourceAfter !== $sourceSnapshots) {
            $anomalies['SOURCE_CHANGED_SINCE_CHECKPOINT'] = 1;
            $blocking[] = 'SOURCE_CHANGED_SINCE_CHECKPOINT';
        }
        if ($this->foreignKeyIssues() !== []) {
            $anomalies['FOREIGN_KEY_INTEGRITY'] = count($this->foreignKeyIssues());
            $blocking[] = 'FOREIGN_KEY_INTEGRITY';
        }

        $payload = [
            'command_version' => self::COMMAND_VERSION,
            'phase' => 'validate',
            'head' => getenv('DOVRA_GIT_HEAD') !== false ? getenv('DOVRA_GIT_HEAD') : null,
            'tree' => getenv('DOVRA_GIT_TREE') !== false ? getenv('DOVRA_GIT_TREE') : null,
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
            details: ['source_immutable' => true, 'cutover' => 'EXTERNAL_BLOCKED'],
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
        foreach ([self::SOURCE_PERMISSION => 'permission_id', self::SOURCE_ROLE => 'role_id'] as $table => $pivot) {
            $columns = Schema::getColumnListing($table);
            $expectedColumns = [$pivot, 'model_type', 'model_id'];
            if (array_diff($expectedColumns, $columns) !== [] || array_diff($columns, $expectedColumns) !== []) {
                $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
            }
            if (Schema::hasColumn($table, 'team_id')) {
                $issues[] = 'TEAMS_SHAPE_UNSUPPORTED';
            }
            if (Schema::hasColumn($table, 'model_type') && ! $this->isUuidType(Schema::getColumnType($table, 'model_type'))) {
                $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
            }
            $descriptor = $this->tableDescriptor($table);
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
                if (($index['name'] ?? null) === $lookupName && $indexColumns === ['model_id', 'model_type']) {
                    $lookupMatches = true;
                }
                if (($index['name'] ?? null) === $primaryName
                    && $indexColumns === $primaryColumns) {
                    $primaryMatches = true;
                }
                if (DB::getDriverName() === 'sqlite'
                    && ($index['primary'] ?? false) === true
                    && $indexColumns === $primaryColumns) {
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
            if (! $lookupMatches || ! $primaryMatches || ! $foreignKeyMatches) {
                $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
            }
        }
        foreach (['permissions' => 'id', 'roles' => 'id'] as $table => $column) {
            if (! Schema::hasColumn($table, $column) || ! $this->isIntegerType(Schema::getColumnType($table, $column))) {
                $issues[] = 'ROLE_PERMISSION_KEY_TYPE_DRIFT';
            }
        }
        if (! Schema::hasColumn('role_has_permissions', 'permission_id')
            || ! Schema::hasColumn('role_has_permissions', 'role_id')
            || Schema::hasColumn('role_has_permissions', 'team_id')) {
            $issues[] = 'CONFIG_TABLE_OR_COLUMN_DRIFT';
        }

        return array_values(array_unique($issues));
    }

    /** @return list<string> */
    private function cutoverStateIssues(): array
    {
        $issues = [];
        $permissionShadow = Schema::hasTable(self::SHADOW_PERMISSION);
        $roleShadow = Schema::hasTable(self::SHADOW_ROLE);
        $permissionBackup = Schema::hasTable(self::BACKUP_PERMISSION);
        $roleBackup = Schema::hasTable(self::BACKUP_ROLE);

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

        return array_values(array_unique($issues));
    }

    /** @param array<string, list<object>> $source */
    private function classifyShape(array $source, array $schemaIssues, array $cutoverIssues): string
    {
        if ($schemaIssues !== [] || $cutoverIssues !== []) {
            return self::SHAPE_MIXED;
        }
        $hasInvalid = false;
        $hasRows = false;
        foreach ($source as $rows) {
            foreach ($rows as $row) {
                $hasRows = true;
                if (! $this->isUuid((string) ($row->model_id ?? ''))) {
                    $hasInvalid = true;
                }
            }
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

    /** @return array<string, list<object>> */
    private function sourceRows(): array
    {
        return [
            self::SOURCE_PERMISSION => $this->rows(self::SOURCE_PERMISSION),
            self::SOURCE_ROLE => $this->rows(self::SOURCE_ROLE),
        ];
    }

    /** @return array<string, list<object>> */
    private function shadowRows(): array
    {
        return [
            self::SHADOW_PERMISSION => $this->rows(self::SHADOW_PERMISSION),
            self::SHADOW_ROLE => $this->rows(self::SHADOW_ROLE),
        ];
    }

    /** @return list<object> */
    private function rows(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->get()->all();
    }

    /** @param array<string, list<object>> $source */
    private function sourceSnapshots(array $source): array
    {
        return [
            self::SOURCE_PERMISSION => $this->snapshot($source[self::SOURCE_PERMISSION] ?? [], 'permission_id'),
            self::SOURCE_ROLE => $this->snapshot($source[self::SOURCE_ROLE] ?? [], 'role_id'),
            'aggregate_hash' => hash('sha256', self::canonicalJson([
                self::SOURCE_PERMISSION => $this->snapshot($source[self::SOURCE_PERMISSION] ?? [], 'permission_id'),
                self::SOURCE_ROLE => $this->snapshot($source[self::SOURCE_ROLE] ?? [], 'role_id'),
            ])),
        ];
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

        return [
            'count' => count($tuples),
            'hash' => hash('sha256', self::canonicalJson($tuples)),
        ];
    }

    /** @param array<string, list<object>> $source @return array<string, int> */
    private function scanSourceAnomalies(array $source): array
    {
        $anomalies = [];
        foreach ([self::SOURCE_PERMISSION => 'permission_id', self::SOURCE_ROLE => 'role_id'] as $table => $pivot) {
            $seen = [];
            foreach ($source[$table] ?? [] as $row) {
                $key = (string) ($row->{$pivot} ?? '').'|'.(string) ($row->model_type ?? '').'|'.(string) ($row->model_id ?? '');
                if (isset($seen[$key])) {
                    $anomalies['DUPLICATE_ASSIGNMENT'] = ($anomalies['DUPLICATE_ASSIGNMENT'] ?? 0) + 1;
                }
                $seen[$key] = true;
                foreach ($this->rowIssues($row, $table) as $code) {
                    $anomalies[$code] = ($anomalies[$code] ?? 0) + 1;
                }
            }
        }

        return $anomalies;
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
        $shadow ??= $this->shadowRows();

        return [
            self::SHADOW_PERMISSION => $this->snapshot($shadow[self::SHADOW_PERMISSION] ?? [], 'permission_id'),
            self::SHADOW_ROLE => $this->snapshot($shadow[self::SHADOW_ROLE] ?? [], 'role_id'),
        ];
    }

    /** @param array<string, list<object>> $rows @return array<string, bool> */
    private function tupleSet(array $rows, string $table): array
    {
        $pivot = $table === self::SOURCE_PERMISSION ? 'permission_id' : 'role_id';
        $set = [];
        foreach ($rows as $row) {
            $key = self::canonicalJson([
                $pivot => (string) ($row->{$pivot} ?? ''),
                'model_type' => (string) ($row->model_type ?? ''),
                'model_id' => (string) ($row->model_id ?? ''),
            ]);
            $set[hash('sha256', $key)] = true;
        }

        return $set;
    }

    /** @param list<object> $rows @return array{0:list<object>,1:?string,2:?string} */
    private function selectBatch(array $rows, string $table, ?string $cursor, int $limit): array
    {
        usort($rows, static fn (object $left, object $right): int => self::compareRows($left, $right, $table));
        $start = 0;
        if ($cursor !== null) {
            $found = false;
            foreach ($rows as $index => $row) {
                if (hash('sha256', self::cursorKey($row, $table)) === $cursor) {
                    $start = $index + 1;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return [[], null, 'CHECKPOINT_CURSOR_STALE'];
            }
        }
        $selected = array_slice($rows, $start, $limit);
        $last = $selected === [] ? null : $selected[array_key_last($selected)];
        $next = $last === null ? $cursor : hash('sha256', self::cursorKey($last, $table));

        return [$selected, $next, null];
    }

    /** @param array<string, list<object>> $rows @param array<string, ?string> $cursors */
    private function hasMore(array $rows, array $cursors): bool
    {
        foreach ([self::SOURCE_PERMISSION, self::SOURCE_ROLE] as $table) {
            $cursor = $cursors[$table] ?? null;
            $ordered = $rows[$table] ?? [];
            usort($ordered, static fn (object $left, object $right): int => self::compareRows($left, $right, $table));
            if ($ordered === []) {
                continue;
            }
            if ($cursor === null) {
                return true;
            }
            $found = false;
            foreach ($ordered as $row) {
                if ($found) {
                    return true;
                }
                $found = hash('sha256', self::cursorKey($row, $table)) === $cursor;
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
        foreach (['command_version', 'phase', 'driver', 'database_version', 'cursor_version', 'batch_size', 'batch_sequence', 'source', 'schema_fingerprint', 'source_schema_fingerprint', 'config_fingerprint', 'cursors', 'has_more', 'chain_sha256', 'checkpoint_sha256'] as $key) {
            if (! array_key_exists($key, $checkpoint)) {
                return 'CHECKPOINT_TAMPERED';
            }
        }
        if (($checkpoint['command_version'] ?? null) !== self::COMMAND_VERSION
            || ($checkpoint['phase'] ?? null) !== 'backfill'
            || ($checkpoint['cursor_version'] ?? null) !== 1
            || ($checkpoint['batch_size'] ?? null) !== $batchSize
            || ! is_int($checkpoint['batch_sequence'] ?? null)
            || $checkpoint['batch_sequence'] < 1
            || ($checkpoint['source'] ?? null) !== $snapshots
            || ($checkpoint['schema_fingerprint'] ?? null) !== $schemaFingerprint
            || ($checkpoint['source_schema_fingerprint'] ?? null) !== $this->sourceSchemaFingerprint()
            || ($checkpoint['config_fingerprint'] ?? null) !== $this->configurationFingerprint()
            || ! is_array($checkpoint['cursors'] ?? null)
            || ! is_bool($checkpoint['has_more'] ?? null)) {
            return 'SOURCE_CHANGED_SINCE_CHECKPOINT';
        }
        if (($checkpoint['checkpoint_sha256'] ?? null) !== $this->checkpointChecksum($checkpoint)
            || ! is_string($checkpoint['chain_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['chain_sha256']) !== 1) {
            return 'CHECKPOINT_TAMPERED';
        }

        return null;
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
            'chain_sha256' => (string) ($checkpoint['chain_sha256'] ?? ''),
            'checkpoint_sha256' => (string) ($checkpoint['checkpoint_sha256'] ?? ''),
        ];
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
        foreach (['permissions', 'roles', self::SOURCE_PERMISSION, self::SOURCE_ROLE, self::SHADOW_PERMISSION, self::SHADOW_ROLE] as $table) {
            if (Schema::hasTable($table)) {
                $tables[$table] = $this->tableDescriptor($table);
            }
        }

        return ['fingerprint' => $this->schemaFingerprint(), 'tables' => $tables];
    }

    /** @return array<string, mixed> */
    private function tableDescriptor(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return ['missing' => true];
        }
        $columns = [];
        try {
            foreach (Schema::getColumns($table) as $column) {
                $columns[] = [
                    'name' => (string) ($column['name'] ?? ''),
                    'type' => strtolower((string) ($column['type'] ?? '')),
                    'nullable' => (bool) ($column['nullable'] ?? false),
                    'default' => $column['default'] ?? null,
                ];
            }
        } catch (Throwable) {
            foreach (Schema::getColumnListing($table) as $name) {
                $columns[] = ['name' => $name, 'type' => strtolower((string) Schema::getColumnType($table, $name))];
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
        }
        usort($indexes, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));
        $foreignKeys = [];
        try {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $foreignKeys[] = [
                    'columns' => array_values(array_map('strval', $foreignKey['columns'] ?? [])),
                    'foreign_table' => (string) ($foreignKey['foreign_table'] ?? ''),
                    'foreign_columns' => array_values(array_map('strval', $foreignKey['foreign_columns'] ?? [])),
                    'on_delete' => strtolower((string) ($foreignKey['on_delete'] ?? '')),
                ];
            }
        } catch (Throwable) {
        }
        usort($foreignKeys, static fn (array $left, array $right): int => self::canonicalJson($left) <=> self::canonicalJson($right));

        return ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys];
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
                || ! $this->isIntegerType(Schema::getColumnType($table, $pivot))
                || ! $this->isUuidType(Schema::getColumnType($table, 'model_id'))
                || Schema::hasColumn($table, 'team_id')) {
                $issues[] = 'SHADOW_SCHEMA_DRIFT';
            }
            $descriptor = $this->tableDescriptor($table);
            $requiredIndex = $table === self::SHADOW_PERMISSION ? 'spatie_mhpu_model_lookup_idx' : 'spatie_mhru_model_lookup_idx';
            $requiredPrimary = $table === self::SHADOW_PERMISSION ? 'spatie_mhpu_assignment_pk' : 'spatie_mhru_assignment_pk';
            $lookupMatches = false;
            $primaryMatches = false;
            foreach ($descriptor['indexes'] ?? [] as $index) {
                $indexColumns = array_values(array_map('strval', $index['columns'] ?? []));
                if (($index['name'] ?? null) === $requiredIndex
                    && $indexColumns === ['model_id', 'model_type']) {
                    $lookupMatches = true;
                }
                if (($index['name'] ?? null) === $requiredPrimary
                    && $indexColumns === [$pivot, 'model_id', 'model_type']) {
                    $primaryMatches = true;
                }
                // SQLite synthesizes primary index names, so its column shape is authoritative.
                if (DB::getDriverName() === 'sqlite'
                    && ($index['primary'] ?? false) === true
                    && $indexColumns === [$pivot, 'model_id', 'model_type']) {
                    $primaryMatches = true;
                }
            }
            $parent = $table === self::SHADOW_PERMISSION ? 'permissions' : 'roles';
            $foreignKeyMatches = false;
            foreach ($descriptor['foreign_keys'] ?? [] as $foreignKey) {
                if (($foreignKey['columns'] ?? []) === [$pivot]
                    && ($foreignKey['foreign_table'] ?? null) === $parent
                    && ($foreignKey['foreign_columns'] ?? []) === ['id']
                    && strtolower((string) ($foreignKey['on_delete'] ?? '')) === 'cascade') {
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
            return [];
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
        return in_array(strtolower($type), ['integer', 'bigint', 'int', 'int unsigned', 'integer unsigned'], true);
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
