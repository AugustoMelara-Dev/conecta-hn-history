<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('write_idempotency_key', 191)->nullable()->after('slug');
            $table->char('write_payload_hash', 64)->nullable()->after('write_idempotency_key');
            $table->unique(['tenant_id', 'write_idempotency_key'], 'products_tenant_write_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_tenant_write_idempotency_unique');
            $table->dropColumn(['write_idempotency_key', 'write_payload_hash']);
        });
    }
};
