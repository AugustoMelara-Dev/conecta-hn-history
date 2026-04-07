<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_memberships', 'preset_key')) {
                $table->string('preset_key', 64)->nullable()->after('role');
            }

            if (! Schema::hasColumn('tenant_memberships', 'granted_permissions')) {
                $table->json('granted_permissions')->nullable()->after('preset_key');
            }

            if (! Schema::hasColumn('tenant_memberships', 'preset_key') || ! Schema::hasColumn('tenant_memberships', 'granted_permissions')) {
                $table->index(['tenant_id', 'preset_key']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_memberships', 'preset_key')) {
                $table->dropIndex(['tenant_id', 'preset_key']);
            }

            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('tenant_memberships', 'preset_key') ? 'preset_key' : null,
                Schema::hasColumn('tenant_memberships', 'granted_permissions') ? 'granted_permissions' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
