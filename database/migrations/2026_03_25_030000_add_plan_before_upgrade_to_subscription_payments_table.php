<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_payments', 'plan_before_upgrade')) {
                $table->string('plan_before_upgrade')->nullable()->after('subscription_extended_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('subscription_payments', 'plan_before_upgrade')) {
                $table->dropColumn('plan_before_upgrade');
            }
        });
    }
};
