<?php

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

        $hasAttempts = Schema::hasColumn('outbox_messages', 'attempts');
        $hasFailedAt = Schema::hasColumn('outbox_messages', 'failed_at');
        $hasLastError = Schema::hasColumn('outbox_messages', 'last_error');

        if ($hasAttempts && $hasFailedAt && $hasLastError) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table) use ($hasAttempts, $hasFailedAt, $hasLastError) {
            if (! $hasAttempts) {
                $table->unsignedInteger('attempts')->default(0);
            }
            if (! $hasFailedAt) {
                $table->timestamp('failed_at')->nullable();
            }
            if (! $hasLastError) {
                $table->text('last_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('outbox_messages', 'attempts') ? 'attempts' : null,
            Schema::hasColumn('outbox_messages', 'failed_at') ? 'failed_at' : null,
            Schema::hasColumn('outbox_messages', 'last_error') ? 'last_error' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
