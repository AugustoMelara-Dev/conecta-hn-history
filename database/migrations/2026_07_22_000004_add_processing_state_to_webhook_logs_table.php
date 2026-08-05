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
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->string('status', 20)->default('processing')->index();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->uuid('processing_token')->nullable();
        });

        DB::table('webhook_logs')->orderBy('id')->each(function (object $webhookLog): void {
            DB::table('webhook_logs')->where('id', $webhookLog->id)->update([
                'status' => 'processed',
                'attempts' => 1,
                'processing_started_at' => $webhookLog->created_at,
                'processed_at' => $webhookLog->created_at,
                // Historical JSON representation varies between SQLite/MySQL.
                // Mark it terminal without comparing a non-canonical hash later.
                'payload_hash' => null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'attempts',
                'processing_started_at',
                'processed_at',
                'failed_at',
                'last_error',
                'payload_hash',
                'processing_token',
            ]);
        });
    }
};
