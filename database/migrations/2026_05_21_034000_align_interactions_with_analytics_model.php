<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('interactions')) {
            return;
        }

        Schema::table('interactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('interactions', 'visitor_id')) {
                $table->string('visitor_id', 128)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('interactions', 'session_id')) {
                $table->string('session_id', 128)->nullable()->after('visitor_id');
            }

            if (! Schema::hasColumn('interactions', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('session_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('interactions', 'referrer')) {
                $table->string('referrer', 512)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('interactions', 'url')) {
                $table->string('url', 1024)->nullable()->after('referrer');
            }
        });

        Schema::table('interactions', function (Blueprint $table): void {
            if (! $this->indexExists('interactions', 'interactions_tenant_session_type_created_idx')) {
                $table->index(['tenant_id', 'session_id', 'type', 'created_at'], 'interactions_tenant_session_type_created_idx');
            }

            if (! $this->indexExists('interactions', 'interactions_product_created_idx')) {
                $table->index(['product_id', 'created_at'], 'interactions_product_created_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('interactions')) {
            return;
        }

        Schema::table('interactions', function (Blueprint $table): void {
            if ($this->indexExists('interactions', 'interactions_product_created_idx')) {
                $table->dropIndex('interactions_product_created_idx');
            }

            if ($this->indexExists('interactions', 'interactions_tenant_session_type_created_idx')) {
                $table->dropIndex('interactions_tenant_session_type_created_idx');
            }
        });

        Schema::table('interactions', function (Blueprint $table): void {
            foreach (['url', 'referrer', 'product_id', 'session_id', 'visitor_id'] as $column) {
                if (Schema::hasColumn('interactions', $column)) {
                    if ($column === 'product_id' && $this->indexExists('interactions', 'interactions_product_id_foreign')) {
                        $table->dropForeign('interactions_product_id_foreign');
                    }

                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
