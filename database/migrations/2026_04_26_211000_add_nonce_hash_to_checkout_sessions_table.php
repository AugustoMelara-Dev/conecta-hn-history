<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checkout_sessions') || Schema::hasColumn('checkout_sessions', 'nonce_hash')) {
            return;
        }

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->string('nonce_hash', 128)->nullable()->after('session_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('checkout_sessions') || ! Schema::hasColumn('checkout_sessions', 'nonce_hash')) {
            return;
        }

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->dropColumn('nonce_hash');
        });
    }
};
