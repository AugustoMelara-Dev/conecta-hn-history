<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('one_time_passwords') || ! Schema::hasTable('tenants')) {
            return;
        }

        // OTPs are ephemeral. Clearing pending legacy codes provides a safe,
        // deterministic cutover before adding replay and uniqueness constraints.
        DB::table('one_time_passwords')->delete();

        Schema::table('one_time_passwords', function (Blueprint $table): void {
            $table->timestamp('used_at')->nullable()->after('expires_at');
            $table->unique(['tenant_id', 'identifier'], 'otp_tenant_identifier_unique');
            $table->foreign('tenant_id', 'otp_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('one_time_passwords')) {
            return;
        }

        Schema::table('one_time_passwords', function (Blueprint $table): void {
            $table->dropForeign('otp_tenant_fk');
            $table->dropUnique('otp_tenant_identifier_unique');
            $table->dropColumn('used_at');
        });
    }
};
