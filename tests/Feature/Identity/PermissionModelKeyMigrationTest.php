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
