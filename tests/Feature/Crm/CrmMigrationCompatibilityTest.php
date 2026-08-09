<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmMigrationCompatibilityTest extends TestCase
{
    public function test_crm_migration_preserves_legacy_bigint_deals_and_canonical_columns(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
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
            $table->timestamps();
        });

        DB::table('crm_deals')->insert([
            'id' => 41,
            'tenant_id' => '00000000-0000-0000-0000-000000000041',
            'crm_pipeline_id' => 7,
            'crm_stage_id' => 9,
            'customer_profile_id' => null,
            'lead_id' => null,
            'owner_user_id' => null,
            'location_id' => null,
            'title' => 'Legacy opportunity',
            'value' => '1250.00',
            'currency' => 'HNL',
            'status' => 'open',
            'source' => 'legacy',
            'expected_close_at' => null,
            'closed_at' => null,
            'lost_reason' => null,
            'notes' => 'Existing CRM data.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_10_000003_create_crm_deals_table.php');
        $migration->up();

        self::assertTrue(Schema::hasTable('crm_deals'));
        foreach ([
            'id', 'tenant_id', 'crm_pipeline_id', 'crm_stage_id', 'customer_profile_id', 'lead_id',
            'owner_user_id', 'location_id', 'title', 'value', 'currency', 'status', 'source',
            'expected_close_at', 'closed_at', 'lost_reason', 'notes', 'created_at', 'updated_at',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('crm_deals', $column), "Missing canonical column [crm_deals.{$column}].");
        }
        self::assertSame('integer', Schema::getColumnType('crm_deals', 'id'));
        self::assertSame('integer', Schema::getColumnType('crm_deals', 'crm_pipeline_id'));
        self::assertSame('integer', Schema::getColumnType('crm_deals', 'crm_stage_id'));
        self::assertDatabaseHas('crm_deals', [
            'id' => 41,
            'crm_pipeline_id' => 7,
            'crm_stage_id' => 9,
            'title' => 'Legacy opportunity',
            'value' => '1250.00',
        ]);
    }
}
