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
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('users') || ! Schema::hasColumn('tenants', 'user_id')) {
            return;
        }

        DB::table('tenants')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'tenants.user_id');
            })
            ->update(['user_id' => null]);

        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreign('user_id', 'tenants_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'user_id')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropForeign('tenants_user_id_foreign');
        });
    }
};
