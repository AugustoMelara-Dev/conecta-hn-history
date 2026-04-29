<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SchemaFkGovernanceTest extends TestCase
{
    public function test_fk_contracts_exist_and_match_expected_rules(): void
    {
        /** @var array<int, array<string, mixed>> $contracts */
        $contracts = (array) config('schema-governance.fk_contracts', []);
        self::assertNotEmpty($contracts, 'schema-governance.fk_contracts must not be empty.');

        $foreignKeys = $this->foreignKeyMetadataFor($contracts);
        $columns = $this->columnMetadataFor($contracts);

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

            $columnMeta = $columns[$this->columnKey($table, $column)] ?? null;
            self::assertNotNull($columnMeta, "Governed column [{$table}.{$column}] does not exist.");

            $nullable = $columnMeta['nullable'];
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
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<string, array{nullable: bool}>
     */
    private function columnMetadataFor(array $contracts): array
    {
        $metadata = [];

        foreach ($this->createTableSqlByTable($contracts) as $table => $createSql) {
            foreach ($this->contractColumnsForTable($contracts, $table) as $column) {
                $line = $this->columnDefinitionLine($createSql, $column);

                if ($line === null) {
                    continue;
                }

                $metadata[$this->columnKey($table, $column)] = [
                    'nullable' => ! str_contains(strtoupper($line), ' NOT NULL'),
                ];
            }
        }

        return $metadata;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<string, array{on_delete: string}>
     */
    private function foreignKeyMetadataFor(array $contracts): array
    {
        $metadata = [];

        foreach ($this->createTableSqlByTable($contracts) as $table => $createSql) {
            foreach ($contracts as $contract) {
                if ((string) ($contract['table'] ?? '') !== $table) {
                    continue;
                }

                $column = (string) ($contract['column'] ?? '');
                /** @var array<string, string> $references */
                $references = (array) ($contract['references'] ?? []);
                $foreignTable = (string) ($references['table'] ?? '');
                $foreignColumn = (string) ($references['column'] ?? 'id');

                if ($column === '' || $foreignTable === '') {
                    continue;
                }

                $deleteRule = $this->foreignKeyDeleteRule($createSql, $column, $foreignTable, $foreignColumn);

                if ($deleteRule === null) {
                    continue;
                }

                $metadata[$this->fkKey($table, $column, $foreignTable, $foreignColumn)] = [
                    'on_delete' => $deleteRule,
                ];
            }
        }

        return $metadata;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<string, string>
     */
    private function createTableSqlByTable(array $contracts): array
    {
        static $cache = [];

        $tables = collect($contracts)
            ->pluck('table')
            ->filter()
            ->map(static fn (mixed $table): string => (string) $table)
            ->unique()
            ->values();

        $sql = [];

        foreach ($tables as $table) {
            if (isset($cache[$table])) {
                $sql[$table] = $cache[$table];

                continue;
            }

            $row = DB::selectOne('SHOW CREATE TABLE `'.$this->escapeIdentifier($table).'`');
            self::assertNotNull($row, "Governed table [{$table}] does not exist.");

            $values = (array) $row;
            $createSql = (string) ($values['Create Table'] ?? array_values($values)[1] ?? '');
            self::assertNotSame('', $createSql, "Unable to inspect governed table [{$table}].");

            $cache[$table] = $createSql;
            $sql[$table] = $createSql;
        }

        return $sql;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return list<string>
     */
    private function contractColumnsForTable(array $contracts, string $table): array
    {
        return collect($contracts)
            ->filter(static fn (array $contract): bool => (string) ($contract['table'] ?? '') === $table)
            ->pluck('column')
            ->filter()
            ->map(static fn (mixed $column): string => (string) $column)
            ->unique()
            ->values()
            ->all();
    }

    private function columnDefinitionLine(string $createSql, string $column): ?string
    {
        $quotedColumn = preg_quote($column, '/');

        if (! preg_match('/^\s*`'.$quotedColumn.'`\s+.+$/m', $createSql, $matches)) {
            return null;
        }

        return (string) $matches[0];
    }

    private function foreignKeyDeleteRule(
        string $createSql,
        string $column,
        string $foreignTable,
        string $foreignColumn,
    ): ?string {
        $pattern = sprintf(
            '/FOREIGN KEY \(`%s`\) REFERENCES `%s` \(`%s`\)(?:[^,\n]*? ON DELETE (RESTRICT|CASCADE|SET NULL|NO ACTION))?/i',
            preg_quote($column, '/'),
            preg_quote($foreignTable, '/'),
            preg_quote($foreignColumn, '/'),
        );

        if (! preg_match($pattern, $createSql, $matches)) {
            return null;
        }

        return $this->normalizeDeleteRule((string) ($matches[1] ?? 'restrict'));
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    private function fkKey(string $table, string $column, string $foreignTable, string $foreignColumn): string
    {
        return "{$table}.{$column}:{$foreignTable}.{$foreignColumn}";
    }

    private function columnKey(string $table, string $column): string
    {
        return "{$table}.{$column}";
    }

    private function normalizeDeleteRule(string $rule): string
    {
        return strtolower(str_replace('_', ' ', $rule));
    }
}
