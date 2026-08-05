<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->string('aggregate_id', 191)
                ->nullable()
                ->comment('Tenant-safe canonical aggregate key')
                ->change();
        });
        Schema::table('outbox_sequences', function (Blueprint $table): void {
            $table->string('aggregate_id', 191)
                ->comment('Tenant-safe canonical aggregate key')
                ->change();
        });
    }

    public function down(): void
    {
        // Expand-only: shrinking can truncate canonical type-prefixed UUID keys.
    }
};
