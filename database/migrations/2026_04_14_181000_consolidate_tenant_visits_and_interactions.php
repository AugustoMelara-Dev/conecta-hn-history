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
        $this->alignTenantVisits();
        $this->alignInteractions();
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_visits') && Schema::hasTable('tenant_visits_legacy_snapshot')) {
            DB::table('tenant_visits_legacy_snapshot')
                ->orderBy('visit_id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('tenant_visits')
                            ->where('id', $row->visit_id)
                            ->update([
                                'visited_at' => $row->old_visited_at,
                            ]);
                    }
                }, 'visit_id');
        }

        if (Schema::hasTable('interactions') && Schema::hasTable('interactions_legacy_snapshot')) {
            DB::table('interactions_legacy_snapshot')
                ->orderBy('interaction_id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('interactions')
                            ->where('id', $row->interaction_id)
                            ->update([
                                'visitor_id' => $row->old_visitor_id,
                                'session_id' => $row->old_session_id,
                            ]);
                    }
                }, 'interaction_id');
        }
    }

    private function alignTenantVisits(): void
    {
        if (! Schema::hasTable('tenant_visits')) {
            return;
        }

        if (! Schema::hasTable('tenant_visits_legacy_snapshot')) {
            Schema::create('tenant_visits_legacy_snapshot', function (Blueprint $table): void {
                $table->unsignedBigInteger('visit_id')->primary();
                $table->timestamp('old_visited_at')->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        Schema::table('tenant_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_visits', 'session_hash')) {
                $table->string('session_hash', 64)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('tenant_visits', 'referer')) {
                $table->string('referer', 512)->nullable()->after('session_hash');
            }

            if (! Schema::hasColumn('tenant_visits', 'device_type')) {
                $table->string('device_type', 20)->default('desktop')->after('referer');
            }

            if (! Schema::hasColumn('tenant_visits', 'visited_at')) {
                $table->timestamp('visited_at')->nullable()->after('device_type');
            }
        });

        DB::table('tenant_visits_legacy_snapshot')->insertUsing(
            ['visit_id', 'old_visited_at', 'captured_at'],
            DB::table('tenant_visits')
                ->selectRaw('id, visited_at, NOW()')
                ->whereNull('visited_at')
                ->whereNotIn('id', DB::table('tenant_visits_legacy_snapshot')->select('visit_id'))
        );

        DB::table('tenant_visits')
            ->whereNull('visited_at')
            ->update([
                'visited_at' => DB::raw('created_at'),
            ]);

        if (! $this->hasIndex('tenant_visits', 'idx_tenant_visits_lookup')) {
            Schema::table('tenant_visits', function (Blueprint $table): void {
                $table->index(['tenant_id', 'session_hash'], 'idx_tenant_visits_lookup');
            });
        }

        if (! $this->hasIndex('tenant_visits', 'idx_tenant_visits_tenant_date')) {
            Schema::table('tenant_visits', function (Blueprint $table): void {
                $table->index(['tenant_id', 'created_at'], 'idx_tenant_visits_tenant_date');
            });
        }
    }

    private function alignInteractions(): void
    {
        if (! Schema::hasTable('interactions')) {
            return;
        }

        if (! Schema::hasTable('interactions_legacy_snapshot')) {
            Schema::create('interactions_legacy_snapshot', function (Blueprint $table): void {
                $table->unsignedBigInteger('interaction_id')->primary();
                $table->string('old_visitor_id', 100)->nullable();
                $table->string('old_session_id', 100)->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        Schema::table('interactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('interactions', 'visitor_id')) {
                $table->string('visitor_id', 100)->nullable()->after('tenant_id');
            }

            if (! Schema::hasColumn('interactions', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('visitor_id');
            }

            if (! Schema::hasColumn('interactions', 'referrer')) {
                $table->string('referrer', 1024)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('interactions', 'url')) {
                $table->string('url', 1024)->nullable()->after('referrer');
            }
        });

        DB::table('interactions_legacy_snapshot')->insertUsing(
            ['interaction_id', 'old_visitor_id', 'old_session_id', 'captured_at'],
            DB::table('interactions')
                ->selectRaw('id, visitor_id, session_id, NOW()')
                ->where(function ($query): void {
                    $query->whereNull('visitor_id')
                        ->orWhereNull('session_id');
                })
                ->whereNotIn('id', DB::table('interactions_legacy_snapshot')->select('interaction_id'))
        );

        $driver = DB::getDriverName();
        $jsonSessionExpression = $driver === 'sqlite'
            ? "json_extract(metadata, '$.session_id')"
            : "JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.session_id'))";

        DB::statement("UPDATE interactions
            SET session_id = {$jsonSessionExpression}
            WHERE session_id IS NULL
              AND metadata IS NOT NULL");

        DB::table('interactions')
            ->whereNull('visitor_id')
            ->update([
                'visitor_id' => DB::raw('session_id'),
            ]);

        if (! $this->hasIndex('interactions', 'interactions_tenant_visitor_type_created_idx')) {
            Schema::table('interactions', function (Blueprint $table): void {
                $table->index(['tenant_id', 'visitor_id', 'type', 'created_at'], 'interactions_tenant_visitor_type_created_idx');
            });
        }

        if (! $this->hasIndex('interactions', 'interactions_tenant_session_idx')) {
            Schema::table('interactions', function (Blueprint $table): void {
                $table->index(['tenant_id', 'session_id'], 'interactions_tenant_session_idx');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = collect(Schema::getIndexes($table));

        return $indexes->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $indexName);
    }
};
