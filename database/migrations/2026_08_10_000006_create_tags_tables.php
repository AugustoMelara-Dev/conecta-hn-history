<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->string('color', 20)->default('#4F46E5');
                $table->string('type', 30)->default('all'); // contact/deal/company/all
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'type']);
            });
        }

        if (! Schema::hasTable('contact_tag')) {
            Schema::create('contact_tag', function (Blueprint $table) {
                $table->foreignUuid('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
                $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['contact_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable('deal_tag')) {
            Schema::create('deal_tag', function (Blueprint $table) {
                $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
                $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['deal_id', 'tag_id']);
            });
        }
    }

    public function down(): void
    {
        // This historical migration may have been applied under the old basename; keep CRM tag data intact on rollback.
    }
};
