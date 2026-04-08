<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_profiles')) {
            $this->addCustomerProfileRetentionColumns();

            if (
                Schema::hasColumn('customer_profiles', 'tenant_id')
                && Schema::hasColumn('customer_profiles', 'redeemable_rewards_count')
                && ! $this->indexExists('customer_profiles', 'customer_profiles_tenant_redeemable_idx')
            ) {
                Schema::table('customer_profiles', function (Blueprint $table): void {
                    $table->index(['tenant_id', 'redeemable_rewards_count'], 'customer_profiles_tenant_redeemable_idx');
                });
            }
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
                if ($this->indexExists('customer_profiles', 'customer_profiles_tenant_redeemable_idx')) {
                    $table->dropIndex('customer_profiles_tenant_redeemable_idx');
                }
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

    private function addCustomerProfileRetentionColumns(): void
    {
        if (! Schema::hasColumn('customer_profiles', 'lifetime_points_earned')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'loyalty_points_balance')) {
                    $table->unsignedInteger('lifetime_points_earned')->default(0)->after('loyalty_points_balance');
                } else {
                    $table->unsignedInteger('lifetime_points_earned')->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'redeemable_rewards_count')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'loyalty_tier')) {
                    $table->unsignedInteger('redeemable_rewards_count')->default(0)->after('loyalty_tier');
                } elseif (Schema::hasColumn('customer_profiles', 'lifetime_points_earned')) {
                    $table->unsignedInteger('redeemable_rewards_count')->default(0)->after('lifetime_points_earned');
                } else {
                    $table->unsignedInteger('redeemable_rewards_count')->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'points_to_next_reward')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'redeemable_rewards_count')) {
                    $table->unsignedInteger('points_to_next_reward')->default(0)->after('redeemable_rewards_count');
                } else {
                    $table->unsignedInteger('points_to_next_reward')->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'next_reward_name')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'points_to_next_reward')) {
                    $table->string('next_reward_name')->nullable()->after('points_to_next_reward');
                } else {
                    $table->string('next_reward_name')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'last_inbound_reply_at')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'last_reactivated_at')) {
                    $table->timestamp('last_inbound_reply_at')->nullable()->after('last_reactivated_at');
                } else {
                    $table->timestamp('last_inbound_reply_at')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'last_loyalty_activity_at')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'last_inbound_reply_at')) {
                    $table->timestamp('last_loyalty_activity_at')->nullable()->after('last_inbound_reply_at');
                } else {
                    $table->timestamp('last_loyalty_activity_at')->nullable();
                }
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return $result !== [];
    }
};
