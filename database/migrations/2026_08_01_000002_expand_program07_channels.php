<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tenantTables = [
        'channel_connections',
        'channel_capability_snapshots',
        'channel_provider_health',
        'channel_identities',
        'channel_consents',
        'channel_delivery_attempts',
        'channel_webhook_claims',
    ];

    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->string('connection_key', 64);
            $table->string('provider_key', 64);
            $table->string('market_country_code', 8)->nullable();
            $table->string('secret_reference_key')->nullable();
            $table->json('config')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'connection_key'], 'channel_connections_tenant_channel_connection_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('channel_capability_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->string('connection_key', 64);
            $table->string('adapter_key', 64);
            $table->string('provider_key', 64);
            $table->string('market_country_code', 8);
            $table->boolean('allowed');
            $table->string('reason', 64);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'connection_key'], 'channel_capability_snapshots_tenant_channel_idx');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('channel_provider_health', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->string('connection_key', 64);
            $table->string('provider_key', 64);
            $table->string('status', 32);
            $table->string('reason', 64)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'connection_key'], 'channel_provider_health_tenant_channel_connection_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('channel_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->string('identity_type', 32);
            $table->string('normalized_value');
            $table->string('display_value')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'identity_type', 'normalized_value'], 'channel_identities_tenant_channel_identity_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('channel_consents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->uuid('identity_id');
            $table->string('purpose', 32);
            $table->string('status', 32);
            $table->string('source', 32);
            $table->string('market_country_code', 8);
            $table->string('connection_key', 64);
            $table->json('evidence')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'identity_id', 'purpose'], 'channel_consents_tenant_channel_identity_purpose_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('identity_id')->references('id')->on('channel_identities')->cascadeOnDelete();
        });

        Schema::create('channel_delivery_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->string('connection_key', 64);
            $table->string('idempotency_key', 160);
            $table->string('external_thread_reference', 500)->nullable();
            $table->string('recipient');
            $table->string('provider_message_id')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->string('delivery_state', 32);
            $table->char('payload_hash', 64);
            $table->decimal('cost_amount', 10, 2)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'connection_key', 'idempotency_key'], 'channel_delivery_attempts_idempotency_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('channel_webhook_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 32);
            $table->string('connection_key', 64);
            $table->string('provider_event_id');
            $table->char('payload_hash', 64);
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'connection_key', 'provider_event_id'], 'channel_webhook_claims_event_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        $this->enablePostgresRowLevelSecurity();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ($this->tenantTables as $table) {
                $identifier = '"'.str_replace('"', '""', $table).'"';
                $policy = '"'.str_replace('"', '""', $table.'_tenant_isolation').'"';
                DB::statement("DROP POLICY IF EXISTS {$policy} ON {$identifier}");
                DB::statement("ALTER TABLE {$identifier} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$identifier} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('channel_webhook_claims');
        Schema::dropIfExists('channel_delivery_attempts');
        Schema::dropIfExists('channel_consents');
        Schema::dropIfExists('channel_identities');
        Schema::dropIfExists('channel_provider_health');
        Schema::dropIfExists('channel_capability_snapshots');
        Schema::dropIfExists('channel_connections');
    }

    private function enablePostgresRowLevelSecurity(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tenantTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $identifier = '"'.str_replace('"', '""', $table).'"';
            $policy = '"'.str_replace('"', '""', $table.'_tenant_isolation').'"';

            DB::statement("ALTER TABLE {$identifier} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$identifier} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$policy} ON {$identifier}");
            DB::statement(
                "CREATE POLICY {$policy} ON {$identifier} "
                ."USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid) "
                ."WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)"
            );
        }
    }
};
