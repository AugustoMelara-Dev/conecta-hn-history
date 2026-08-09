<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const LEGACY_COLUMNS = [
        'pipeline_id',
        'stage_id',
        'name',
    ];

    /** @var list<string> */
    private const CANONICAL_COLUMNS = [
        'crm_pipeline_id',
        'crm_stage_id',
        'title',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('crm_deals')) {
            throw new RuntimeException('CRM legacy UUID reconciliation cannot classify a missing crm_deals table.');
        }

        $columns = Schema::getColumnListing('crm_deals');
        if (! in_array('id', $columns, true)) {
            throw new RuntimeException('CRM deals shape is unknown or mixed; an id column is required for UUID reconciliation.');
        }
        $idType = strtolower((string) Schema::getColumnType('crm_deals', 'id'));
        $canonicalId = in_array($idType, ['integer', 'bigint', 'int', 'int unsigned'], true);
        $legacyUuid = in_array($idType, ['string', 'varchar', 'char', 'text', 'uuid'], true);
        $hasLegacy = $this->hasAll($columns, self::LEGACY_COLUMNS);
        $hasCanonical = $this->hasAll($columns, self::CANONICAL_COLUMNS);

        // Canonical installations are intentionally untouched, including child tables.
        if ($hasCanonical && $canonicalId && ! $hasLegacy && ! in_array('pipeline_id', $columns, true)) {
            return;
        }

        if (! $hasLegacy || ! $legacyUuid || $hasCanonical || in_array('crm_pipeline_id', $columns, true)) {
            throw new RuntimeException('CRM deals shape is unknown or mixed; legacy UUID expansion is fail-closed.');
        }

        $this->createShadow();
        $this->createMap();
        $this->createPayloads();
        $this->addChildColumns();
    }

    /**
     * The expand migration is additive. Existing legacy data and the newly added
     * reconciliation structures are deliberately retained on rollback.
     */
    public function down(): void
    {
        // Intentionally non-destructive: no legacy table, shadow row, map, payload,
        // or child column is removed by this migration.
    }

    /** @param list<string> $columns @param list<string> $required */
    private function hasAll(array $columns, array $required): bool
    {
        foreach ($required as $column) {
            if (! in_array($column, $columns, true)) {
                return false;
            }
        }

        return true;
    }

    private function createShadow(): void
    {
        if (Schema::hasTable('crm_deals_canonical_shadow')) {
            return;
        }

        Schema::create('crm_deals_canonical_shadow', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('legacy_uuid');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->unsignedBigInteger('customer_profile_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->uuid('owner_user_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->string('status', 16)->default('open');
            $table->string('source')->nullable();
            $table->date('expected_close_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('write_idempotency_key', 160)->nullable();
            $table->char('write_payload_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_uuid'], 'crm_shadow_tenant_legacy_unique');
            $table->unique('legacy_uuid', 'crm_shadow_legacy_unique');
            $table->index(['tenant_id', 'crm_pipeline_id'], 'crm_shadow_tenant_pipeline_idx');
            $table->index(['tenant_id', 'crm_stage_id'], 'crm_shadow_tenant_stage_idx');
        });
    }

    private function createMap(): void
    {
        if (Schema::hasTable('crm_deal_legacy_id_map')) {
            return;
        }

        Schema::create('crm_deal_legacy_id_map', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('legacy_uuid');
            $table->unsignedBigInteger('canonical_deal_id')->nullable();
            $table->string('state', 24);
            $table->char('source_checksum', 64)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_uuid'], 'crm_legacy_map_tenant_uuid_unique');
            $table->unique('canonical_deal_id', 'crm_legacy_map_canonical_unique');
            $table->index(['tenant_id', 'state'], 'crm_legacy_map_tenant_state_idx');
        });
    }

    private function createPayloads(): void
    {
        if (Schema::hasTable('crm_deal_legacy_payloads')) {
            return;
        }

        Schema::create('crm_deal_legacy_payloads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('legacy_uuid');
            $table->char('source_checksum', 64);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_uuid'], 'crm_legacy_payload_tenant_uuid_unique');
            $table->index('source_checksum', 'crm_legacy_payload_checksum_idx');
            $table->index('legacy_uuid', 'crm_legacy_payload_uuid_idx');
        });
    }

    private function addChildColumns(): void
    {
        foreach (['crm_activities', 'deal_tag'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'canonical_deal_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('canonical_deal_id')->nullable();
                $table->index('canonical_deal_id');
            });
        }
    }
};
