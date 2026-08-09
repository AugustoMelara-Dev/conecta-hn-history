<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_companies')) {
            Schema::create('crm_companies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('industry', 100)->nullable();
                $table->string('size', 30)->nullable(); // micro/small/medium/large/enterprise
                $table->string('website', 255)->nullable();
                $table->string('country', 2)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('tax_id', 50)->nullable();
                $table->string('phone', 50)->nullable();
                $table->text('notes')->nullable();
                $table->json('tags')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'name']);
                $table->index(['tenant_id', 'industry']);
                $table->index(['tenant_id', 'size']);
            });
        }
    }

    public function down(): void
    {
        // This historical migration may have been applied under the old basename; keep CRM data intact on rollback.
    }
};
