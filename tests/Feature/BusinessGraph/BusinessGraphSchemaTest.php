<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessGraph;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BusinessGraphSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_schema_is_tenant_scoped_and_provenance_bearing(): void
    {
        $this->assertTrue(Schema::hasTable('dovra_business_contexts'));
        $this->assertTrue(Schema::hasTable('dovra_business_facts'));
        $this->assertTrue(Schema::hasTable('dovra_business_edges'));

        foreach ([
            'tenant_id',
            'subject_type',
            'subject_id',
            'fact_key',
            'value',
            'source_type',
            'source_id',
            'confidence',
            'valid_from',
            'valid_until',
            'revoked_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('dovra_business_facts', $column),
                "Missing dovra_business_facts.{$column}",
            );
        }

        foreach ([
            'tenant_id',
            'from_type',
            'from_id',
            'relation',
            'to_type',
            'to_id',
            'source_type',
            'source_id',
            'valid_from',
            'valid_until',
            'revoked_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('dovra_business_edges', $column),
                "Missing dovra_business_edges.{$column}",
            );
        }

        foreach ([
            'tenant_id',
            'context_key',
            'value',
            'source_type',
            'source_id',
            'confidence',
            'valid_from',
            'valid_until',
            'revoked_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('dovra_business_contexts', $column),
                "Missing dovra_business_contexts.{$column}",
            );
        }

        $this->assertTrue($this->hasCompositeIndexContaining('dovra_business_facts', ['tenant_id', 'subject_type', 'subject_id']));
        $this->assertTrue($this->hasCompositeIndexContaining('dovra_business_facts', ['tenant_id', 'fact_key']));
        $this->assertTrue($this->hasCompositeIndexContaining('dovra_business_edges', ['tenant_id', 'relation']));
    }

    /**
     * @param  list<string>  $requiredColumns
     */
    private function hasCompositeIndexContaining(string $table, array $requiredColumns): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
                $columns = array_map(
                    static fn (object $row): string => (string) $row->name,
                    DB::select("PRAGMA index_info('{$index->name}')"),
                );

                if ($columns === $requiredColumns) {
                    return true;
                }
            }

            return false;
        }

        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (($index['columns'] ?? []) === $requiredColumns) {
                return true;
            }
        }

        return false;
    }
}
