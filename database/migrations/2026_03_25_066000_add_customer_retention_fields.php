<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_profiles') && ! Schema::hasColumn('customer_profiles', 'lifetime_points_earned')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->unsignedInteger('lifetime_points_earned')->default(0)->after('loyalty_points_balance');
                $table->unsignedInteger('redeemable_rewards_count')->default(0)->after('loyalty_tier');
                $table->unsignedInteger('points_to_next_reward')->default(0)->after('redeemable_rewards_count');
                $table->string('next_reward_name')->nullable()->after('points_to_next_reward');
                $table->timestamp('last_loyalty_activity_at')->nullable()->after('last_inbound_reply_at');

                $table->index(['tenant_id', 'redeemable_rewards_count'], 'customer_profiles_tenant_redeemable_idx');
            });
        }

        if (Schema::hasTable('customer_notes') && ! Schema::hasColumn('customer_notes', 'conversation_key')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                $table->uuid('conversation_key')->nullable()->after('note_type');
                $table->timestamp('response_due_at')->nullable()->after('next_action_at');
                $table->timestamp('first_response_at')->nullable()->after('response_due_at');
                $table->timestamp('last_customer_message_at')->nullable()->after('first_response_at');
                $table->timestamp('last_team_message_at')->nullable()->after('last_customer_message_at');
                $table->unsignedInteger('reply_count')->default(1)->after('requires_follow_up');

                $table->index(['tenant_id', 'status', 'response_due_at'], 'customer_notes_tenant_response_idx');
                $table->index(['tenant_id', 'conversation_key'], 'customer_notes_conversation_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_notes') && Schema::hasColumn('customer_notes', 'conversation_key')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                $table->dropIndex('customer_notes_tenant_response_idx');
                $table->dropIndex('customer_notes_conversation_idx');
                $table->dropColumn([
                    'conversation_key',
                    'response_due_at',
                    'first_response_at',
                    'last_customer_message_at',
                    'last_team_message_at',
                    'reply_count',
                ]);
            });
        }

        if (Schema::hasTable('customer_profiles') && Schema::hasColumn('customer_profiles', 'lifetime_points_earned')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->dropIndex('customer_profiles_tenant_redeemable_idx');
                $table->dropColumn([
                    'lifetime_points_earned',
                    'redeemable_rewards_count',
                    'points_to_next_reward',
                    'next_reward_name',
                    'last_loyalty_activity_at',
                ]);
            });
        }
    }
};
