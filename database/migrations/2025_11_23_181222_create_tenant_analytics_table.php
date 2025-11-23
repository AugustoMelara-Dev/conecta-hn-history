<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('date')->index();
            $table->bigInteger('unique_visits')->default(0);
            $table->bigInteger('total_visits')->default(0);
            $table->timestamps();

            // Composite index for fast range queries
            $table->index(['tenant_id', 'date']);
            
            // Unique constraint to prevent duplicates
            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_analytics');
    }
};
