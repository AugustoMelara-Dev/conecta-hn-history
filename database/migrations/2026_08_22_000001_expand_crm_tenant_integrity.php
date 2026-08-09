<?php

declare(strict_types=1);

use App\Application\Crm\TenantIntegrity\CrmTenantIntegrity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, true> */
    private static array $ownedColumns = [];

    /** @var array<string, true> */
    private static array $ownedIndexes = [];

    /** @var array<string, true> */
    private static array $ownedUniques = [];

    /** @var array<string, list<string>> */
    private const CANDIDATE_KEYS = [
        'crm_pipelines_tenant_id_unique' => ['crm_pipelines', 'tenant_id', 'id'],
        'crm_stages_tenant_pipeline_id_unique' => ['crm_stages', 'tenant_id', 'crm_pipeline_id', 'id'],
        'crm_deals_tenant_id_unique' => ['crm_deals', 'tenant_id', 'id'],
        'crm_companies_tenant_id_unique' => ['crm_companies', 'tenant_id', 'id'],
        'crm_contacts_tenant_id_unique' => ['crm_contacts', 'tenant_id', 'id'],
        'tags_tenant_id_unique' => ['tags', 'tenant_id', 'id'],
        'leads_tenant_id_unique' => ['leads', 'tenant_id', 'id'],
        'locations_tenant_id_unique' => ['locations', 'tenant_id', 'id'],
        'customer_profiles_tenant_id_unique' => ['customer_profiles', 'tenant_id', 'id'],
        'customer_notes_tenant_id_unique' => ['customer_notes', 'tenant_id', 'id'],
        'customer_tasks_tenant_id_unique' => ['customer_tasks', 'tenant_id', 'id'],
        'quotes_tenant_id_unique' => ['quotes', 'tenant_id', 'id'],
        'tenant_memberships_tenant_user_id_unique' => ['tenant_memberships', 'tenant_id', 'user_id', 'id'],
    ];

    public function up(?string $expectedFingerprint = null): void
    {
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight('expand');
        $fingerprint = $expectedFingerprint === null || trim($expectedFingerprint) === ''
            ? $preflight->schemaFingerprint
            : trim($expectedFingerprint);
        if ($preflight->status !== CrmTenantIntegrity::STATUS_READY
            || $fingerprint === ''
            || ! hash_equals($preflight->schemaFingerprint, $fingerprint)) {
            throw new LogicException('CRM tenant-integrity migration requires a READY canonical schema fingerprint.');
        }
        $this->assertEngineProof();
        $this->apply($fingerprint);
    }

    private function apply(string $boundFingerprint): void
    {
        if ($boundFingerprint === '') {
            throw new LogicException('CRM tenant-integrity migration requires a bound schema fingerprint.');
        }
        DB::transaction(function (): void {
            foreach ([
                ['contact_tag', 'contact_tag_tenant_contact_idx', ['tenant_id', 'contact_id']],
                ['contact_tag', 'contact_tag_tenant_tag_idx', ['tenant_id', 'tag_id']],
                ['deal_tag', 'deal_tag_tenant_deal_idx', ['tenant_id', 'deal_id']],
                ['deal_tag', 'deal_tag_tenant_tag_idx', ['tenant_id', 'tag_id']],
            ] as [$table, $index, $columns]) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                if (! Schema::hasColumn($table, 'tenant_id')) {
                    Schema::table($table, function (Blueprint $blueprint): void {
                        $blueprint->uuid('tenant_id')->nullable();
                    });
                    self::$ownedColumns[$table.'.tenant_id'] = true;
                }
                if ($this->hasIndex($table, $index)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $index): void {
                    $blueprint->index($columns, $index);
                });
                self::$ownedIndexes[$table.'.'.$index] = true;
            }

            foreach (self::CANDIDATE_KEYS as $name => $definition) {
                $table = (string) $definition[0];
                $columns = array_slice($definition, 1);
                if (! Schema::hasTable($table) || ! $this->hasColumns($table, $columns)) {
                    continue;
                }
                if ($this->hasCompatibleUnique($table, $columns)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->unique($columns, $name);
                });
                self::$ownedUniques[$table.'.'.$name] = true;
            }
        });
    }

    private function assertEngineProof(?string $driver = null): void
    {
        $resolvedDriver = $driver === null ? DB::getDriverName() : strtolower(trim($driver));
        if (! in_array($resolvedDriver, ['sqlite', 'pgsql'], true)) {
            throw new LogicException('EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED');
        }
    }

    public function down(): void
    {
        foreach (self::CANDIDATE_KEYS as $name => $definition) {
            [$table] = $definition;
            if (! Schema::hasTable($table) || ! $this->owns(self::$ownedUniques, $table.'.'.$name)
                || ! $this->hasIndex($table, $name) || DB::table($table)->exists()) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropUnique($name);
            });
            unset(self::$ownedUniques[$table.'.'.$name]);
        }

        foreach ([
            ['contact_tag', ['contact_tag_tenant_contact_idx', 'contact_tag_tenant_tag_idx']],
            ['deal_tag', ['deal_tag_tenant_deal_idx', 'deal_tag_tenant_tag_idx']],
        ] as [$table, $indexes]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            $provenancePopulated = DB::table($table)->whereNotNull('tenant_id')->exists();
            if ($provenancePopulated) {
                continue;
            }
            foreach ($indexes as $index) {
                if ($this->owns(self::$ownedIndexes, $table.'.'.$index) && $this->hasIndex($table, $index)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                        $blueprint->dropIndex($index);
                    });
                    unset(self::$ownedIndexes[$table.'.'.$index]);
                }
            }
            if ($this->owns(self::$ownedColumns, $table.'.tenant_id')
                && ! $this->hasIndex($table, 'contact_tag_tenant_contact_idx')
                && ! $this->hasIndex($table, 'contact_tag_tenant_tag_idx')
                && ! $this->hasIndex($table, 'deal_tag_tenant_deal_idx')
                && ! $this->hasIndex($table, 'deal_tag_tenant_tag_idx')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('tenant_id');
                });
                unset(self::$ownedColumns[$table.'.tenant_id']);
            }
        }
    }

    /** @param array<string, true> $ownership */
    private function owns(array $ownership, string $key): bool
    {
        return isset($ownership[$key]);
    }

    /** @param list<string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $columns */
    private function hasCompatibleUnique(string $table, array $columns): bool
    {
        foreach ($this->indexes($table) as $index) {
            if ((bool) ($index['unique'] ?? false)
                && array_values(array_map('strval', is_array($index['columns'] ?? null) ? $index['columns'] : [])) === $columns) {
                return true;
            }
        }
        return false;
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach ($this->indexes($table) as $index) {
            if ((string) ($index['name'] ?? '') === $name) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    private function indexes(string $table): array
    {
        return Schema::getIndexes($table);
    }
};
