<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('crm_stages')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 200);
            $table->decimal('value', 14, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->string('status', 20)->default('open'); // open/won/lost
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'pipeline_id', 'stage_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'expected_close_date']);
            $table->index(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
