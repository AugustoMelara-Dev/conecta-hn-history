<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'address')) {
                $table->string('address')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'whatsapp_message')) {
                $table->text('whatsapp_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $columnsToDrop = [];

            if (Schema::hasColumn('tenants', 'address')) {
                $columnsToDrop[] = 'address';
            }

            if (Schema::hasColumn('tenants', 'whatsapp_message')) {
                $columnsToDrop[] = 'whatsapp_message';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
