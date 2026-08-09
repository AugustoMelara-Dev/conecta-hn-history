<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Application\Crm\TenantIntegrity\CrmTenantIntegrity;
use Tests\TestCase;

final class CrmTenantIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_command_emits_deterministic_json_for_canonical_shape(): void
    {
        $this->canonicalDealsTable();

        $report = app(CrmTenantIntegrity::class)->preflight();
        self::assertSame('READY', $report->status, json_encode($report->toArray(), JSON_THROW_ON_ERROR));
        $this->artisan('crm:reconcile-tenant-integrity', ['phase' => 'preflight', '--json' => true])
            ->expectsOutputToContain('"shape": "canonical_bigint"')
            ->assertSuccessful();
    }

    public function test_expand_command_requires_current_fingerprint_and_refuses_legacy_shape(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('name');
        });

        $this->artisan('crm:reconcile-tenant-integrity', ['phase' => 'expand', '--json' => true])
            ->expectsOutputToContain('"status": "BLOCKED_LEGACY_UUID_CONTRACT"')
            ->assertExitCode(5);
    }

    public function test_unknown_phase_is_invalid_without_database_changes(): void
    {
        $this->artisan('crm:reconcile-tenant-integrity', ['phase' => 'nonsense', '--json' => true])
            ->assertExitCode(2);
    }

    private function canonicalDealsTable(): void
    {
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');
        $migration->down();
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }
}
