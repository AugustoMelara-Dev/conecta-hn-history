<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'tenant_id') || ! Schema::hasColumn('orders', 'order_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if ($this->indexExists('orders', 'orders_order_number_unique')) {
                $table->dropUnique('orders_order_number_unique');
            }

            if (! $this->indexExists('orders', 'orders_tenant_order_number_unique')) {
                $table->unique(['tenant_id', 'order_number'], 'orders_tenant_order_number_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'order_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if ($this->indexExists('orders', 'orders_tenant_order_number_unique')) {
                $table->dropUnique('orders_tenant_order_number_unique');
            }

            if (! $this->indexExists('orders', 'orders_order_number_unique')) {
                $table->unique('order_number', 'orders_order_number_unique');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
