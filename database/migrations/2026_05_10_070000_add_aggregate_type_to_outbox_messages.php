<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('outbox_messages', 'aggregate_type')) {
                $table->string('aggregate_type')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('outbox_messages', 'aggregate_type')) {
                $table->dropColumn('aggregate_type');
            }
        });
    }
};
