<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_published_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('projection_key', 190)->unique();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_record_id');
            $table->foreignUuid('source_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tenant_market_id')->nullable()->constrained('tenant_markets')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->char('country_code', 2)->nullable();
            $table->string('locale_code', 16)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('timezone_name', 64)->nullable();
            $table->string('business_name');
            $table->string('business_slug')->nullable();
            $table->string('business_url')->nullable();
            $table->string('offer_name');
            $table->text('offer_description')->nullable();
            $table->text('search_document')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('price_display')->nullable();
            $table->string('availability_status', 24)->default('unknown');
            $table->unsignedTinyInteger('freshness_score')->default(0);
            $table->unsignedTinyInteger('completeness_score')->default(0);
            $table->string('verification_level', 24)->default('unknown');
            $table->unsignedTinyInteger('verification_score')->default(0);
            $table->string('moderation_status', 24)->default('approved');
            $table->unsignedTinyInteger('moderation_score')->default(100);
            $table->json('evidence_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('projected_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_current')->default(false);
            $table->boolean('is_eligible')->default(false);
            $table->boolean('prohibited_offer')->default(false);
            $table->timestamps();

            $table->index(['country_code', 'currency_code', 'source_type'], 'discover_market_lookup_idx');
            $table->index(['is_published', 'is_current', 'is_eligible'], 'discover_visibility_lookup_idx');
            $table->index(['source_tenant_id', 'source_type'], 'discover_tenant_source_idx');
        });

        Schema::create('discovery_offer_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('projection_id')->constrained('discovery_published_offers')->cascadeOnDelete();
            $table->string('evidence_type', 48);
            $table->string('evidence_key', 190);
            $table->foreignUuid('source_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('source_table', 64)->nullable();
            $table->string('source_identifier', 190)->nullable();
            $table->timestamp('freshness_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('visibility', 24)->default('public');
            $table->string('summary')->nullable();
            $table->json('facts')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['projection_id', 'evidence_key'], 'discover_evidence_unique');
            $table->index(['projection_id', 'evidence_type'], 'discover_evidence_projection_idx');
        });

        Schema::create('discovery_marketplace_integrity_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('projection_id')->constrained('discovery_published_offers')->cascadeOnDelete();
            $table->string('record_type', 32);
            $table->string('status', 32);
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('reason')->nullable();
            $table->string('actor_scope', 32)->nullable();
            $table->string('actor_reference')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['projection_id', 'record_type'], 'discover_integrity_projection_idx');
            $table->index(['record_type', 'status'], 'discover_integrity_status_idx');
        });

        Schema::create('discovery_buyer_action_preparations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('projection_id')->constrained('discovery_published_offers')->cascadeOnDelete();
            $table->foreignUuid('source_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('action_type', 48);
            $table->string('status', 32);
            $table->string('reason_code', 32)->nullable();
            $table->date('requested_local_date')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedBigInteger('quoted_total_minor')->nullable();
            $table->string('availability_status', 24)->default('unknown');
            $table->boolean('confirmation_required')->default(true);
            $table->json('payload')->nullable();
            $table->json('lineage')->nullable();
            $table->timestamps();

            $table->index(['projection_id', 'action_type'], 'discover_action_projection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_buyer_action_preparations');
        Schema::dropIfExists('discovery_marketplace_integrity_records');
        Schema::dropIfExists('discovery_offer_evidence');
        Schema::dropIfExists('discovery_published_offers');
    }
};
