<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interactions')) {
            $missingInteractionColumns = $this->missingColumns('interactions');

            Schema::table('interactions', function (Blueprint $table): void {
                foreach ($this->attributionColumns() as $column => $length) {
                    if (! Schema::hasColumn('interactions', $column)) {
                        $table->string($column, $length)->nullable()->after('url');
                    }
                }
            });

            if ($missingInteractionColumns !== []) {
                Schema::table('interactions', function (Blueprint $table): void {
                    $table->index(['tenant_id', 'utm_source', 'type', 'created_at'], 'interactions_tenant_utm_type_created_idx');
                });
            }
        }

        if (Schema::hasTable('leads')) {
            $missingLeadColumns = $this->missingColumns('leads');

            Schema::table('leads', function (Blueprint $table): void {
                foreach ($this->attributionColumns() as $column => $length) {
                    if (! Schema::hasColumn('leads', $column)) {
                        $table->string($column, $length)->nullable()->after('session_id');
                    }
                }
            });

            if ($missingLeadColumns !== []) {
                Schema::table('leads', function (Blueprint $table): void {
                    $table->index(['tenant_id', 'utm_source', 'source', 'created_at'], 'leads_tenant_utm_source_created_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('interactions') && Schema::hasColumn('interactions', 'utm_source')) {
            Schema::table('interactions', function (Blueprint $table): void {
                $table->dropIndex('interactions_tenant_utm_type_created_idx');
                $table->dropColumn(array_keys($this->attributionColumns()));
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'utm_source')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->dropIndex('leads_tenant_utm_source_created_idx');
                $table->dropColumn(array_keys($this->attributionColumns()));
            });
        }
    }

    /**
     * @return array<string, int>
     */
    private function attributionColumns(): array
    {
        return [
            'utm_source' => 100,
            'utm_medium' => 100,
            'utm_campaign' => 150,
            'utm_term' => 150,
            'utm_content' => 150,
        ];
    }

    /**
     * @return list<string>
     */
    private function missingColumns(string $table): array
    {
        return array_values(array_filter(
            array_keys($this->attributionColumns()),
            static fn (string $column): bool => ! Schema::hasColumn($table, $column),
        ));
    }
};
