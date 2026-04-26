<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->uuid('entity_definition_id');
            $table->uuid('schema_version_id')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default((string) config('dynamic-forms.default_record_status', 'active'));
            $table->json('payload_json');
            $table->json('computed_json')->nullable();
            $table->timestamps();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->cascadeOnDelete();

            $table->foreign('schema_version_id')
                ->references('id')
                ->on('schema_versions')
                ->nullOnDelete();

            $table->index(['tenant_id', 'entity_definition_id', 'status'], 'dynamic_records_tenant_entity_status_idx');
            $table->index(['tenant_id', 'created_at'], 'dynamic_records_tenant_created_idx');
        });

        Schema::create('dynamic_record_index_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dynamic_record_id');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('entity_definition_id');
            $table->string('field_key', 120);
            $table->string('value_string')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->foreign('dynamic_record_id')
                ->references('id')
                ->on('dynamic_records')
                ->cascadeOnDelete();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->cascadeOnDelete();

            $table->unique(['dynamic_record_id', 'field_key'], 'dynamic_record_field_unique');
            $table->index(['tenant_id', 'entity_definition_id', 'field_key'], 'dynamic_record_index_lookup_idx');
            $table->index(['field_key', 'value_string'], 'dynamic_record_index_string_idx');
            $table->index(['field_key', 'value_number'], 'dynamic_record_index_number_idx');
            $table->index(['field_key', 'value_date'], 'dynamic_record_index_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_record_index_values');
        Schema::dropIfExists('dynamic_records');
    }
};

