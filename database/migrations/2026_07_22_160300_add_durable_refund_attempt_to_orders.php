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
            $table->string('refund_idempotency_key', 191)->nullable()->unique()->after('refund_transaction_id');
            $table->timestamp('refund_attempted_at')->nullable()->after('refund_idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['refund_idempotency_key']);
            $table->dropColumn(['refund_idempotency_key', 'refund_attempted_at']);
        });
    }
};
