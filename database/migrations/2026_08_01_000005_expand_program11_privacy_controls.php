<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dovra_privacy_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('subject_type', 191);
            $table->string('subject_id', 191)->index();
            $table->string('request_type', 40);
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 40)->default('pending');
            $table->json('result_summary')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dovra_privacy_request_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('privacy_request_id')->index();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('event_type', 80);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dovra_legal_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('subject_type', 191);
            $table->string('subject_id', 191)->index();
            $table->uuid('requested_by')->nullable()->index();
            $table->text('reason');
            $table->string('status', 40)->default('active');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dovra_data_retention_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('subject_type', 191);
            $table->string('subject_id', 191)->index();
            $table->string('classification', 80);
            $table->string('retention_category', 80);
            $table->string('source_system', 80);
            $table->string('source_reference', 191);
            $table->timestamp('retain_until')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->string('disposition_status', 40)->default('pending');
            $table->timestamp('last_disposition_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dovra_data_retention_records');
        Schema::dropIfExists('dovra_legal_holds');
        Schema::dropIfExists('dovra_privacy_request_events');
        Schema::dropIfExists('dovra_privacy_requests');
    }
};
