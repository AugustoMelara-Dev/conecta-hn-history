<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignUuid('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('job_title', 100)->nullable();
                $table->string('source', 100)->nullable();
                $table->string('lifecycle_stage', 30)->default('lead');
                $table->timestamp('last_contact_at')->nullable();
                $table->json('tags')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'lifecycle_stage']);
                $table->index(['tenant_id', 'owner_id']);
                $table->index(['tenant_id', 'email']);
                $table->index(['tenant_id', 'last_contact_at']);
            });
        }
    }

    public function down(): void
    {
        // This historical migration may have been applied under the old basename; keep CRM data intact on rollback.
    }
};
