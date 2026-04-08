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
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        $this->addRetentionColumns();
        $this->ensureIndexes();
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

    private function addRetentionColumns(): void
    {
        if (! Schema::hasColumn('customer_profiles', 'acquisition_channel')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'email_opt_in')) {
                    $table->string('acquisition_channel', 32)->nullable()->after('email_opt_in');
                } else {
                    $table->string('acquisition_channel', 32)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'first_conversion_channel')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'acquisition_channel')) {
                    $table->string('first_conversion_channel', 32)->nullable()->after('acquisition_channel');
                } else {
                    $table->string('first_conversion_channel', 32)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'first_conversion_value')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'first_conversion_channel')) {
                    $table->decimal('first_conversion_value', 12, 2)->default(0)->after('first_conversion_channel');
                } else {
                    $table->decimal('first_conversion_value', 12, 2)->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'initial_ltv')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'first_conversion_value')) {
                    $table->decimal('initial_ltv', 12, 2)->default(0)->after('first_conversion_value');
                } else {
                    $table->decimal('initial_ltv', 12, 2)->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'repeat_purchase_count')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'appointments_count')) {
                    $table->unsignedInteger('repeat_purchase_count')->default(0)->after('appointments_count');
                } else {
                    $table->unsignedInteger('repeat_purchase_count')->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'loyalty_points_balance')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'repeat_purchase_count')) {
                    $table->unsignedInteger('loyalty_points_balance')->default(0)->after('repeat_purchase_count');
                } else {
                    $table->unsignedInteger('loyalty_points_balance')->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'loyalty_tier')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'loyalty_points_balance')) {
                    $table->string('loyalty_tier', 32)->default('base')->after('loyalty_points_balance');
                } else {
                    $table->string('loyalty_tier', 32)->default('base');
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'last_engaged_channel')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'last_contacted_at')) {
                    $table->string('last_engaged_channel', 32)->nullable()->after('last_contacted_at');
                } else {
                    $table->string('last_engaged_channel', 32)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_profiles', 'last_reactivated_at')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_profiles', 'last_engaged_channel')) {
                    $table->timestamp('last_reactivated_at')->nullable()->after('last_engaged_channel');
                } else {
                    $table->timestamp('last_reactivated_at')->nullable();
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
    }

    private function ensureIndexes(): void
    {
        if (
            Schema::hasColumn('customer_profiles', 'tenant_id')
            && Schema::hasColumn('customer_profiles', 'acquisition_channel')
            && ! $this->indexExists('customer_profiles', 'customer_profiles_tenant_acquisition_idx')
        ) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->index(['tenant_id', 'acquisition_channel'], 'customer_profiles_tenant_acquisition_idx');
            });
        }

        if (
            Schema::hasColumn('customer_profiles', 'tenant_id')
            && Schema::hasColumn('customer_profiles', 'loyalty_tier')
            && ! $this->indexExists('customer_profiles', 'customer_profiles_tenant_loyalty_idx')
        ) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->index(['tenant_id', 'loyalty_tier'], 'customer_profiles_tenant_loyalty_idx');
            });
        }

        if (
            Schema::hasColumn('customer_profiles', 'tenant_id')
            && Schema::hasColumn('customer_profiles', 'last_reactivated_at')
            && ! $this->indexExists('customer_profiles', 'customer_profiles_tenant_reactivated_idx')
        ) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->index(['tenant_id', 'last_reactivated_at'], 'customer_profiles_tenant_reactivated_idx');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return $result !== [];
    }
};
