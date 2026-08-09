<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ((bool) config('permission.teams', false)) {
            throw new RuntimeException('Spatie teams are not supported by the UUID pivot expansion.');
        }

        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);
        if (! is_array($tableNames) || ! is_array($columnNames)
            || ($tableNames['permissions'] ?? null) !== 'permissions'
            || ($tableNames['roles'] ?? null) !== 'roles'
            || ($tableNames['model_has_permissions'] ?? null) !== 'model_has_permissions'
            || ($tableNames['model_has_roles'] ?? null) !== 'model_has_roles'
            || ($tableNames['role_has_permissions'] ?? null) !== 'role_has_permissions'
            || ($columnNames['model_morph_key'] ?? 'model_id') !== 'model_id'
            || (($columnNames['permission_pivot_key'] ?? null) !== null && ($columnNames['permission_pivot_key'] ?? null) !== 'permission_id')
            || (($columnNames['role_pivot_key'] ?? null) !== null && ($columnNames['role_pivot_key'] ?? null) !== 'role_id')) {
            throw new RuntimeException('Spatie permission table or column configuration drifted from the frozen UUID contract.');
        }

        if (! Schema::hasTable('model_has_permissions') || ! Schema::hasTable('model_has_roles')) {
            throw new RuntimeException('Legacy Spatie model assignment tables are required before expansion.');
        }
        if (Schema::hasTable('model_has_permissions_bigint_legacy') || Schema::hasTable('model_has_roles_bigint_legacy')) {
            throw new RuntimeException('A legacy Spatie backup table is already occupied.');
        }

        $permissionShadowExists = Schema::hasTable('model_has_permissions_uuid_shadow');
        $roleShadowExists = Schema::hasTable('model_has_roles_uuid_shadow');
        if ($permissionShadowExists || $roleShadowExists) {
            if (! $permissionShadowExists || ! $roleShadowExists) {
                throw new RuntimeException('Partial Spatie UUID shadow expansion is not resumable.');
            }

            return;
        }

        Schema::create('model_has_permissions_uuid_shadow', static function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->foreign('permission_id', 'spatie_mhpu_permission_fk')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
            $table->primary(
                ['permission_id', 'model_id', 'model_type'],
                'spatie_mhpu_assignment_pk',
            );
            $table->index(
                ['model_id', 'model_type'],
                'spatie_mhpu_model_lookup_idx',
            );
        });

        try {
            Schema::create('model_has_roles_uuid_shadow', static function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->uuid('model_id');
                $table->foreign('role_id', 'spatie_mhru_role_fk')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');
                $table->primary(
                    ['role_id', 'model_id', 'model_type'],
                    'spatie_mhru_assignment_pk',
                );
                $table->index(
                    ['model_id', 'model_type'],
                    'spatie_mhru_model_lookup_idx',
                );
            });
        } catch (Throwable $exception) {
            Schema::dropIfExists('model_has_permissions_uuid_shadow');

            throw $exception;
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('model_has_permissions_bigint_legacy') || Schema::hasTable('model_has_roles_bigint_legacy')) {
            throw new RuntimeException('UUID shadow rollback is blocked while a legacy backup exists.');
        }

        foreach (['model_has_permissions_uuid_shadow', 'model_has_roles_uuid_shadow'] as $table) {
            if (Schema::hasTable($table) && Schema::getConnection()->table($table)->count() > 0) {
                throw new RuntimeException('UUID shadow rollback is blocked while assignments exist.');
            }
        }

        Schema::dropIfExists('model_has_roles_uuid_shadow');
        Schema::dropIfExists('model_has_permissions_uuid_shadow');
    }
};
