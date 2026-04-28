<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaDuplicateColumnsGovernanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_semantic_duplicate_columns_do_not_exceed_contract(): void
    {
        /** @var array<int, array<string, mixed>> $contracts */
        $contracts = (array) config('schema-governance.duplicate_column_contracts', []);
        self::assertNotEmpty($contracts, 'schema-governance.duplicate_column_contracts must not be empty.');

        foreach ($contracts as $contract) {
            $table = (string) ($contract['table'] ?? '');
            $concept = (string) ($contract['concept'] ?? 'unnamed');
            $mode = (string) ($contract['mode'] ?? 'strict');
            $maxPresent = (int) ($contract['max_present'] ?? 1);
            /** @var array<int, string> $columns */
            $columns = (array) ($contract['columns'] ?? []);

            self::assertNotSame('', $table, 'Duplicate-column contract requires table.');
            self::assertNotEmpty($columns, "Duplicate-column contract [{$concept}] must declare columns.");
            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $tableColumns = collect(Schema::getColumns($table))->pluck('name')->map(
                static fn (mixed $name): string => (string) $name
            );

            $present = collect($columns)
                ->filter(static fn (string $column): bool => $tableColumns->contains($column))
                ->values()
                ->all();

            if ($mode === 'observe') {
                self::assertGreaterThanOrEqual(
                    0,
                    count($present),
                    "Observation mode should not fail for concept [{$concept}]."
                );

                continue;
            }

            self::assertLessThanOrEqual(
                $maxPresent,
                count($present),
                "Semantic duplicate contract [{$concept}] failed in [{$table}]. Present columns: ".implode(', ', $present)
            );
        }
    }

    public function test_critical_index_invariants_exist(): void
    {
        /** @var array<int, array<string, mixed>> $invariants */
        $invariants = (array) config('schema-governance.index_invariants', []);
        self::assertNotEmpty($invariants, 'schema-governance.index_invariants must not be empty.');

        foreach ($invariants as $invariant) {
            $table = (string) ($invariant['table'] ?? '');
            /** @var array<int, string> $expectedColumns */
            $expectedColumns = array_values((array) ($invariant['columns'] ?? []));
            $expectedUnique = (bool) ($invariant['unique'] ?? false);

            self::assertNotSame('', $table, 'Index invariant requires table.');
            self::assertNotEmpty($expectedColumns, "Index invariant [{$table}] must declare columns.");
            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $indexes = collect(Schema::getIndexes($table));

            $found = $indexes->first(function (array $index) use ($expectedColumns, $expectedUnique): bool {
                $columns = array_values(array_map(static fn (mixed $column): string => (string) $column, (array) ($index['columns'] ?? [])));
                $isUnique = (bool) ($index['unique'] ?? false);

                return $columns === $expectedColumns && $isUnique === $expectedUnique;
            });

            self::assertNotNull(
                $found,
                "Missing index invariant on [{$table}] for columns [".implode(', ', $expectedColumns).'] unique='.($expectedUnique ? 'true' : 'false')
            );
        }
    }
}
