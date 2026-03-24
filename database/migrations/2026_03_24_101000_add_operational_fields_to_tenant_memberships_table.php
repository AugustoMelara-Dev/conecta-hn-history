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
            $table->string('preset_key', 64)->nullable()->after('role');
            $table->json('granted_permissions')->nullable()->after('preset_key');

            $table->index(['tenant_id', 'preset_key']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'preset_key']);
            $table->dropColumn(['preset_key', 'granted_permissions']);
        });
    }
};
