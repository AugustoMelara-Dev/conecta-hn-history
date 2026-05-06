<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('HNL');
            $table->string('status')->default('pending');
            $table->string('reference_id')->nullable();
            $table->string('provider')->nullable(); // bank_transfer, card
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('views')->default(0);
            $table->integer('whatsapp_clicks')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'date']);
        });

        /*
        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');
            $table->string('token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('one_time_passwords');
        Schema::dropIfExists('tenant_analytics');
        Schema::dropIfExists('payments');
    }
};
