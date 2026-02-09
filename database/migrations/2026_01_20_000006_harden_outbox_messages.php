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

        Schema::table('outbox_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('outbox_messages', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0);
            }
            if (! Schema::hasColumn('outbox_messages', 'failed_at')) {
                $table->timestamp('failed_at')->nullable();
            }
            if (! Schema::hasColumn('outbox_messages', 'last_error')) {
                $table->text('last_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'failed_at', 'last_error']);
        });
    }
};
