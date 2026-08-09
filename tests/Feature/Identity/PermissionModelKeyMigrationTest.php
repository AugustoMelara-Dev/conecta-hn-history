<?php

declare(strict_types=1);

use App\Application\Identity\Permissions\PermissionModelKeyMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    setUpPermissionFixture();
});

afterEach(function (): void {
    Schema::disableForeignKeyConstraints();
    foreach ([
        'model_has_permissions_uuid_shadow',
        'model_has_roles_uuid_shadow',
        'model_has_permissions_bigint_legacy',
        'model_has_roles_bigint_legacy',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'permissions',
        'roles',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();
});

it('reports the empty legacy bigint shape without writing source or shadow tables', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $before = $migration->preflight()->toArray();

    expect($before['shape'])->toBe('legacy_bigint_empty')
        ->and($before['status'])->toBe('ready')
        ->and($before['source']['model_has_permissions']['count'])->toBe(0)
        ->and($before['source']['model_has_roles']['count'])->toBe(0)
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeFalse()
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeFalse();
});

it('classifies source shape with a bounded model-id cursor instead of eager rows', function (): void {
    $source = (string) file_get_contents(app_path('Application/Identity/Permissions/PermissionModelKeyMigration.php'));

    expect($source)->not->toContain('->get()->all()')
        ->and($source)->toContain("DB::table(\$table)->select(['model_id'])->cursor()");

    $report = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($report['shape'])->toBe('legacy_bigint_empty')
        ->and($report['source']['model_has_permissions']['count'])->toBe(0)
        ->and($report['source']['model_has_roles']['count'])->toBe(0);
});

it('does not drop exact empty foreign shadows on rollback', function (): void {
    Schema::create('model_has_permissions_uuid_shadow', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->uuid('model_id');
        $table->foreign('permission_id', 'spatie_mhpu_permission_fk')
            ->references('id')->on('permissions')->onDelete('cascade');
        $table->primary(['permission_id', 'model_id', 'model_type'], 'spatie_mhpu_assignment_pk');
        $table->index(['model_id', 'model_type'], 'spatie_mhpu_model_lookup_idx');
    });
    Schema::create('model_has_roles_uuid_shadow', static function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->uuid('model_id');
        $table->foreign('role_id', 'spatie_mhru_role_fk')
            ->references('id')->on('roles')->onDelete('cascade');
        $table->primary(['role_id', 'model_id', 'model_type'], 'spatie_mhru_assignment_pk');
        $table->index(['model_id', 'model_type'], 'spatie_mhru_model_lookup_idx');
    });

    $migrationDefinition = require database_path('migrations/2026_08_23_000001_expand_spatie_uuid_permission_pivots.php');

    expect(fn (): mixed => $migrationDefinition->down())
        ->toThrow(RuntimeException::class)
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeTrue();
});

it('requires a non-null exact-length uuid primary key and exact numeric key contract', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('users');
    Schema::enableForeignKeyConstraints();
    Schema::create('users', static function (Blueprint $table): void {
        $table->uuid('id')->nullable()->primary();
    });

    $nullable = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($nullable['status'])->toBe('blocked')
        ->and($nullable['anomalies']['USER_UUID_SCHEMA_DRIFT'] ?? 0)->toBeGreaterThan(0);

    setUpPermissionFixture();
    $canonical = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($canonical['status'])->toBe('ready')
        ->and($canonical['shape'])->toBe('legacy_bigint_empty');
});

it('reports blank morphs and orphan permission or role assignments', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('model_has_permissions');
    Schema::drop('model_has_roles');
    Schema::enableForeignKeyConstraints();
    Schema::create('model_has_permissions', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
        $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
    });
    Schema::create('model_has_roles', static function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
        $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
    });
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.orphan.permission',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => 999999,
        'model_type' => '',
        'model_id' => '00000000-0000-0000-0000-000000000001',
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => 999999,
        'model_type' => 'user',
        'model_id' => '00000000-0000-0000-0000-000000000002',
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => '00000000-0000-0000-0000-000000000003',
    ]);

    $report = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($report['anomalies']['BLANK_MODEL_TYPE'] ?? 0)->toBeGreaterThan(0)
        ->and($report['anomalies']['ORPHAN_PERMISSION'] ?? 0)->toBeGreaterThan(0)
        ->and($report['anomalies']['ORPHAN_ROLE'] ?? 0)->toBeGreaterThan(0);
});

it('blocks a single partial uuid shadow before any expansion or rollback', function (): void {
    Schema::create('model_has_permissions_uuid_shadow', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->uuid('model_id');
    });

    $report = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['PARTIAL_CUTOVER_STATE'] ?? 0)->toBeGreaterThan(0)
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeFalse();
});

it('fails closed for missing tables, teams mode, and configuration drift', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('roles');
    Schema::enableForeignKeyConstraints();
    $missing = app(PermissionModelKeyMigration::class)->preflight()->toArray();
    expect($missing['shape'])->toBe('missing_required_table')
        ->and($missing['anomalies']['MISSING_REQUIRED_TABLE'])->toBe(1);

    setUpPermissionFixture();
    Config::set('permission.teams', true);
    $teams = app(PermissionModelKeyMigration::class)->preflight()->toArray();
    expect($teams['status'])->toBe('blocked')
        ->and($teams['anomalies']['TEAMS_SHAPE_UNSUPPORTED'])->toBeGreaterThan(0);

    Config::set('permission.teams', false);
    Config::set('permission.table_names.model_has_roles', 'renamed_model_has_roles');
    $drift = app(PermissionModelKeyMigration::class)->preflight()->toArray();
    expect($drift['status'])->toBe('blocked')
        ->and($drift['anomalies']['CONFIG_TABLE_OR_COLUMN_DRIFT'])->toBeGreaterThan(0)
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeFalse();
});

it('classifies malformed assignments and unknown morphs without exposing row values', function (): void {
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.view',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        ['permission_id' => $permissionId, 'model_type' => 'user', 'model_id' => 'not-a-uuid'],
        ['permission_id' => $permissionId, 'model_type' => 'App\\Models\\User', 'model_id' => '00000000-0000-0000-0000-000000000001'],
    ]);

    $payload = app(PermissionModelKeyMigration::class)->preflight()->toArray();
    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    expect($payload['shape'])->toBe('legacy_numeric_or_unresolved')
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['anomalies']['NON_UUID_MODEL_ID'])->toBe(1)
        ->and($payload['anomalies']['NON_CANONICAL_MORPH_TYPE'])->toBe(1)
        ->and($json)->not->toContain('not-a-uuid')
        ->and($json)->not->toContain('00000000-0000-0000-0000-000000000001');
});

it('blocks an orphan canonical user before any shadow or checkpoint write', function (): void {
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.orphan',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => '00000000-0000-0000-0000-000000000099',
    ]);

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-checkpoint-');
    $report = $migration->backfill(1, $checkpoint ?: null, false)->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['ORPHAN_CANONICAL_USER'])->toBe(1)
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeFalse()
        ->and((string) file_get_contents($checkpoint ?: ''))->toBe('');

    @unlink($checkpoint ?: '');
});

it('expands exact uuid shadows and only allows an empty safe rollback', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $report = $migration->expand();

    expect($report->toArray()['status'])->toBe('expanded')
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeTrue()
        ->and(Schema::getColumnType('model_has_permissions_uuid_shadow', 'model_id'))->toBeIn(['string', 'varchar'])
        ->and(Schema::getColumnType('model_has_roles_uuid_shadow', 'model_id'))->toBeIn(['string', 'varchar']);

    $migrationDefinition = require database_path('migrations/2026_08_23_000001_expand_spatie_uuid_permission_pivots.php');
    Schema::create('migrations', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
    DB::table('migrations')->insert([
        'migration' => '2026_08_23_000001_expand_spatie_uuid_permission_pivots',
        'batch' => 999,
    ]);
    $migrationDefinition->down();

    expect(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeFalse()
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeFalse();
});

it('refuses expansion when source shape is mixed or a legacy backup is occupied', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    Schema::create('model_has_permissions_bigint_legacy', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->uuid('model_id');
    });

    $report = $migration->expand()->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['PARTIAL_CUTOVER_STATE'])->toBeGreaterThan(0);
});

it('backfills in bounded resumable batches and validates a stable sanitized artifact', function (): void {
    $userId = '00000000-0000-0000-0000-000000000001';
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Fixture',
        'email' => 'fixture@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.view',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $roleId = DB::table('roles')->insertGetId([
        'name' => 'crm.viewer',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-checkpoint-');
    $artifact = tempnam(sys_get_temp_dir(), 'permission-key-artifact-');
    expect($checkpoint)->toBeString()->and($artifact)->toBeString();

    $first = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    expect((string) file_get_contents($checkpoint ?: ''))->not->toContain($userId);
    $sourceBeforeReplay = DB::table('model_has_permissions')->get()->toArray();
    $second = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    $replay = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    $validated = $migration->validate($artifact ?: null)->toArray();

    expect($first['status'])->toBe('pending')
        ->and($second['status'])->toBe('complete')
        ->and($replay['status'])->toBe('complete')
        ->and($replay['copied'])->toBe(0)
        ->and($validated['status'])->toBe('valid')
        ->and($validated['details']['replay_proof']['measured'] ?? false)->toBeTrue()
        ->and($validated['details']['replay_proof']['passes'] ?? 0)->toBe(2)
        ->and(DB::table('model_has_permissions')->get()->toArray())->toEqual($sourceBeforeReplay)
        ->and(DB::table('model_has_permissions_uuid_shadow')->count())->toBe(1)
        ->and(DB::table('model_has_roles_uuid_shadow')->count())->toBe(1)
        ->and(file_get_contents($artifact ?: '') ?: '')->toContain('expires_at')
        ->and(file_get_contents($artifact ?: '') ?: '')->toContain('artifact_sha256')
        ->and(file_get_contents($artifact ?: '') ?: '')->not->toContain($userId);

    @unlink($checkpoint ?: '');
    @unlink($artifact ?: '');
});

it('counts a pre-existing exact target tuple as already present without changing the source', function (): void {
    $userId = '00000000-0000-0000-0000-000000000003';
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Fixture',
        'email' => 'fixture3@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.existing',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    DB::table('model_has_permissions_uuid_shadow')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);
    $report = $migration->backfill(1)->toArray();

    expect($report['status'])->toBe('complete')
        ->and($report['copied'])->toBe(0)
        ->and($report['already_present'])->toBe(1)
        ->and($migration->validate()->toArray()['status'])->toBe('valid');
});

it('blocks source drift ahead of or behind a resumable cursor and preserves the checkpoint', function (): void {
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.drift',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    foreach (['00000000-0000-0000-0000-000000000011', '00000000-0000-0000-0000-000000000012'] as $index => $userId) {
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Fixture '.$index,
            'email' => 'fixture-drift-'.$index.'@example.test',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => $permissionId,
            'model_type' => 'user',
            'model_id' => $userId,
        ]);
    }

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-checkpoint-');
    $first = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    $checkpointBeforeDrift = (string) file_get_contents($checkpoint ?: '');
    DB::table('users')->insert([
        'id' => '00000000-0000-0000-0000-000000000013',
        'name' => 'Fixture 3',
        'email' => 'fixture-drift-3@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => '00000000-0000-0000-0000-000000000013',
    ]);
    $drift = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    DB::table('model_has_permissions')->where('model_id', '00000000-0000-0000-0000-000000000013')->delete();
    DB::table('model_has_permissions')->where('model_id', '00000000-0000-0000-0000-000000000011')->delete();
    $driftBehind = $migration->backfill(1, $checkpoint ?: null, false)->toArray();

    expect($first['status'])->toBe('pending')
        ->and($drift['status'])->toBe('blocked')
        ->and($drift['anomalies']['SOURCE_CHANGED_SINCE_CHECKPOINT'])->toBe(1)
        ->and($driftBehind['status'])->toBe('blocked')
        ->and($driftBehind['anomalies']['SOURCE_CHANGED_SINCE_CHECKPOINT'])->toBe(1)
        ->and((string) file_get_contents($checkpoint ?: ''))->toBe($checkpointBeforeDrift)
        ->and(DB::table('model_has_permissions_uuid_shadow')->count())->toBe(1);

    @unlink($checkpoint ?: '');
});

it('rolls back dry runs and rejects checkpoint or validation tampering', function (): void {
    $userId = '00000000-0000-0000-0000-000000000002';
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Fixture',
        'email' => 'fixture2@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.edit',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-checkpoint-');
    $artifact = tempnam(sys_get_temp_dir(), 'permission-key-artifact-');
    expect($checkpoint)->toBeString()->and($artifact)->toBeString();

    $dryRun = $migration->backfill(1, $checkpoint ?: null, true)->toArray();
    expect($dryRun['status'])->toBe('dry_run')
        ->and(DB::table('model_has_permissions_uuid_shadow')->count())->toBe(0)
        ->and((string) file_get_contents($checkpoint ?: ''))->toBe('');

    $migration->backfill(1, $checkpoint ?: null, false);
    $artifactReport = $migration->validate($artifact ?: null)->toArray();
    file_put_contents($checkpoint ?: '', '{"tampered":true}');
    $checkpointReport = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    file_put_contents($artifact ?: '', '{"tampered":true}');
    $artifactReportAfterTamper = $migration->validate($artifact ?: null)->toArray();

    expect($artifactReport['status'])->toBe('valid')
        ->and($checkpointReport['anomalies']['CHECKPOINT_TAMPERED'])->toBe(1)
        ->and($artifactReportAfterTamper['anomalies']['VALIDATION_ARTIFACT_TAMPERED'])->toBe(1);

    @unlink($checkpoint ?: '');
    @unlink($artifact ?: '');
});

it('exposes a sanitized artisan report with the sqlite verification scope', function (): void {
    $result = $this->artisan('permissions:reconcile-model-keys', [
        'phase' => 'preflight',
        '--json' => true,
    ]);

    $result->assertExitCode(0)
        ->expectsOutputToContain('"verification_scope":"LOCALLY_VERIFIED_SQLITE"');
});

it('blocks when users id is not an exact uuid primary key', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('users');
    Schema::enableForeignKeyConstraints();
    Schema::create('users', static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name')->nullable();
    });

    $report = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['USER_UUID_SCHEMA_DRIFT'] ?? 0)->toBeGreaterThan(0)
        ->and($report['blocking_issues'])->toContain('USER_UUID_SCHEMA_DRIFT');
});

it('blocks when role permission keys drift from the exact primary and foreign-key contract', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('role_has_permissions');
    Schema::enableForeignKeyConstraints();
    Schema::create('role_has_permissions', static function (Blueprint $table): void {
        $table->integer('permission_id');
        $table->integer('role_id');
    });

    $report = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['ROLE_PERMISSION_SCHEMA_DRIFT'] ?? 0)->toBeGreaterThan(0)
        ->and($report['blocking_issues'])->toContain('ROLE_PERMISSION_SCHEMA_DRIFT');
});

it('reports duplicate source assignments while preserving a sanitized anomaly', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('model_has_permissions');
    Schema::enableForeignKeyConstraints();
    Schema::create('model_has_permissions', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.duplicate',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        ['permission_id' => $permissionId, 'model_type' => 'user', 'model_id' => 1],
        ['permission_id' => $permissionId, 'model_type' => 'user', 'model_id' => 1],
    ]);

    $report = app(PermissionModelKeyMigration::class)->preflight()->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['DUPLICATE_ASSIGNMENT'] ?? 0)->toBeGreaterThan(0)
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->not->toContain('"model_id":1');
});

it('does not let a direct migration bypass preflight ownership', function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::drop('users');
    Schema::enableForeignKeyConstraints();
    Schema::create('users', static function (Blueprint $table): void {
        $table->integer('id')->primary();
    });

    $migrationDefinition = require database_path('migrations/2026_08_23_000001_expand_spatie_uuid_permission_pivots.php');

    expect(fn (): mixed => $migrationDefinition->up())
        ->toThrow(RuntimeException::class)
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeFalse()
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeFalse();
});

it('rejects rollback when an owned shadow contains assignments', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.rollback',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions_uuid_shadow')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => '00000000-0000-0000-0000-0000000000ab',
    ]);
    $migrationDefinition = require database_path('migrations/2026_08_23_000001_expand_spatie_uuid_permission_pivots.php');

    expect(fn (): mixed => $migrationDefinition->down())
        ->toThrow(RuntimeException::class)
        ->and(Schema::hasTable('model_has_permissions_uuid_shadow'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles_uuid_shadow'))->toBeTrue();
});

it('blocks a shadow schema drift before any copy can proceed', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    expect($migration->expand()->status)->toBe('expanded');
    Schema::table('model_has_permissions_uuid_shadow', static function (Blueprint $table): void {
        $table->dropIndex('spatie_mhpu_model_lookup_idx');
    });

    $preflight = $migration->preflight()->toArray();
    $backfill = $migration->backfill(1)->toArray();

    expect($preflight['status'])->toBe('blocked')
        ->and($preflight['anomalies']['SHADOW_SCHEMA_DRIFT'] ?? 0)->toBeGreaterThan(0)
        ->and($backfill['status'])->toBe('blocked')
        ->and($backfill['anomalies']['SHADOW_SCHEMA_DRIFT'] ?? 0)->toBeGreaterThan(0);
});

it('blocks a command with a stable nonzero exit when preflight is unsafe', function (): void {
    Config::set('permission.teams', true);

    $result = $this->artisan('permissions:reconcile-model-keys', [
        'phase' => 'preflight',
        '--json' => true,
    ]);

    $result->assertExitCode(5)
        ->expectsOutputToContain('TEAMS_SHAPE_UNSUPPORTED');
});

it('binds validation artifacts to exact nonempty head and tree identities', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $artifact = tempnam(sys_get_temp_dir(), 'permission-key-identity-');
    $head = [];
    $tree = [];
    $headStatus = 1;
    $treeStatus = 1;
    exec('git rev-parse HEAD', $head, $headStatus);
    exec('git write-tree', $tree, $treeStatus);
    expect($headStatus)->toBe(0)->and($treeStatus)->toBe(0);

    $valid = $migration->validate($artifact ?: null, trim((string) ($head[0] ?? '')), trim((string) ($tree[0] ?? '')))->toArray();
    $stale = $migration->validate($artifact ?: null, str_repeat('a', 40), trim((string) ($tree[0] ?? '')))->toArray();

    expect($valid['status'])->toBe('valid')
        ->and($valid['artifact'])->not->toBeEmpty()
        ->and($stale['status'])->toBe('blocked')
        ->and($stale['anomalies']['HEAD_TREE_IDENTITY_MISMATCH'] ?? 0)->toBeGreaterThan(0);

    @unlink($artifact ?: '');
});

it('blocks expired validation artifacts with a stable anomaly', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $artifact = tempnam(sys_get_temp_dir(), 'permission-key-expired-');
    $migration->validate($artifact ?: null);
    $payload = json_decode((string) file_get_contents($artifact ?: ''), true, 512, JSON_THROW_ON_ERROR);
    $payload['expires_at'] = '2000-01-01T00:00:00+00:00';
    $canonical = new ReflectionMethod(PermissionModelKeyMigration::class, 'canonicalJson');
    $canonical->setAccessible(true);
    $payload['artifact_sha256'] = hash('sha256', $canonical->invoke(null, $payload, ['artifact_sha256']));
    file_put_contents($artifact ?: '', json_encode($payload, JSON_THROW_ON_ERROR));

    $report = $migration->validate($artifact ?: null)->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['VALIDATION_ARTIFACT_EXPIRED'] ?? 0)->toBeGreaterThan(0);

    @unlink($artifact ?: '');
});

it('rejects a checkpoint whose chain cannot be recomputed from its previous chain', function (): void {
    $userId = '00000000-0000-0000-0000-0000000000ac';
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Checkpoint fixture',
        'email' => 'checkpoint@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.checkpoint',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-chain-');
    $migration->backfill(1, $checkpoint ?: null, false);
    $payload = json_decode((string) file_get_contents($checkpoint ?: ''), true, 512, JSON_THROW_ON_ERROR);
    $payload['chain_sha256'] = str_repeat('a', 64);
    $checksum = new ReflectionMethod(PermissionModelKeyMigration::class, 'checkpointChecksum');
    $checksum->setAccessible(true);
    $payload['checkpoint_sha256'] = $checksum->invoke($migration, $payload);
    file_put_contents($checkpoint ?: '', json_encode($payload, JSON_THROW_ON_ERROR));

    $report = $migration->backfill(1, $checkpoint ?: null, false)->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['CHECKPOINT_TAMPERED'] ?? 0)->toBeGreaterThan(0);

    @unlink($checkpoint ?: '');
});

it('rejects malformed cursor maps and checkpoint counters before array replacement', function (): void {
    $userId = '00000000-0000-0000-0000-0000000000af';
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Malformed checkpoint',
        'email' => 'malformed-checkpoint@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.malformed.checkpoint',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'user',
        'model_id' => $userId,
    ]);

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-malformed-');
    $migration->backfill(1, $checkpoint ?: null, false);
    $payload = json_decode((string) file_get_contents($checkpoint ?: ''), true, 512, JSON_THROW_ON_ERROR);
    $payload['cursors']['unexpected'] = null;
    $payload['cursors']['model_has_permissions'] = 123;
    $payload['rows_examined'] = '1';
    $payload['anomaly_counts'] = 'invalid';
    $batchPayload = new ReflectionMethod(PermissionModelKeyMigration::class, 'batchPayloadChecksum');
    $batchPayload->setAccessible(true);
    $payload['batch_payload_sha256'] = $batchPayload->invoke($migration, $payload);
    $chain = new ReflectionMethod(PermissionModelKeyMigration::class, 'chainChecksum');
    $chain->setAccessible(true);
    $payload['chain_sha256'] = $chain->invoke($migration, $payload);
    $checksum = new ReflectionMethod(PermissionModelKeyMigration::class, 'checkpointChecksum');
    $checksum->setAccessible(true);
    $payload['checkpoint_sha256'] = $checksum->invoke($migration, $payload);
    file_put_contents($checkpoint ?: '', json_encode($payload, JSON_THROW_ON_ERROR));

    $report = null;
    expect(function () use (&$report, $migration, $checkpoint): void {
        $report = $migration->backfill(1, $checkpoint ?: null, false)->toArray();
    })->not->toThrow(Throwable::class);

    expect($report['status'] ?? null)->toBe('blocked')
        ->and($report['anomalies']['CHECKPOINT_TAMPERED'] ?? 0)->toBeGreaterThan(0);

    @unlink($checkpoint ?: '');
});

it('blocks validation when the repository identity cannot be observed', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $originalDirectory = getcwd();

    try {
        chdir(sys_get_temp_dir());
        $report = $migration->validate(
            expectedHead: str_repeat('a', 40),
            expectedTree: str_repeat('b', 40),
        )->toArray();
    } finally {
        chdir($originalDirectory ?: base_path());
    }

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['HEAD_TREE_IDENTITY_UNAVAILABLE'] ?? 0)->toBeGreaterThan(0);
});

it('blocks a shadow whose required foreign-key metadata is absent', function (): void {
    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    Schema::disableForeignKeyConstraints();
    Schema::drop('model_has_roles_uuid_shadow');
    Schema::enableForeignKeyConstraints();
    Schema::create('model_has_roles_uuid_shadow', static function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->uuid('model_id');
        $table->primary(['role_id', 'model_id', 'model_type'], 'spatie_mhru_assignment_pk');
        $table->index(['model_id', 'model_type'], 'spatie_mhru_model_lookup_idx');
    });

    $report = $migration->preflight()->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['SHADOW_SCHEMA_DRIFT'] ?? 0)->toBeGreaterThan(0);
});

it('blocks a stale opaque cursor without exposing its source tuple', function (): void {
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'crm.cursor',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    foreach (['00000000-0000-0000-0000-0000000000ad', '00000000-0000-0000-0000-0000000000ae'] as $index => $userId) {
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Cursor '.$index,
            'email' => 'cursor'.$index.'@example.test',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => $permissionId,
            'model_type' => 'user',
            'model_id' => $userId,
        ]);
    }

    $migration = app(PermissionModelKeyMigration::class);
    $migration->expand();
    $checkpoint = tempnam(sys_get_temp_dir(), 'permission-key-stale-');
    $migration->backfill(1, $checkpoint ?: null, false);
    $payload = json_decode((string) file_get_contents($checkpoint ?: ''), true, 512, JSON_THROW_ON_ERROR);
    $cursorKey = new ReflectionMethod(PermissionModelKeyMigration::class, 'cursorKey');
    $cursorKey->setAccessible(true);
    $payload['cursors']['model_has_permissions'] = hash('sha256', $cursorKey->invoke(null, (object) [
        'permission_id' => 999999,
        'model_type' => 'user',
        'model_id' => '00000000-0000-0000-0000-0000000000ff',
    ], 'model_has_permissions'));
    $chain = new ReflectionMethod(PermissionModelKeyMigration::class, 'chainChecksum');
    $chain->setAccessible(true);
    $batchPayload = new ReflectionMethod(PermissionModelKeyMigration::class, 'batchPayloadChecksum');
    $batchPayload->setAccessible(true);
    $payload['batch_payload_sha256'] = $batchPayload->invoke($migration, $payload);
    $payload['chain_sha256'] = $chain->invoke($migration, $payload);
    $checksum = new ReflectionMethod(PermissionModelKeyMigration::class, 'checkpointChecksum');
    $checksum->setAccessible(true);
    $payload['checkpoint_sha256'] = $checksum->invoke($migration, $payload);
    file_put_contents($checkpoint ?: '', json_encode($payload, JSON_THROW_ON_ERROR));

    $report = $migration->backfill(1, $checkpoint ?: null, false)->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['anomalies']['CHECKPOINT_CURSOR_STALE'] ?? 0)->toBeGreaterThan(0)
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->not->toContain('00000000-0000-0000-0000-0000000000ff');

    @unlink($checkpoint ?: '');
});

function setUpPermissionFixture(): void
{
    Config::set('permission.teams', false);
    Config::set('permission.table_names', [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ]);
    Config::set('permission.column_names', [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ]);

    Schema::disableForeignKeyConstraints();
    foreach (['model_has_permissions_uuid_shadow', 'model_has_roles_uuid_shadow', 'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'permissions', 'roles', 'users'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();

    Schema::create('users', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
    Schema::create('permissions', static function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });
    Schema::create('roles', static function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });
    Schema::create('role_has_permissions', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        $table->primary(['permission_id', 'role_id']);
    });
    Schema::create('model_has_permissions', static function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
        $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
    });
    Schema::create('model_has_roles', static function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
        $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
    });
}
