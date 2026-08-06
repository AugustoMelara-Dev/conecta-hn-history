<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assistant_key', 100);
            $table->text('prompt')->nullable();
            $table->text('response')->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->string('status', 20)->default('completed'); // pending/running/completed/failed
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'assistant_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_runs');
    }
};
