<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->uuid('source_entity_definition_id')->nullable()->index();
            $table->string('owner_scope', 24)->default('superadmin');
            $table->string('module_key', 80);
            $table->string('entity_key', 120);
            $table->string('name');
            $table->string('business_segment_key', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->boolean('allow_tenant_overrides')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->uuid('active_schema_version_id')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'module_key']);
            $table->unique(['tenant_id', 'entity_key']);
        });

        Schema::create('schema_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('entity_definition_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft');
            $table->json('schema_json');
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->cascadeOnDelete();

            $table->unique(['entity_definition_id', 'version_number'], 'schema_versions_entity_version_unique');
            $table->index(['entity_definition_id', 'status']);
        });

        Schema::create('field_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('entity_definition_id');
            $table->string('field_key', 120);
            $table->string('field_type', 40);
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('storage_mode', 24)->default('json');
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('options_json')->nullable();
            $table->json('visibility_json')->nullable();
            $table->json('conditions_json')->nullable();
            $table->json('hooks_json')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->cascadeOnDelete();

            $table->unique(['entity_definition_id', 'field_key']);
            $table->index(['entity_definition_id', 'sort_order']);
            $table->index(['entity_definition_id', 'is_indexed']);
        });

        Schema::create('layout_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('entity_definition_id');
            $table->string('layout_key', 80);
            $table->json('layout_json');
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->cascadeOnDelete();

            $table->unique(['entity_definition_id', 'layout_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_definitions');
        Schema::dropIfExists('field_definitions');
        Schema::dropIfExists('schema_versions');
        Schema::dropIfExists('entity_definitions');
    }
};

