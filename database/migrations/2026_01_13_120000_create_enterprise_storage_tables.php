<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. SNAPSHOTS (The Snapshot Rule)
        // 1. SNAPSHOTS (The Snapshot Rule)
        if (! Schema::hasTable('snapshots')) {
            Schema::create('snapshots', function (Blueprint $table) {
                $table->uuid('aggregate_uuid')->primary(); // Quick Access Strategy
                $table->string('aggregate_type');
                $table->unsignedInteger('aggregate_version');
                $table->json('state'); // Compressed State
                $table->timestamp('created_at')->useCurrent();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');

    }
};
