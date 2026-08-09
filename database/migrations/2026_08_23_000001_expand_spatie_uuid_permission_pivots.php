<?php

declare(strict_types=1);

use App\Application\Identity\Permissions\PermissionModelKeyMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(PermissionModelKeyMigration::class);
        if (! $service->authorizeExpansionMigration()) {
            return;
        }

        try {
            Schema::create('model_has_permissions_uuid_shadow', static function (Blueprint $table): void {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->uuid('model_id');
                $table->foreign('permission_id', 'spatie_mhpu_permission_fk')->references('id')->on('permissions')->onDelete('cascade');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'spatie_mhpu_assignment_pk');
                $table->index(['model_id', 'model_type'], 'spatie_mhpu_model_lookup_idx');
            });
            Schema::create('model_has_roles_uuid_shadow', static function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->uuid('model_id');
                $table->foreign('role_id', 'spatie_mhru_role_fk')->references('id')->on('roles')->onDelete('cascade');
                $table->primary(['role_id', 'model_id', 'model_type'], 'spatie_mhru_assignment_pk');
                $table->index(['model_id', 'model_type'], 'spatie_mhru_model_lookup_idx');
            });
            $service->recordExpansionOwnershipMigration();
        } catch (Throwable $exception) {
            Schema::dropIfExists('spatie_uuid_permission_pivot_ownership');
            Schema::dropIfExists('model_has_roles_uuid_shadow');
            Schema::dropIfExists('model_has_permissions_uuid_shadow');

            throw $exception;
        }
    }

    public function down(): void
    {
        $service = app(PermissionModelKeyMigration::class);
        if (! $service->authorizeRollbackMigration()) {
            return;
        }

        Schema::dropIfExists('model_has_roles_uuid_shadow');
        Schema::dropIfExists('model_has_permissions_uuid_shadow');
        Schema::dropIfExists('spatie_uuid_permission_pivot_ownership');
    }
};
