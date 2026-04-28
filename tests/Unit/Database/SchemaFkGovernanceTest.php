<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaFkGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fk_contracts_exist_and_match_expected_rules(): void
    {
        /** @var array<int, array<string, mixed>> $contracts */
        $contracts = (array) config('schema-governance.fk_contracts', []);
        self::assertNotEmpty($contracts, 'schema-governance.fk_contracts must not be empty.');

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

            $tableColumns = collect(Schema::getColumns($table))->keyBy('name');
            self::assertTrue($tableColumns->has($column), "Governed column [{$table}.{$column}] does not exist.");

            $nullable = (bool) ($tableColumns->get($column)['nullable'] ?? false);
            self::assertSame(
                $expectedNullable,
                $nullable,
                "Column [{$table}.{$column}] nullable mismatch. Expected [".($expectedNullable ? 'true' : 'false').'], got ['.($nullable ? 'true' : 'false').'].'
            );

            $foreignKeys = collect(Schema::getForeignKeys($table));
            $matchedFk = $foreignKeys->first(function (array $foreignKey) use ($column, $foreignTable, $foreignColumn): bool {
                $columns = array_map(static fn (mixed $value): string => (string) $value, (array) ($foreignKey['columns'] ?? []));
                $foreignColumns = array_map(static fn (mixed $value): string => (string) $value, (array) ($foreignKey['foreign_columns'] ?? []));

                return $columns === [$column]
                    && ((string) ($foreignKey['foreign_table'] ?? '')) === $foreignTable
                    && $foreignColumns === [$foreignColumn];
            });

            self::assertNotNull(
                $matchedFk,
                "Missing FK contract for [{$table}.{$column}] -> [{$foreignTable}.{$foreignColumn}]."
            );

            if ($expectedOnDelete !== null) {
                $actualOnDelete = strtolower((string) (($matchedFk['on_delete'] ?? 'restrict')));
                self::assertSame(
                    $expectedOnDelete,
                    $actualOnDelete,
                    "FK [{$table}.{$column}] on_delete mismatch. Expected [{$expectedOnDelete}] got [{$actualOnDelete}]."
                );
            }

            $orphans = DB::table("{$table} as child")
                ->leftJoin("{$foreignTable} as parent", "child.{$column}", '=', "parent.{$foreignColumn}")
                ->whereNotNull("child.{$column}")
                ->whereNull("parent.{$foreignColumn}")
                ->count();

            self::assertSame(
                0,
                $orphans,
                "Detected {$orphans} orphan rows for FK contract [{$table}.{$column}] -> [{$foreignTable}.{$foreignColumn}]."
            );
        }
    }
}
