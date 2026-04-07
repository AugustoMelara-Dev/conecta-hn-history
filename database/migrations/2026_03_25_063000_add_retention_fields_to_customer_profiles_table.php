<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles') || Schema::hasColumn('customer_profiles', 'acquisition_channel')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->string('acquisition_channel', 32)->nullable()->after('email_opt_in');
            $table->string('first_conversion_channel', 32)->nullable()->after('acquisition_channel');
            $table->decimal('first_conversion_value', 12, 2)->default(0)->after('first_conversion_channel');
            $table->decimal('initial_ltv', 12, 2)->default(0)->after('first_conversion_value');
            $table->unsignedInteger('repeat_purchase_count')->default(0)->after('appointments_count');
            $table->unsignedInteger('loyalty_points_balance')->default(0)->after('repeat_purchase_count');
            $table->string('loyalty_tier', 32)->default('base')->after('loyalty_points_balance');
            $table->string('last_engaged_channel', 32)->nullable()->after('last_contacted_at');
            $table->timestamp('last_reactivated_at')->nullable()->after('last_engaged_channel');
            $table->timestamp('last_inbound_reply_at')->nullable()->after('last_reactivated_at');

            $table->index(['tenant_id', 'acquisition_channel'], 'customer_profiles_tenant_acquisition_idx');
            $table->index(['tenant_id', 'loyalty_tier'], 'customer_profiles_tenant_loyalty_idx');
            $table->index(['tenant_id', 'last_reactivated_at'], 'customer_profiles_tenant_reactivated_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_profiles') || ! Schema::hasColumn('customer_profiles', 'acquisition_channel')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->dropIndex('customer_profiles_tenant_acquisition_idx');
            $table->dropIndex('customer_profiles_tenant_loyalty_idx');
            $table->dropIndex('customer_profiles_tenant_reactivated_idx');

            $table->dropColumn([
                'acquisition_channel',
                'first_conversion_channel',
                'first_conversion_value',
                'initial_ltv',
                'repeat_purchase_count',
                'loyalty_points_balance',
                'loyalty_tier',
                'last_engaged_channel',
                'last_reactivated_at',
                'last_inbound_reply_at',
            ]);
        });
    }
};
