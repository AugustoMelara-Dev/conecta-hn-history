<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_assistant_runs', function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->after('assistant_key');
            $table->unique(['tenant_id', 'assistant_key', 'correlation_id'], 'ai_assistant_runs_correlation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ai_assistant_runs', function (Blueprint $table): void {
            $table->dropUnique('ai_assistant_runs_correlation_unique');
            $table->dropColumn('correlation_id');
        });
    }
};
