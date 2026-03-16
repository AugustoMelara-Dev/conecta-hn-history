<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('one_time_passwords')) {
            return;
        }

        DB::statement('ALTER TABLE one_time_passwords MODIFY expires_at DATETIME NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('one_time_passwords')) {
            return;
        }

        DB::statement('ALTER TABLE one_time_passwords MODIFY expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }
};
