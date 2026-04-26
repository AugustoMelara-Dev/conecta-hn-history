<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_config_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_key', 64);
            $table->string('resource_type', 80);
            $table->uuid('resource_id')->nullable();
            $table->string('event_name', 190);
            $table->string('request_id', 120)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('json_patch')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'action_key', 'created_at'], 'dyn_cfg_audit_tenant_action_idx');
            $table->index(['resource_type', 'resource_id', 'created_at'], 'dyn_cfg_audit_resource_idx');
            $table->index(['event_name', 'created_at'], 'dyn_cfg_audit_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_config_audits');
    }
};

