<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaKeyTypeGovernanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_critical_fk_and_id_columns_keep_expected_key_types(): void
    {
        /** @var array<string, array<string, array<int, string>>> $contracts */
        $contracts = (array) config('schema-governance.key_type_contracts', []);
        self::assertNotEmpty($contracts, 'schema-governance.key_type_contracts must not be empty.');

        foreach ($contracts as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $tableColumns = collect(Schema::getColumns($table))->keyBy('name');

            foreach ($columns as $column => $allowedTypes) {
                self::assertTrue($tableColumns->has($column), "Expected governed column [{$table}.{$column}] was not found.");

                $columnMeta = $tableColumns->get($column);
                $actualType = strtolower((string) ($columnMeta['type'] ?? $columnMeta['type_name'] ?? ''));
                $expected = array_map(
                    static fn (string $type): string => strtolower($type),
                    $allowedTypes
                );

                $isMatch = collect($expected)->contains(
                    static fn (string $allowed): bool => str_contains($actualType, $allowed)
                );

                self::assertTrue(
                    $isMatch,
                    "Column [{$table}.{$column}] type [{$actualType}] does not match expected contract: ".implode(', ', $allowedTypes)
                );
            }
        }
    }
}
