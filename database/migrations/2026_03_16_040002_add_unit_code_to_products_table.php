<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'unit_code')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->string('unit_code')->default('pcs')->after('manage_stock');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'unit_code')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('unit_code');
        });
    }
};
