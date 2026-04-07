<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('checkout_sessions')) {
            return;
        }

        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->json('items');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamp('last_active_at')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('checkout_sessions');
        Schema::enableForeignKeyConstraints();
    }
};
