<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaNullabilityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_columns_are_not_nullable(): void
    {
        /** @var array<string, array<int, string>> $requiredNow */
        $requiredNow = (array) config('schema-governance.nullability_contracts.required_now', []);
        self::assertNotEmpty($requiredNow, 'schema-governance.nullability_contracts.required_now must not be empty.');

        foreach ($requiredNow as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $tableColumns = collect(Schema::getColumns($table))->keyBy('name');

            foreach ($columns as $column) {
                self::assertTrue($tableColumns->has($column), "Expected required column [{$table}.{$column}] was not found.");

                $meta = $tableColumns->get($column);
                $nullable = (bool) ($meta['nullable'] ?? true);

                self::assertFalse($nullable, "Column [{$table}.{$column}] must be NOT NULL under strict gradual governance.");
            }
        }
    }

    public function test_optional_columns_remain_explicitly_nullable(): void
    {
        /** @var array<string, array<int, string>> $optionalLegit */
        $optionalLegit = (array) config('schema-governance.nullability_contracts.optional_legit', []);
        self::assertNotEmpty($optionalLegit, 'schema-governance.nullability_contracts.optional_legit must not be empty.');

        foreach ($optionalLegit as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $tableColumns = collect(Schema::getColumns($table))->keyBy('name');

            foreach ($columns as $column) {
                self::assertTrue($tableColumns->has($column), "Expected optional column [{$table}.{$column}] was not found.");

                $meta = $tableColumns->get($column);
                $nullable = (bool) ($meta['nullable'] ?? false);

                self::assertTrue($nullable, "Column [{$table}.{$column}] should remain nullable by contract.");
            }
        }
    }
}
