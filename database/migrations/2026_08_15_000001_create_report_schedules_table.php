<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_key', 100);
            $table->string('frequency', 20); // daily/weekly/monthly
            $table->json('recipients')->nullable(); // array of emails
            $table->json('filters')->nullable(); // optional report filters
            $table->string('format', 10)->default('pdf'); // pdf/xlsx/csv
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['next_run_at', 'is_active']);
            $table->index(['report_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
