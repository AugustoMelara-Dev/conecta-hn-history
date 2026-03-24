<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->uuidMorphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumns('notifications', ['notifiable_id', 'notifiable_type'])) {
            return;
        }

        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $columnType = Schema::getColumnType('notifications', 'notifiable_id');

        if (in_array($columnType, ['char', 'string', 'uuid'], true)) {
            return;
        }

        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
            });
        } catch (\Throwable) {
            // Index may not exist in every environment.
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE notifications MODIFY notifiable_id CHAR(36) NOT NULL');
            DB::statement('ALTER TABLE notifications MODIFY notifiable_type VARCHAR(255) NOT NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE VARCHAR(36)');
            DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_type TYPE VARCHAR(255)');
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        });
    }

    public function down(): void
    {
        // No destructive rollback: reverting UUID morphs to integers would risk data corruption.
    }
};
