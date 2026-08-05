<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verified_commercial_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('aggregate_type', 32);
            $table->string('aggregate_id', 64);
            $table->string('outcome', 64);
            $table->string('projection_key', 191)->unique();
            $table->bigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->nullable();
            $table->timestamp('source_occurred_at');
            $table->unsignedSmallInteger('source_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'outcome', 'source_occurred_at'],
                'verified_outcomes_tenant_type_occurred_idx',
            );
            $table->index(
                ['tenant_id', 'aggregate_type', 'aggregate_id'],
                'verified_outcomes_tenant_aggregate_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_commercial_outcomes');
    }
};
