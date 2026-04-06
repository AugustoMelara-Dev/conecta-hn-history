<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_message_templates')) {
            return;
        }

        Schema::create('customer_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('template_key', 120);
            $table->string('name', 160);
            $table->string('description', 255)->nullable();
            $table->string('channel', 24)->default('email');
            $table->string('customer_segment', 64)->nullable();
            $table->string('business_segment_key', 128)->nullable();
            $table->string('automation_key', 120)->default('manual_follow_up');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('cta_label', 120)->nullable();
            $table->string('template_name', 120)->nullable();
            $table->string('template_language', 16)->nullable();
            $table->json('template_variables')->nullable();
            $table->boolean('requires_marketing_opt_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'template_key']);
            $table->index(['tenant_id', 'customer_segment', 'is_active'], 'customer_message_templates_segment_idx');
            $table->index(['tenant_id', 'business_segment_key', 'is_active'], 'customer_message_templates_business_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_message_templates');
    }
};
