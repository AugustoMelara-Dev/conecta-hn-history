<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaJsonGovernanceTest extends TestCase
{
    public function test_json_contract_columns_exist_with_allowed_storage_types_and_size_budget(): void
    {
        /** @var array<int, array<string, mixed>> $contracts */
        $contracts = (array) config('schema-governance.json_contracts', []);
        self::assertNotEmpty($contracts, 'schema-governance.json_contracts must not be empty.');

        foreach ($contracts as $contract) {
            $table = (string) ($contract['table'] ?? '');
            $column = (string) ($contract['column'] ?? '');
            /** @var array<int, string> $allowedTypes */
            $allowedTypes = array_map(
                static fn (string $type): string => strtolower($type),
                (array) ($contract['allowed_types'] ?? [])
            );
            $maxBytes = (int) ($contract['max_bytes'] ?? 0);

            self::assertNotSame('', $table, 'JSON contract must define table.');
            self::assertNotSame('', $column, "JSON contract for [{$table}] must define column.");
            self::assertNotEmpty($allowedTypes, "JSON contract for [{$table}.{$column}] must define allowed_types.");

            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $tableColumns = collect(Schema::getColumns($table))->keyBy('name');
            self::assertTrue($tableColumns->has($column), "Governed JSON column [{$table}.{$column}] does not exist.");

            $actualType = strtolower((string) ($tableColumns->get($column)['type'] ?? ''));
            $typeAllowed = collect($allowedTypes)
                ->contains(static fn (string $allowed): bool => str_contains($actualType, $allowed));

            self::assertTrue(
                $typeAllowed,
                "JSON governed column [{$table}.{$column}] has unsupported type [{$actualType}]. Allowed: ".implode(', ', $allowedTypes)
            );

            if ($maxBytes <= 0) {
                continue;
            }

            $driver = DB::getDriverName();
            $lengthExpression = $driver === 'sqlite'
                ? "MAX(LENGTH(CAST({$column} AS BLOB))) AS max_bytes"
                : "MAX(OCTET_LENGTH({$column})) AS max_bytes";

            $maxObserved = DB::table($table)
                ->selectRaw($lengthExpression)
                ->whereNotNull($column)
                ->value('max_bytes');

            $maxObservedInt = $maxObserved === null ? 0 : (int) $maxObserved;

            self::assertLessThanOrEqual(
                $maxBytes,
                $maxObservedInt,
                "JSON governed column [{$table}.{$column}] exceeds max_bytes contract ({$maxObservedInt} > {$maxBytes})."
            );
        }
    }

    public function test_deprecated_columns_are_not_reintroduced(): void
    {
        /** @var array<int, array<string, mixed>> $contracts */
        $contracts = (array) config('schema-governance.deprecation_contracts', []);

        foreach ($contracts as $contract) {
            $mode = (string) ($contract['mode'] ?? 'strict');
            if ($mode !== 'strict') {
                continue;
            }

            $table = (string) ($contract['table'] ?? '');
            /** @var array<int, string> $forbiddenColumns */
            $forbiddenColumns = (array) ($contract['forbidden_columns'] ?? []);

            self::assertNotSame('', $table, 'Deprecation contract must define table.');
            self::assertNotEmpty($forbiddenColumns, "Deprecation contract for [{$table}] must define forbidden_columns.");
            self::assertTrue(Schema::hasTable($table), "Governed table [{$table}] does not exist.");

            $columns = collect(Schema::getColumns($table))->pluck('name')->all();

            foreach ($forbiddenColumns as $forbiddenColumn) {
                self::assertFalse(
                    in_array($forbiddenColumn, $columns, true),
                    "Deprecated column [{$table}.{$forbiddenColumn}] was reintroduced."
                );
            }
        }
    }
}
