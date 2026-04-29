<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaIndexGovernanceTest extends TestCase
{
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
