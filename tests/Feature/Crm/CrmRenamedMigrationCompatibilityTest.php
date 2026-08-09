<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmRenamedMigrationCompatibilityTest extends TestCase
{
    private const LEGACY_TENANT_ID = '00000000-0000-0000-0000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();

        foreach (['deal_tag', 'contact_tag', 'tags', 'crm_contacts', 'crm_companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_company_migration_preserves_table_created_by_legacy_basename(): void
    {
        $this->createLegacyCompaniesTable();

        DB::table('crm_companies')->insert([
            'id' => '00000000-0000-0000-0000-000000000010',
            'tenant_id' => self::LEGACY_TENANT_ID,
            'name' => 'Legacy company',
            'industry' => 'Retail',
            'size' => 'small',
            'website' => null,
            'country' => 'HN',
            'city' => 'Tegucigalpa',
            'tax_id' => null,
            'phone' => null,
            'notes' => null,
            'tags' => null,
            'custom_fields' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $migration = require database_path('migrations/2026_08_10_000001_create_crm_companies_table.php');
        $migration->up();

        self::assertTrue(Schema::hasTable('crm_companies'));
        $this->assertColumns('crm_companies', [
            'id', 'tenant_id', 'name', 'industry', 'size', 'website', 'country', 'city', 'tax_id',
            'phone', 'notes', 'tags', 'custom_fields', 'created_at', 'updated_at', 'deleted_at',
        ]);
        self::assertDatabaseHas('crm_companies', [
            'id' => '00000000-0000-0000-0000-000000000010',
            'name' => 'Legacy company',
        ]);

        $migration->down();

        self::assertTrue(Schema::hasTable('crm_companies'));
    }

    public function test_contact_migration_preserves_table_created_by_legacy_basename(): void
    {
        $this->createLegacyContactsTable();

        DB::table('crm_contacts')->insert([
            'id' => '00000000-0000-0000-0000-000000000011',
            'tenant_id' => self::LEGACY_TENANT_ID,
            'company_id' => null,
            'owner_id' => null,
            'first_name' => 'Legacy',
            'last_name' => 'Contact',
            'email' => 'legacy@example.test',
            'phone' => null,
            'job_title' => null,
            'source' => 'legacy-basename',
            'lifecycle_stage' => 'lead',
            'last_contact_at' => null,
            'tags' => null,
            'custom_fields' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $migration = require database_path('migrations/2026_08_10_000002_create_crm_contacts_table.php');
        $migration->up();

        self::assertTrue(Schema::hasTable('crm_contacts'));
        $this->assertColumns('crm_contacts', [
            'id', 'tenant_id', 'company_id', 'owner_id', 'first_name', 'last_name', 'email', 'phone',
            'job_title', 'source', 'lifecycle_stage', 'last_contact_at', 'tags', 'custom_fields',
            'created_at', 'updated_at', 'deleted_at',
        ]);
        self::assertDatabaseHas('crm_contacts', [
            'id' => '00000000-0000-0000-0000-000000000011',
            'email' => 'legacy@example.test',
        ]);

        $migration->down();

        self::assertTrue(Schema::hasTable('crm_contacts'));
    }

    public function test_each_tags_table_is_reconciled_independently_when_legacy_basename_exists(): void
    {
        foreach (['tags', 'contact_tag', 'deal_tag'] as $legacyTable) {
            $this->resetCrmTables();
            $this->createLegacyTagTable($legacyTable);

            $migration = require database_path('migrations/2026_08_10_000006_create_tags_tables.php');
            $migration->up();

            foreach (['tags', 'contact_tag', 'deal_tag'] as $table) {
                self::assertTrue(Schema::hasTable($table), "Missing reconciled table [{$table}] when [{$legacyTable}] pre-existed.");
            }

            if ($legacyTable === 'tags') {
                $this->assertColumns('tags', ['id', 'tenant_id', 'name', 'slug', 'color', 'type', 'created_at', 'updated_at']);
                self::assertDatabaseHas('tags', ['id' => '00000000-0000-0000-0000-000000000020', 'name' => 'Legacy tag']);
            } elseif ($legacyTable === 'contact_tag') {
                $this->assertColumns('contact_tag', ['contact_id', 'tag_id', 'created_at', 'updated_at']);
                self::assertDatabaseHas('contact_tag', [
                    'contact_id' => '00000000-0000-0000-0000-000000000021',
                    'tag_id' => '00000000-0000-0000-0000-000000000020',
                ]);
            } else {
                $this->assertColumns('deal_tag', ['deal_id', 'tag_id', 'created_at', 'updated_at']);
                self::assertDatabaseHas('deal_tag', [
                    'deal_id' => '00000000-0000-0000-0000-000000000022',
                    'tag_id' => '00000000-0000-0000-0000-000000000020',
                ]);
            }

            $migration->down();

            foreach (['tags', 'contact_tag', 'deal_tag'] as $table) {
                self::assertTrue(Schema::hasTable($table), "Historical down() removed [{$table}] owned by an old basename.");
            }
        }
    }

    private function resetCrmTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['deal_tag', 'contact_tag', 'tags', 'crm_contacts', 'crm_companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createLegacyCompaniesTable(): void
    {
        Schema::create('crm_companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('industry', 100)->nullable();
            $table->string('size', 30)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'industry']);
            $table->index(['tenant_id', 'size']);
        });
    }

    private function createLegacyContactsTable(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('lifecycle_stage', 30)->default('lead');
            $table->timestamp('last_contact_at')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'lifecycle_stage']);
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'last_contact_at']);
        });
    }

    private function createLegacyTagTable(string $tableName): void
    {
        if ($tableName === 'tags') {
            Schema::create('tags', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->string('color', 20)->default('#4F46E5');
                $table->string('type', 30)->default('all');
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'type']);
            });

            DB::table('tags')->insert([
                'id' => '00000000-0000-0000-0000-000000000020',
                'tenant_id' => self::LEGACY_TENANT_ID,
                'name' => 'Legacy tag',
                'slug' => 'legacy-tag',
                'color' => '#4F46E5',
                'type' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($tableName === 'contact_tag') {
            Schema::create('contact_tag', function (Blueprint $table): void {
                $table->foreignUuid('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
                $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['contact_id', 'tag_id']);
            });

            DB::table('contact_tag')->insert([
                'contact_id' => '00000000-0000-0000-0000-000000000021',
                'tag_id' => '00000000-0000-0000-0000-000000000020',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        Schema::create('deal_tag', function (Blueprint $table): void {
            $table->foreignUuid('deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['deal_id', 'tag_id']);
        });

        DB::table('deal_tag')->insert([
            'deal_id' => '00000000-0000-0000-0000-000000000022',
            'tag_id' => '00000000-0000-0000-0000-000000000020',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param list<string> $columns
     */
    private function assertColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            self::assertTrue(Schema::hasColumn($table, $column), "Missing preserved column [{$table}.{$column}].");
        }
    }
}
