<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RegistryMutationScanner
{
    /** @var list<string> */
    private const QUERY_WRITES = [
        'insert',
        'insertGetId',
        'insertOrIgnore',
        'update',
        'updateOrInsert',
        'upsert',
        'delete',
        'truncate',
        'increment',
        'decrement',
    ];

    /** @var list<string> */
    private const ELOQUENT_WRITES = [
        'create',
        'forceCreate',
        'firstOrCreate',
        'updateOrCreate',
        'save',
        'saveOrFail',
        'push',
        'update',
        'delete',
        'forceDelete',
        'destroy',
        'upsert',
        'insert',
        'insertOrIgnore',
        'truncate',
    ];

    /**
     * @param  list<string>  $roots
     * @param  list<string>  $registryTables
     * @return list<RegistryMutationFinding>
     */
    public function scan(array $roots, array $registryTables): array
    {
        $findings = [];
        foreach ($this->phpFiles($roots) as $file) {
            $source = file_get_contents($file);
            if (! is_string($source)) {
                continue;
            }
            array_push(
                $findings,
                ...$this->scanSource($source, $file, $registryTables),
            );
        }

        usort(
            $findings,
            static fn (RegistryMutationFinding $left, RegistryMutationFinding $right): int => [
                $left->relativeFile,
                $left->line,
                $left->table,
                $left->method,
            ] <=> [
                $right->relativeFile,
                $right->line,
                $right->table,
                $right->method,
            ],
        );

        return $findings;
    }

    /**
     * @param  list<string>  $registryTables
     * @return list<RegistryMutationFinding>
     */
    private function scanSource(string $source, string $file, array $registryTables): array
    {
        $code = $this->codeWithoutComments($source);
        $tablesPattern = implode('|', array_map(
            static fn (string $table): string => preg_quote($table, '/'),
            $registryTables,
        ));
        if ($tablesPattern === '') {
            return [];
        }
        $facades = ['DB'];
        preg_match_all(
            '/\buse\s+Illuminate\\\\Support\\\\Facades\\\\DB(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/',
            $code,
            $aliases,
        );
        foreach ($aliases[1] as $alias) {
            $facades[] = $alias !== '' ? $alias : 'DB';
        }
        $facadePattern = implode('|', array_map(
            static fn (string $facade): string => preg_quote($facade, '/'),
            array_unique($facades),
        ));
        $writePattern = implode('|', array_map(
            static fn (string $method): string => preg_quote($method, '/'),
            self::QUERY_WRITES,
        ));
        $findings = [];

        $tableCall = '/\b(?:'.$facadePattern.')\s*::\s*table\s*\(\s*([\'"])('
            .$tablesPattern.')\1\s*\)([^;]*)/s';
        preg_match_all($tableCall, $code, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($calls as $call) {
            $this->appendChainWrites(
                $findings,
                $source,
                $file,
                $call[2][0],
                $call[3][0],
                $call[3][1],
                $writePattern,
            );
        }

        $aliasesByVariable = [];
        $aliasPattern = '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:'.$facadePattern
            .')\s*::\s*table\s*\(\s*([\'"])('.$tablesPattern.')\2\s*\)/s';
        preg_match_all($aliasPattern, $code, $assignments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($assignments as $assignment) {
            $aliasesByVariable[$assignment[1][0]] = $assignment[3][0];
        }
        do {
            $changed = false;
            preg_match_all(
                '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*;/',
                $code,
                $copies,
                PREG_SET_ORDER,
            );
            foreach ($copies as $copy) {
                if (isset($aliasesByVariable[$copy[2]]) && ! isset($aliasesByVariable[$copy[1]])) {
                    $aliasesByVariable[$copy[1]] = $aliasesByVariable[$copy[2]];
                    $changed = true;
                }
            }
        } while ($changed);
        foreach ($aliasesByVariable as $variable => $table) {
            preg_match_all(
                '/\$'.preg_quote($variable, '/').'\s*->\s*('.$writePattern.')\s*\(/',
                $code,
                $writes,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
            );
            foreach ($writes as $write) {
                $this->append($findings, $source, $file, $table, $write[1][0], $write[1][1]);
            }
        }

        $presentTables = array_values(array_filter(
            $registryTables,
            static fn (string $table): bool => str_contains($code, "'{$table}'")
                || str_contains($code, "\"{$table}\""),
        ));
        preg_match_all(
            '/(?:'.$facadePattern.')\s*::\s*table\s*\(\s*\$[A-Za-z_][A-Za-z0-9_]*(?:\[[^\]]+\])?\s*\)([^;]*)/s',
            $code,
            $dynamicCalls,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        foreach ($dynamicCalls as $call) {
            preg_match_all(
                '/->\s*('.$writePattern.')\s*\(/',
                $call[1][0],
                $writes,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
            );
            foreach ($writes as $write) {
                foreach ($presentTables as $table) {
                    $this->append(
                        $findings,
                        $source,
                        $file,
                        $table,
                        $write[1][0],
                        $call[1][1] + $write[1][1],
                    );
                }
            }
        }

        $this->appendRawSqlWrites(
            $findings,
            $source,
            $code,
            $file,
            $facadePattern,
            $registryTables,
        );
        $this->appendEloquentWrites(
            $findings,
            $source,
            $code,
            $file,
            $registryTables,
        );

        $unique = [];
        foreach ($findings as $finding) {
            $unique[implode('|', [
                $finding->relativeFile,
                $finding->table,
                $finding->method,
                $finding->line,
            ])] = $finding;
        }

        return array_values($unique);
    }

    /**
     * @param  list<RegistryMutationFinding>  $findings
     */
    private function appendChainWrites(
        array &$findings,
        string $source,
        string $file,
        string $table,
        string $chain,
        int $offset,
        string $writePattern,
    ): void {
        preg_match_all(
            '/->\s*('.$writePattern.')\s*\(/',
            $chain,
            $writes,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        foreach ($writes as $write) {
            $this->append(
                $findings,
                $source,
                $file,
                $table,
                $write[1][0],
                $offset + $write[1][1],
            );
        }
    }

    /**
     * @param  list<RegistryMutationFinding>  $findings
     * @param  list<string>  $registryTables
     */
    private function appendRawSqlWrites(
        array &$findings,
        string $source,
        string $code,
        string $file,
        string $facadePattern,
        array $registryTables,
    ): void {
        preg_match_all(
            '/\b(?:'.$facadePattern.')\s*::\s*(?:statement|unprepared|raw)\s*\((.*?)\)\s*;?/is',
            $code,
            $calls,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        foreach ($calls as $call) {
            $sql = $call[1][0];
            if (preg_match('/\b(?:insert|update|delete|truncate|replace|alter|drop)\b/i', $sql) !== 1) {
                continue;
            }
            foreach ($registryTables as $table) {
                if (preg_match('/\b'.preg_quote($table, '/').'\b/i', $sql) === 1) {
                    $this->append(
                        $findings,
                        $source,
                        $file,
                        $table,
                        'raw',
                        $call[0][1],
                    );
                }
            }
        }
    }

    /**
     * @param  list<RegistryMutationFinding>  $findings
     * @param  list<string>  $registryTables
     */
    private function appendEloquentWrites(
        array &$findings,
        string $source,
        string $code,
        string $file,
        array $registryTables,
    ): void {
        $classes = [];
        foreach ($registryTables as $table) {
            preg_match_all(
                '/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+[^{]*Model[^{]*\{(?:(?!\bclass\b).)*?'
                    .'\$table\s*=\s*([\'"])'.preg_quote($table, '/').'\2\s*;/s',
                $code,
                $matches,
                PREG_SET_ORDER,
            );
            foreach ($matches as $match) {
                $classes[$match[1]] = $table;
            }
        }
        $writePattern = implode('|', array_map(
            static fn (string $method): string => preg_quote($method, '/'),
            self::ELOQUENT_WRITES,
        ));
        foreach ($classes as $class => $table) {
            preg_match_all(
                '/\b'.preg_quote($class, '/').'\s*::(?:(?!;).)*?\b('.$writePattern.')\s*\(/s',
                $code,
                $writes,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
            );
            foreach ($writes as $write) {
                $this->append($findings, $source, $file, $table, $write[1][0], $write[1][1]);
            }

            $instances = [];
            preg_match_all(
                '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*new\s+'.preg_quote($class, '/').'\b/',
                $code,
                $assignments,
                PREG_SET_ORDER,
            );
            foreach ($assignments as $assignment) {
                $instances[] = $assignment[1];
            }
            foreach (array_unique($instances) as $variable) {
                preg_match_all(
                    '/\$'.preg_quote($variable, '/').'\s*->\s*('.$writePattern.')\s*\(/',
                    $code,
                    $writes,
                    PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
                );
                foreach ($writes as $write) {
                    $this->append($findings, $source, $file, $table, $write[1][0], $write[1][1]);
                }
            }
        }
    }

    /** @param list<RegistryMutationFinding> $findings */
    private function append(
        array &$findings,
        string $source,
        string $file,
        string $table,
        string $method,
        int $offset,
    ): void {
        $normalizedFile = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', (string) getcwd()), '/');
        $relative = str_starts_with($normalizedFile, $base.'/')
            ? substr($normalizedFile, strlen($base) + 1)
            : $normalizedFile;
        $findings[] = new RegistryMutationFinding(
            relativeFile: $relative,
            table: $table,
            method: $method,
            line: substr_count(substr($source, 0, $offset), "\n") + 1,
        );
    }

    private function codeWithoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= preg_replace('/[^\n]/', ' ', $token[1]);
            } else {
                $code .= is_array($token) ? $token[1] : $token;
            }
        }

        return $code;
    }

    /** @param list<string> $roots @return list<string> */
    private function phpFiles(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            if (is_file($root) && pathinfo($root, PATHINFO_EXTENSION) === 'php') {
                $files[] = $root;
            } elseif (is_dir($root)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        return $files;
    }
}
