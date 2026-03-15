<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->char('user_id', 36)->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->date('requested_date');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'service_id', 'requested_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
