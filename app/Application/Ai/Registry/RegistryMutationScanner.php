<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
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

    /** @var list<string> */
    private const DIRECT_SQL_WRITES = [
        'insert',
        'update',
        'delete',
        'affectingStatement',
        'statement',
        'unprepared',
        'raw',
    ];

    /** @var array<string, string> */
    private array $modelTables = [];

    /** @var list<string> */
    private array $registryTables = [];

    /** @var list<RegistryMutationFinding> */
    private array $findings = [];

    private string $source = '';

    private string $file = '';

    /**
     * @param  list<string>  $roots
     * @param  list<string>  $registryTables
     * @return list<RegistryMutationFinding>
     */
    public function scan(array $roots, array $registryTables): array
    {
        $this->registryTables = array_values(array_unique($registryTables));
        $files = $this->phpFiles($roots);
        $modelFiles = array_values(array_unique([
            ...$files,
            ...$this->phpFiles([getcwd().DIRECTORY_SEPARATOR.'app']),
        ]));
        $this->modelTables = $this->indexModels($modelFiles);
        $this->findings = [];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            if (! is_string($source)) {
                throw new RuntimeException("Unable to inspect registry mutation source [{$file}].");
            }
            $this->source = $source;
            $this->file = $file;
            $environment = [];
            $this->analyzeStatements($this->parse($source, $file), $environment);
        }

        $unique = [];
        foreach ($this->findings as $finding) {
            $unique[implode('|', [
                $finding->relativeFile,
                $finding->table,
                $finding->method,
                $finding->line,
            ])] = $finding;
        }
        $findings = array_values($unique);
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
     * @param  list<string>  $files
     * @return array<string, string>
     */
    private function indexModels(array $files): array
    {
        $models = [];
        $finder = new NodeFinder;
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if (! is_string($source)) {
                continue;
            }
            $nodes = $this->parse($source, $file);
            /** @var Stmt\Class_ $class */
            foreach ($finder->findInstanceOf($nodes, Stmt\Class_::class) as $class) {
                if ($class->name === null || ! $this->extendsModel($class)) {
                    continue;
                }
                $table = null;
                foreach ($class->getProperties() as $property) {
                    foreach ($property->props as $propertyItem) {
                        if ($propertyItem->name->toString() === 'table'
                            && $propertyItem->default instanceof String_) {
                            $table = $propertyItem->default->value;
                        }
                    }
                }
                if ($table === null || ! in_array($table, $this->registryTables, true)) {
                    continue;
                }
                $className = $class->namespacedName?->toString() ?? $class->name->toString();
                $models[strtolower(ltrim($className, '\\'))] = $table;
            }
        }

        return $models;
    }

    /**
     * @param  list<Stmt>  $statements
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     */
    private function analyzeStatements(array $statements, array &$environment): void
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Stmt\Expression) {
                $this->analyzeExpression($statement->expr, $environment);
            } elseif ($statement instanceof Stmt\Return_ && $statement->expr !== null) {
                $this->analyzeExpression($statement->expr, $environment);
            } elseif ($statement instanceof Stmt\If_) {
                $this->analyzeExpression($statement->cond, $environment);
                $branches = [];
                $branch = $environment;
                $this->analyzeStatements($statement->stmts, $branch);
                $branches[] = $branch;
                foreach ($statement->elseifs as $elseif) {
                    $branch = $environment;
                    $this->analyzeExpression($elseif->cond, $branch);
                    $this->analyzeStatements($elseif->stmts, $branch);
                    $branches[] = $branch;
                }
                $branch = $environment;
                if ($statement->else !== null) {
                    $this->analyzeStatements($statement->else->stmts, $branch);
                }
                $branches[] = $branch;
                $environment = $this->mergeEnvironments($branches);
            } elseif ($statement instanceof Stmt\Foreach_) {
                $this->analyzeExpression($statement->expr, $environment);
                $branch = $environment;
                $this->assignForeachOrigin($statement, $branch);
                $this->analyzeStatements($statement->stmts, $branch);
                $environment = $this->mergeEnvironments([$environment, $branch]);
            } elseif ($statement instanceof Stmt\TryCatch) {
                $branches = [];
                $branch = $environment;
                $this->analyzeStatements($statement->stmts, $branch);
                $branches[] = $branch;
                foreach ($statement->catches as $catch) {
                    $branch = $environment;
                    $this->analyzeStatements($catch->stmts, $branch);
                    $branches[] = $branch;
                }
                if ($statement->finally !== null) {
                    foreach ($branches as &$branch) {
                        $this->analyzeStatements($statement->finally->stmts, $branch);
                    }
                }
                $environment = $this->mergeEnvironments($branches);
            } elseif ($statement instanceof Stmt\ClassMethod
                || $statement instanceof Stmt\Function_) {
                $local = [];
                $this->analyzeStatements($statement->stmts ?? [], $local);
            } elseif ($statement instanceof Stmt\Namespace_
                || $statement instanceof Stmt\Class_) {
                $local = $statement instanceof Stmt\Namespace_ ? $environment : [];
                $this->analyzeStatements($statement->stmts, $local);
            } else {
                $this->analyzeNestedNodes($statement, $environment);
            }
        }
    }

    /**
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     * @return array{kind:string,tables:list<string>}|null
     */
    private function analyzeExpression(
        Expr $expression,
        array &$environment,
    ): ?array {
        if ($expression instanceof Expr\Assign) {
            $origin = $this->analyzeExpression($expression->expr, $environment);
            if ($expression->var instanceof Expr\Variable
                && is_string($expression->var->name)) {
                if ($origin === null) {
                    unset($environment[$expression->var->name]);
                } else {
                    $environment[$expression->var->name] = $origin;
                }
            }

            return $origin;
        }
        if ($expression instanceof Expr\StaticCall) {
            return $this->analyzeStaticCall($expression, $environment);
        }
        if ($expression instanceof Expr\MethodCall) {
            $origin = $expression->var instanceof Expr
                ? $this->analyzeExpression($expression->var, $environment)
                : null;
            foreach ($expression->args as $argument) {
                if ($argument->value instanceof Expr) {
                    $this->analyzeExpression($argument->value, $environment);
                }
            }
            $method = $expression->name instanceof Node\Identifier
                ? $expression->name->toString()
                : null;
            if ($origin !== null && $method !== null) {
                $writes = $origin['kind'] === 'model'
                    ? self::ELOQUENT_WRITES
                    : self::QUERY_WRITES;
                if (in_array($method, $writes, true)) {
                    $this->appendForTables($origin['tables'], $method, $expression->getStartLine());
                }

                return $origin;
            }

            return null;
        }
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return $environment[$expression->name] ?? null;
        }
        if ($expression instanceof Expr\New_
            && $expression->class instanceof Name
            && ($table = $this->modelTable($expression->class)) !== null) {
            return ['kind' => 'model', 'tables' => [$table]];
        }

        $this->analyzeNestedNodes($expression, $environment);

        return null;
    }

    /**
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     * @return array{kind:string,tables:list<string>}|null
     */
    private function analyzeStaticCall(
        Expr\StaticCall $call,
        array &$environment,
    ): ?array {
        foreach ($call->args as $argument) {
            if ($argument->value instanceof Expr) {
                $this->analyzeExpression($argument->value, $environment);
            }
        }
        if (! $call->class instanceof Name || ! $call->name instanceof Node\Identifier) {
            return null;
        }
        $method = $call->name->toString();
        if ($this->isDatabaseFacade($call->class)) {
            if ($method === 'table') {
                $table = $this->literalString($call->args[0]->value ?? null);
                $tables = $table === null
                    ? $this->registryTablesReferencedBySource()
                    : [$this->normalizeTable($table)];
                $tables = array_values(array_intersect($tables, $this->registryTables));

                return $tables === [] ? null : ['kind' => 'table', 'tables' => $tables];
            }
            if (in_array($method, self::DIRECT_SQL_WRITES, true)) {
                $sql = $this->sqlValue($call->args[0]->value ?? null);
                if ($sql !== null) {
                    $this->appendSqlWrites(
                        $sql,
                        in_array($method, ['statement', 'unprepared', 'raw'], true)
                            ? 'raw'
                            : $method,
                        $call->getStartLine(),
                    );
                }
            }

            return null;
        }

        $table = $this->modelTable($call->class);
        if ($table === null) {
            return null;
        }
        if (in_array($method, self::ELOQUENT_WRITES, true)) {
            $this->appendForTables([$table], $method, $call->getStartLine());
        }

        return ['kind' => 'model', 'tables' => [$table]];
    }

    /**
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     */
    private function analyzeNestedNodes(Node $node, array &$environment): void
    {
        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            if ($child instanceof Expr) {
                $this->analyzeExpression($child, $environment);
            } elseif ($child instanceof Stmt) {
                $this->analyzeStatements([$child], $environment);
            } elseif (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Expr) {
                        $this->analyzeExpression($item, $environment);
                    } elseif ($item instanceof Stmt) {
                        $this->analyzeStatements([$item], $environment);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     */
    private function assignForeachOrigin(Stmt\Foreach_ $foreach, array &$environment): void
    {
        if (! $foreach->valueVar instanceof Expr\Array_
            || ! $foreach->expr instanceof Expr\Array_) {
            return;
        }
        $tables = [];
        foreach ($foreach->expr->items as $item) {
            if (! $item?->value instanceof Expr\Array_) {
                continue;
            }
            $table = $this->literalString($item->value->items[0]?->value ?? null);
            if ($table !== null && in_array($table, $this->registryTables, true)) {
                $tables[] = $table;
            }
        }
        $first = $foreach->valueVar->items[0]?->value ?? null;
        if ($first instanceof Expr\Variable && is_string($first->name) && $tables !== []) {
            $environment[$first->name] = [
                'kind' => 'table-name',
                'tables' => array_values(array_unique($tables)),
            ];
        }
    }

    /**
     * @param  list<array<string, array{kind:string,tables:list<string>}>>  $environments
     * @return array<string, array{kind:string,tables:list<string>}>
     */
    private function mergeEnvironments(array $environments): array
    {
        $names = [];
        foreach ($environments as $environment) {
            $names = [...$names, ...array_keys($environment)];
        }
        $merged = [];
        foreach (array_unique($names) as $name) {
            $origins = array_values(array_filter(array_map(
                static fn (array $environment): ?array => $environment[$name] ?? null,
                $environments,
            )));
            if (count($origins) !== count($environments)) {
                continue;
            }
            $kinds = array_unique(array_column($origins, 'kind'));
            if (count($kinds) !== 1) {
                continue;
            }
            $tables = [];
            foreach ($origins as $origin) {
                $tables = [...$tables, ...$origin['tables']];
            }
            $merged[$name] = [
                'kind' => $kinds[0],
                'tables' => array_values(array_unique($tables)),
            ];
        }

        return $merged;
    }

    private function appendSqlWrites(string $sql, string $method, int $line): void
    {
        if (preg_match('/\b(?:insert|update|delete|truncate|replace|alter|drop)\b/i', $sql) !== 1) {
            return;
        }
        foreach ($this->registryTables as $table) {
            if (preg_match('/\b'.preg_quote($table, '/').'\b/i', $sql) === 1) {
                $this->append($table, $method, $line);
            }
        }
    }

    /** @param list<string> $tables */
    private function appendForTables(array $tables, string $method, int $line): void
    {
        foreach ($tables as $table) {
            if (in_array($table, $this->registryTables, true)) {
                $this->append($table, $method, $line);
            }
        }
    }

    private function append(string $table, string $method, int $line): void
    {
        $normalizedFile = str_replace('\\', '/', $this->file);
        $base = rtrim(str_replace('\\', '/', (string) getcwd()), '/');
        $relative = str_starts_with($normalizedFile, $base.'/')
            ? substr($normalizedFile, strlen($base) + 1)
            : $normalizedFile;
        $this->findings[] = new RegistryMutationFinding(
            relativeFile: $relative,
            table: $table,
            method: $method,
            line: $line,
        );
    }

    /** @return list<Stmt> */
    private function parse(string $source, string $file): array
    {
        try {
            $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new NameResolver);

            return $traverser->traverse($nodes);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                "Unable to parse registry mutation source [{$file}].",
                previous: $exception,
            );
        }
    }

    private function extendsModel(Stmt\Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }
        $name = strtolower(ltrim($class->extends->toString(), '\\'));

        return $name === 'illuminate\\database\\eloquent\\model'
            || str_ends_with($name, '\\model')
            || $name === 'model';
    }

    private function modelTable(Name $class): ?string
    {
        return $this->modelTables[strtolower(ltrim($class->toString(), '\\'))] ?? null;
    }

    private function isDatabaseFacade(Name $class): bool
    {
        $name = strtolower(ltrim($class->toString(), '\\'));

        return $name === 'illuminate\\support\\facades\\db' || $name === 'db';
    }

    private function sqlValue(mixed $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }
        if ($node instanceof Expr\BinaryOp\Concat) {
            $left = $this->sqlValue($node->left);
            $right = $this->sqlValue($node->right);

            return $left !== null && $right !== null ? $left.$right : null;
        }
        if ($node instanceof Expr\StaticCall
            && $node->class instanceof Name
            && $node->name instanceof Node\Identifier
            && $this->isDatabaseFacade($node->class)
            && $node->name->toString() === 'raw') {
            return $this->sqlValue($node->args[0]->value ?? null);
        }

        return null;
    }

    private function literalString(mixed $node): ?string
    {
        return $node instanceof String_ ? $node->value : null;
    }

    private function normalizeTable(string $table): string
    {
        return preg_split('/\s+(?:as\s+)?/i', trim($table), 2)[0] ?? $table;
    }

    /** @return list<string> */
    private function registryTablesReferencedBySource(): array
    {
        return array_values(array_filter(
            $this->registryTables,
            fn (string $table): bool => str_contains($this->source, "'{$table}'")
                || str_contains($this->source, "\"{$table}\""),
        ));
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
        sort($files);

        return array_values(array_unique($files));
    }
}
