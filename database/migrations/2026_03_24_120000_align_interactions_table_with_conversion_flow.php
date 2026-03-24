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
        if (! Schema::hasTable('interactions')) {
            return;
        }

        $addedVisitorId = false;
        $addedSessionId = false;

        Schema::table('interactions', function (Blueprint $table) use (&$addedVisitorId, &$addedSessionId): void {
            if (! Schema::hasColumn('interactions', 'visitor_id')) {
                $table->string('visitor_id', 100)->nullable()->after('tenant_id');
                $addedVisitorId = true;
            }

            if (! Schema::hasColumn('interactions', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('visitor_id');
                $addedSessionId = true;
            }

            if (! Schema::hasColumn('interactions', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('session_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('interactions', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('product_id');
            }

            if (! Schema::hasColumn('interactions', 'user_agent')) {
                $table->string('user_agent', 1024)->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('interactions', 'referrer')) {
                $table->string('referrer', 1024)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('interactions', 'url')) {
                $table->string('url', 1024)->nullable()->after('referrer');
            }

            if ($addedVisitorId) {
                $table->index(['tenant_id', 'visitor_id', 'type', 'created_at'], 'interactions_tenant_visitor_type_created_idx');
            }

            if ($addedSessionId) {
                $table->index(['tenant_id', 'session_id'], 'interactions_tenant_session_idx');
            }
        });

        if (Schema::hasColumn('interactions', 'session_id')) {
            DB::statement("
                UPDATE interactions
                SET session_id = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.session_id'))
                WHERE session_id IS NULL
                  AND metadata IS NOT NULL
                  AND JSON_EXTRACT(metadata, '$.session_id') IS NOT NULL
            ");
        }

        if (Schema::hasColumn('interactions', 'visitor_id')) {
            DB::statement("
                UPDATE interactions
                SET visitor_id = COALESCE(visitor_id, session_id, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.session_id')))
                WHERE visitor_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('interactions')) {
            return;
        }

        Schema::table('interactions', function (Blueprint $table): void {
            if (Schema::hasColumn('interactions', 'visitor_id')) {
                $table->dropIndex('interactions_tenant_visitor_type_created_idx');
            }

            if (Schema::hasColumn('interactions', 'session_id')) {
                $table->dropIndex('interactions_tenant_session_idx');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('interactions', 'visitor_id') ? 'visitor_id' : null,
                Schema::hasColumn('interactions', 'session_id') ? 'session_id' : null,
                Schema::hasColumn('interactions', 'product_id') ? 'product_id' : null,
                Schema::hasColumn('interactions', 'referrer') ? 'referrer' : null,
                Schema::hasColumn('interactions', 'url') ? 'url' : null,
            ]));

            if ($columns !== []) {
                if (in_array('product_id', $columns, true)) {
                    $table->dropForeign(['product_id']);
                }

                $table->dropColumn($columns);
            }
        });
    }
};
