<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_profiles') && ! Schema::hasColumn('customer_profiles', 'assigned_user_id')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->foreignUuid('assigned_user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
                $table->foreignUuid('location_id')->nullable()->after('assigned_user_id')->constrained('locations')->nullOnDelete();

                $table->index(['tenant_id', 'assigned_user_id'], 'customer_profiles_tenant_assigned_idx');
                $table->index(['tenant_id', 'location_id'], 'customer_profiles_tenant_location_idx');
            });
        }

        if (Schema::hasTable('customer_notes') && ! Schema::hasColumn('customer_notes', 'assigned_user_id')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                $table->foreignUuid('assigned_user_id')->nullable()->after('handled_by_user_id')->constrained('users')->nullOnDelete();
                $table->foreignUuid('location_id')->nullable()->after('assigned_user_id')->constrained('locations')->nullOnDelete();

                $table->index(['tenant_id', 'assigned_user_id', 'status'], 'customer_notes_tenant_assigned_status_idx');
                $table->index(['tenant_id', 'location_id', 'status'], 'customer_notes_tenant_location_status_idx');
            });
        }

        if (Schema::hasTable('campaigns') && ! Schema::hasColumn('campaigns', 'customer_segment')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->string('customer_segment', 32)->nullable()->after('audience_type');
                $table->string('loyalty_tier', 32)->nullable()->after('customer_segment');
                $table->boolean('only_redeemable_rewards')->default(false)->after('loyalty_tier');
                $table->foreignUuid('location_id')->nullable()->after('only_redeemable_rewards')->constrained('locations')->nullOnDelete();
                $table->foreignUuid('owner_user_id')->nullable()->after('location_id')->constrained('users')->nullOnDelete();
                $table->boolean('requires_marketing_opt_in')->default(true)->after('owner_user_id');
                $table->string('whatsapp_template_name')->nullable()->after('requires_marketing_opt_in');
                $table->string('whatsapp_template_language', 16)->nullable()->after('whatsapp_template_name');

                $table->index(['tenant_id', 'customer_segment'], 'campaigns_tenant_segment_idx');
                $table->index(['tenant_id', 'loyalty_tier'], 'campaigns_tenant_loyalty_idx');
                $table->index(['tenant_id', 'location_id'], 'campaigns_tenant_location_idx');
                $table->index(['tenant_id', 'owner_user_id'], 'campaigns_tenant_owner_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'customer_segment')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->dropIndex('campaigns_tenant_segment_idx');
                $table->dropIndex('campaigns_tenant_loyalty_idx');
                $table->dropIndex('campaigns_tenant_location_idx');
                $table->dropIndex('campaigns_tenant_owner_idx');

                $table->dropForeign(['location_id']);
                $table->dropForeign(['owner_user_id']);

                $table->dropColumn([
                    'customer_segment',
                    'loyalty_tier',
                    'only_redeemable_rewards',
                    'location_id',
                    'owner_user_id',
                    'requires_marketing_opt_in',
                    'whatsapp_template_name',
                    'whatsapp_template_language',
                ]);
            });
        }

        if (Schema::hasTable('customer_notes') && Schema::hasColumn('customer_notes', 'assigned_user_id')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                $table->dropIndex('customer_notes_tenant_assigned_status_idx');
                $table->dropIndex('customer_notes_tenant_location_status_idx');

                $table->dropForeign(['assigned_user_id']);
                $table->dropForeign(['location_id']);

                $table->dropColumn([
                    'assigned_user_id',
                    'location_id',
                ]);
            });
        }

        if (Schema::hasTable('customer_profiles') && Schema::hasColumn('customer_profiles', 'assigned_user_id')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->dropIndex('customer_profiles_tenant_assigned_idx');
                $table->dropIndex('customer_profiles_tenant_location_idx');

                $table->dropForeign(['assigned_user_id']);
                $table->dropForeign(['location_id']);

                $table->dropColumn([
                    'assigned_user_id',
                    'location_id',
                ]);
            });
        }
    }
};
