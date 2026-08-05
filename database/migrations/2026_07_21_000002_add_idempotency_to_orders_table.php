<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable();
            $table->char('idempotency_hash', 64)->nullable();
            $table->unique(['tenant_id', 'idempotency_key'], 'orders_tenant_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_tenant_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'idempotency_hash']);
        });
    }
};
