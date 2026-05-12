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
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('outbox_messages', 'event_type')) {
                $table->string('event_type')->nullable()->after('type');
            }

            if (! Schema::hasColumn('outbox_messages', 'processed')) {
                $table->boolean('processed')->default(false)->after('status')->index();
            }

            if (! Schema::hasColumn('outbox_messages', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('published_at')->index();
            }

            if (! Schema::hasColumn('outbox_messages', 'error')) {
                $table->text('error')->nullable()->after('last_error');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS outbox_messages_processed_at_index');
            DB::statement('DROP INDEX IF EXISTS outbox_messages_processed_index');
        }

        foreach (['error', 'processed_at', 'processed', 'event_type'] as $column) {
            if (! Schema::hasColumn('outbox_messages', $column)) {
                continue;
            }

            Schema::table('outbox_messages', function (Blueprint $table) use ($column): void {
                if (DB::getDriverName() !== 'sqlite' && $column === 'processed_at') {
                    try {
                        $table->dropIndex('outbox_messages_processed_at_index');
                    } catch (\Throwable) {
                        // Already absent in some local/test schemas.
                    }
                }

                if (DB::getDriverName() !== 'sqlite' && $column === 'processed') {
                    try {
                        $table->dropIndex('outbox_messages_processed_index');
                    } catch (\Throwable) {
                        // Already absent in some local/test schemas.
                    }
                }

                $table->dropColumn($column);
            });
        }
    }
};
