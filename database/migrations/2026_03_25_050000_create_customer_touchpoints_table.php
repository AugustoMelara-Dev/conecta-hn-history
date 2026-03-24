<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_touchpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('direction', 16)->default('outbound');
            $table->string('automation_key', 64)->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('status', 32)->default('queued');
            $table->boolean('opt_in_snapshot')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'automation_key']);
            $table->index(['customer_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_touchpoints');
    }
};
