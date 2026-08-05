<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseSchemaInspector
{
    /** @return list<string> */
    public static function columnNames(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->map(fn (array $column): string => (string) $column['name'])
            ->all();
    }

    /** @return list<string> */
    public static function indexColumns(string $table, string $index): array
    {
        $matching = collect(Schema::getIndexes($table))
            ->first(fn (array $candidate): bool => (string) $candidate['name'] === $index);

        return $matching === null
            ? []
            : array_values(array_map('strval', $matching['columns']));
    }

    /** @return list<string> */
    public static function indexNames(string $table): array
    {
        return collect(Schema::getIndexes($table))
            ->map(fn (array $index): string => (string) $index['name'])
            ->all();
    }

    /** @return list<string> */
    public static function primaryKeyColumns(string $table): array
    {
        $primary = collect(Schema::getIndexes($table))
            ->first(fn (array $index): bool => (bool) ($index['primary'] ?? false));

        return $primary === null
            ? []
            : array_values(array_map('strval', $primary['columns']));
    }

    public static function columnIsNullable(string $table, string $columnName): ?bool
    {
        $column = collect(Schema::getColumns($table))
            ->first(fn (array $candidate): bool => (string) $candidate['name'] === $columnName);

        return $column === null ? null : (bool) $column['nullable'];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    public static function hasForeignKey(
        string $table,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
    ): bool {
        if (DB::getDriverName() === 'mysql') {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
                return false;
            }

            $result = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $definition = (string) (collect($result)
                ->first(fn (mixed $value, string $key): bool => str_contains(strtolower($key), 'create table')) ?? '');
            $normalized = strtolower(str_replace(['`', ' ', "\r", "\n", "\t"], '', $definition));
            $local = implode(',', array_map('strtolower', $columns));
            $foreign = implode(',', array_map('strtolower', $foreignColumns));

            return str_contains(
                $normalized,
                "foreignkey({$local})references".strtolower($foreignTable)."({$foreign})",
            );
        }

        return collect(Schema::getForeignKeys($table))->contains(
            static fn (array $foreign): bool => array_values(array_map('strval', $foreign['columns'])) === $columns
                && (string) $foreign['foreign_table'] === $foreignTable
                && array_values(array_map('strval', $foreign['foreign_columns'])) === $foreignColumns,
        );
    }

    /** @return list<string> */
    public static function tablesWithColumn(string $columnName, ?bool $nullable = null): array
    {
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $tableName = (string) $table['name'];
            $column = collect(Schema::getColumns($tableName))
                ->first(fn (array $candidate): bool => (string) $candidate['name'] === $columnName);

            if ($column !== null && ($nullable === null || (bool) $column['nullable'] === $nullable)) {
                $tables[] = $tableName;
            }
        }

        return $tables;
    }
}
