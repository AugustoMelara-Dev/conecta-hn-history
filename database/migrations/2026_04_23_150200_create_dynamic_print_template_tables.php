<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_print_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->uuid('entity_definition_id')->nullable()->index();
            $table->uuid('source_print_template_id')->nullable()->index();
            $table->string('owner_scope', 24)->default('superadmin');
            $table->string('template_key', 120);
            $table->string('action_key', 120)->default('default');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('blocks_json');
            $table->json('placeholder_whitelist')->nullable();
            $table->string('status', 24)->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->uuid('active_version_id')->nullable()->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->nullOnDelete();

            $table->index(['tenant_id', 'action_key']);
            $table->unique(['tenant_id', 'template_key']);
        });

        Schema::create('dynamic_print_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dynamic_print_template_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft');
            $table->json('blocks_json');
            $table->json('metadata_json')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('dynamic_print_template_id', 'dpt_versions_template_fk')
                ->references('id')
                ->on('dynamic_print_templates')
                ->cascadeOnDelete();

            $table->unique(['dynamic_print_template_id', 'version_number'], 'dynamic_print_template_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_print_template_versions');
        Schema::dropIfExists('dynamic_print_templates');
    }
};
