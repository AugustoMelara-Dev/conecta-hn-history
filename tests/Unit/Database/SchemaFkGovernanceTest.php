<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaFkGovernanceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, \Illuminate\Support\Collection<int, array<string, mixed>>> */
    private array $columnsByTable = [];

    public function test_fk_contracts_exist_and_match_expected_rules(): void
    {
        /** @var array<int, array<string, mixed>> $contracts */
        $contracts = (array) config('schema-governance.fk_contracts', []);
        self::assertNotEmpty($contracts, 'schema-governance.fk_contracts must not be empty.');

        $foreignKeys = $this->foreignKeyMetadataFor($contracts);

        foreach ($contracts as $contract) {
            $table = (string) ($contract['table'] ?? '');
            $column = (string) ($contract['column'] ?? '');
            /** @var array<string, string> $references */
            $references = (array) ($contract['references'] ?? []);
            $foreignTable = (string) ($references['table'] ?? '');
            $foreignColumn = (string) ($references['column'] ?? 'id');
            $expectedOnDelete = $contract['on_delete'] !== null ? strtolower((string) $contract['on_delete']) : null;
            $expectedNullable = (bool) ($contract['nullable'] ?? false);

            self::assertNotSame('', $table, 'FK contract must define table.');
            self::assertNotSame('', $column, "FK contract for [{$table}] must define column.");
            self::assertNotSame('', $foreignTable, "FK contract for [{$table}.{$column}] must define references.table.");

            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");
            self::assertTrue(Schema::hasTable($foreignTable), "Referenced table [{$foreignTable}] does not exist.");

            $tableColumns = $this->columnsFor($table)->keyBy('name');
            self::assertTrue($tableColumns->has($column), "Governed column [{$table}.{$column}] does not exist.");

            $nullable = (bool) ($tableColumns->get($column)['nullable'] ?? false);
            self::assertSame(
                $expectedNullable,
                $nullable,
                "Column [{$table}.{$column}] nullable mismatch. Expected [".($expectedNullable ? 'true' : 'false').'], got ['.($nullable ? 'true' : 'false').'].'
            );

            $matchedFk = $foreignKeys[$this->fkKey($table, $column, $foreignTable, $foreignColumn)] ?? null;

            self::assertNotNull(
                $matchedFk,
                "Missing FK contract for [{$table}.{$column}] -> [{$foreignTable}.{$foreignColumn}]."
            );

            if ($expectedOnDelete !== null) {
                $actualOnDelete = $this->normalizeDeleteRule((string) (($matchedFk['on_delete'] ?? 'restrict')));
                self::assertSame(
                    $expectedOnDelete,
                    $actualOnDelete,
                    "FK [{$table}.{$column}] on_delete mismatch. Expected [{$expectedOnDelete}] got [{$actualOnDelete}]."
                );
            }

            $orphans = $this->hasOrphanRows($table, $column, $foreignTable, $foreignColumn) ? 1 : 0;

            self::assertSame(
                0,
                $orphans,
                "Detected {$orphans} orphan rows for FK contract [{$table}.{$column}] -> [{$foreignTable}.{$foreignColumn}]."
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function columnsFor(string $table): \Illuminate\Support\Collection
    {
        return $this->columnsByTable[$table] ??= collect(Schema::getColumns($table));
    }

    private function hasOrphanRows(string $table, string $column, string $foreignTable, string $foreignColumn): bool
    {
        $hasChildRows = DB::table($table)
            ->whereNotNull($column)
            ->limit(1)
            ->exists();

        if (! $hasChildRows) {
            return false;
        }

        return DB::table("{$table} as child")
            ->leftJoin("{$foreignTable} as parent", "child.{$column}", '=', "parent.{$foreignColumn}")
            ->whereNotNull("child.{$column}")
            ->whereNull("parent.{$foreignColumn}")
            ->limit(1)
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<string, array{on_delete: string}>
     */
    private function foreignKeyMetadataFor(array $contracts): array
    {
        $tables = collect($contracts)
            ->pluck('table')
            ->filter()
            ->map(static fn (mixed $table): string => (string) $table)
            ->unique()
            ->values();

        if ($tables->isEmpty()) {
            return [];
        }

        $placeholders = $tables->map(static fn (): string => '?')->implode(', ');
        $rows = DB::select(
            <<<SQL
            SELECT
                kcu.TABLE_NAME as table_name,
                kcu.COLUMN_NAME as column_name,
                kcu.REFERENCED_TABLE_NAME as foreign_table,
                kcu.REFERENCED_COLUMN_NAME as foreign_column,
                rc.DELETE_RULE as delete_rule
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                AND kcu.TABLE_NAME IN ({$placeholders})
            SQL,
            $tables->all(),
        );

        $metadata = [];

        foreach ($rows as $row) {
            $key = $this->fkKey(
                (string) $row->table_name,
                (string) $row->column_name,
                (string) $row->foreign_table,
                (string) $row->foreign_column,
            );

            $metadata[$key] = [
                'on_delete' => $this->normalizeDeleteRule((string) ($row->delete_rule ?? 'restrict')),
            ];
        }

        return $metadata;
    }

    private function fkKey(string $table, string $column, string $foreignTable, string $foreignColumn): string
    {
        return "{$table}.{$column}:{$foreignTable}.{$foreignColumn}";
    }

    private function normalizeDeleteRule(string $rule): string
    {
        return strtolower(str_replace('_', ' ', $rule));
    }
}
