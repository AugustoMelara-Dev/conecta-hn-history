<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
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
        'insertOrIgnoreReturning',
        'insertUsing',
        'insertOrIgnoreUsing',
        'update',
        'updateFrom',
        'updateOrInsert',
        'upsert',
        'delete',
        'truncate',
        'increment',
        'decrement',
        'incrementEach',
        'decrementEach',
    ];

    /** @var list<string> */
    private const ELOQUENT_WRITES = [
        'create',
        'createQuietly',
        'createOrFirst',
        'forceCreate',
        'forceCreateQuietly',
        'firstOrCreate',
        'updateOrCreate',
        'incrementOrCreate',
        'fillAndInsert',
        'fillAndInsertGetId',
        'fillAndInsertOrIgnore',
        'save',
        'saveOrIgnore',
        'saveOrFail',
        'saveQuietly',
        'push',
        'pushQuietly',
        'update',
        'updateOrFail',
        'updateQuietly',
        'delete',
        'deleteOrFail',
        'deleteQuietly',
        'forceDelete',
        'forceDeleteQuietly',
        'forceDestroy',
        'destroy',
        'restore',
        'restoreQuietly',
        'touch',
        'touchQuietly',
        'upsert',
        'insert',
        'insertGetId',
        'insertOrIgnore',
        'insertOrIgnoreReturning',
        'insertUsing',
        'insertOrIgnoreUsing',
        'updateFrom',
        'updateOrInsert',
        'truncate',
        'increment',
        'decrement',
        'incrementEach',
        'decrementEach',
        'incrementQuietly',
        'decrementQuietly',
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

    private const SQL_EXPRESSION_SENTINEL = '__DOVRA_DYNAMIC_SQL_EXPRESSION__';

    /** @var array<string, string> */
    private array $modelTables = [];

    /** @var list<string> */
    private array $registryTables = [];

    /** @var list<RegistryMutationFinding> */
    private array $findings = [];

    private string $file = '';

    /** @var array<string, list<string>> */
    private array $constantTables = [];

    /** @var array<string, list<string>> */
    private array $methodTableReturns = [];

    /** @var array<string, array<int, list<string>>> */
    private array $methodTupleReturns = [];

    /** @var array<string, Stmt\ClassMethod> */
    private array $methodNodes = [];

    /** @var array<string, true> */
    private array $internallyCalledMethods = [];

    /** @var array<string, true> */
    private array $methodCallStack = [];

    /**
     * @return array{query:list<string>,eloquent:list<string>,direct_sql:list<string>}
     */
    public static function mutationInventory(): array
    {
        return [
            'query' => self::QUERY_WRITES,
            'eloquent' => self::ELOQUENT_WRITES,
            'direct_sql' => self::DIRECT_SQL_WRITES,
        ];
    }

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
            $this->file = $file;
            $nodes = $this->parse($source, $file);
            $this->indexSourceTables($nodes);
            $environment = [];
            $this->analyzeStatements($nodes, $environment);
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
            } elseif ($statement instanceof Stmt\ClassMethod) {
                $method = strtolower($statement->name->toString());
                if (isset($this->internallyCalledMethods[$method])
                    && ! $statement->isPublic()) {
                    continue;
                }
                $local = [];
                $this->analyzeStatements($statement->stmts ?? [], $local);
            } elseif ($statement instanceof Stmt\Function_) {
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
            if ($expression->var instanceof Expr\Array_
                || $expression->var instanceof Expr\List_) {
                $origin = $this->analyzeExpression($expression->expr, $environment);
                $tuple = $this->tupleTablesFromExpression($expression->expr);
                foreach ($expression->var->items as $index => $item) {
                    if (! $item?->value instanceof Expr\Variable
                        || ! is_string($item->value->name)
                        || ! isset($tuple[$index])) {
                        continue;
                    }
                    $environment['var:'.$item->value->name] = [
                        'kind' => 'table-name',
                        'tables' => $tuple[$index],
                    ];
                }

                return $origin;
            }
            $origin = $this->analyzeExpression($expression->expr, $environment);
            $key = $this->originKey($expression->var);
            if ($key !== null) {
                if ($origin === null) {
                    unset($environment[$key]);
                    if (str_starts_with($key, 'var:')) {
                        foreach (array_keys($environment) as $environmentKey) {
                            if (str_starts_with($environmentKey, $key.'->')) {
                                unset($environment[$environmentKey]);
                            }
                        }
                    }
                } else {
                    $environment[$key] = $origin;
                }
            }

            return $origin;
        }
        if ($expression instanceof Expr\Ternary) {
            $conditionOrigin = $this->analyzeExpression($expression->cond, $environment);
            $ifEnvironment = $environment;
            $elseEnvironment = $environment;
            $ifOrigin = $expression->if instanceof Expr
                ? $this->analyzeExpression($expression->if, $ifEnvironment)
                : $conditionOrigin;
            $elseOrigin = $this->analyzeExpression($expression->else, $elseEnvironment);
            $environment = $this->mergeEnvironments([$ifEnvironment, $elseEnvironment]);

            return $this->mergeOrigins([$ifOrigin, $elseOrigin]);
        }
        if ($expression instanceof Expr\BinaryOp\Coalesce) {
            $leftEnvironment = $environment;
            $rightEnvironment = $environment;
            $leftOrigin = $this->analyzeExpression($expression->left, $leftEnvironment);
            $rightOrigin = $this->analyzeExpression($expression->right, $rightEnvironment);
            $environment = $this->mergeEnvironments([$leftEnvironment, $rightEnvironment]);

            return $this->mergeOrigins([$leftOrigin, $rightOrigin]);
        }
        if ($expression instanceof Expr\Match_) {
            $this->analyzeExpression($expression->cond, $environment);
            $origins = [];
            $branches = [];
            foreach ($expression->arms as $arm) {
                $branch = $environment;
                foreach ($arm->conds ?? [] as $condition) {
                    $this->analyzeExpression($condition, $branch);
                }
                $origins[] = $this->analyzeExpression($arm->body, $branch);
                $branches[] = $branch;
            }
            if ($branches !== []) {
                $environment = $this->mergeEnvironments($branches);
            }

            return $this->mergeOrigins($origins);
        }
        if ($expression instanceof Expr\StaticCall) {
            return $this->analyzeStaticCall($expression, $environment);
        }
        if ($expression instanceof Expr\MethodCall
            || $expression instanceof Expr\NullsafeMethodCall) {
            $origin = $expression->var instanceof Expr
                ? $this->analyzeExpression($expression->var, $environment)
                : null;
            $argumentOrigins = [];
            foreach ($expression->args as $argument) {
                if ($argument->value instanceof Expr) {
                    $argumentOrigins[] = $this->analyzeExpression($argument->value, $environment);
                } else {
                    $argumentOrigins[] = null;
                }
            }
            $method = $expression->name instanceof Node\Identifier
                ? $expression->name->toString()
                : null;
            $methodKey = $method === null ? null : strtolower($method);
            if ($origin !== null) {
                if ($methodKey === null) {
                    if ($origin['kind'] === 'connection') {
                        $this->appendDynamicDirectMethod(
                            $expression->args[0]->value ?? null,
                            $expression->getStartLine(),
                        );
                    } else {
                        $this->appendForTables(
                            $origin['tables'],
                            '*dynamic_method*',
                            $expression->getStartLine(),
                        );
                    }

                    return $origin;
                }
                if ($origin['kind'] === 'connection' && $methodKey === 'table') {
                    return $this->tableOrigin(
                        $expression->args[0]->value ?? null,
                        $environment,
                    );
                }
                $directMethod = $this->canonicalMethod(
                    $methodKey,
                    self::DIRECT_SQL_WRITES,
                );
                if ($origin['kind'] === 'connection' && $directMethod !== null) {
                    $this->appendDirectSqlCall(
                        $directMethod,
                        $expression->args[0]->value ?? null,
                        $expression->getStartLine(),
                    );

                    return null;
                }
                $writes = $origin['kind'] === 'model'
                    ? self::ELOQUENT_WRITES
                    : self::QUERY_WRITES;
                $canonicalMethod = $this->canonicalMethod($methodKey, $writes);
                if ($canonicalMethod !== null) {
                    $this->appendForTables(
                        $origin['tables'],
                        $canonicalMethod,
                        $expression->getStartLine(),
                    );
                }

                return $origin;
            }
            if ($methodKey !== null
                && $expression->var instanceof Expr\Variable
                && $expression->var->name === 'this') {
                $this->analyzeLocalMethod($methodKey, $argumentOrigins, $environment);
            }
            if ($methodKey !== null && isset($this->methodTableReturns[$methodKey])) {
                return [
                    'kind' => 'table-record',
                    'tables' => $this->methodTableReturns[$methodKey],
                ];
            }

            return null;
        }
        if ($expression instanceof Expr\Variable
            || $expression instanceof Expr\PropertyFetch
            || $expression instanceof Expr\ArrayDimFetch) {
            $key = $this->originKey($expression);
            if ($key !== null && isset($environment[$key])) {
                return $environment[$key];
            }
            if ($expression instanceof Expr\ArrayDimFetch
                && $this->literalString($expression->dim) === 'table') {
                $base = $this->analyzeExpression($expression->var, $environment);
                if ($base !== null && $base['kind'] === 'table-record') {
                    return ['kind' => 'table-name', 'tables' => $base['tables']];
                }
            }

            return null;
        }
        if ($expression instanceof Expr\New_
            && $expression->class instanceof Name
            && ($table = $this->modelTable($expression->class)) !== null) {
            return ['kind' => 'model', 'tables' => [$table]];
        }
        if ($expression instanceof String_) {
            $table = $this->normalizeTable($expression->value);

            return $table === ''
                ? null
                : ['kind' => 'table-name', 'tables' => [$table]];
        }
        if ($expression instanceof Expr\Closure) {
            $local = [];
            foreach ($expression->uses as $use) {
                if (! is_string($use->var->name)) {
                    continue;
                }
                $key = 'var:'.$use->var->name;
                if (isset($environment[$key])) {
                    $local[$key] = $environment[$key];
                }
            }
            $this->analyzeStatements($expression->stmts, $local);

            return null;
        }
        if ($expression instanceof Expr\ArrowFunction) {
            $local = $environment;
            $this->analyzeExpression($expression->expr, $local);

            return null;
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
        if (! $call->class instanceof Name) {
            return null;
        }
        $method = $call->name instanceof Node\Identifier
            ? strtolower($call->name->toString())
            : null;
        if ($this->isDatabaseFacade($call->class)) {
            if ($method === null) {
                $this->appendDynamicDirectMethod(
                    $call->args[0]->value ?? null,
                    $call->getStartLine(),
                );

                return null;
            }
            if ($method === 'table') {
                return $this->tableOrigin($call->args[0]->value ?? null, $environment);
            }
            if ($method === 'connection') {
                return ['kind' => 'connection', 'tables' => []];
            }
            $directMethod = $this->canonicalMethod($method, self::DIRECT_SQL_WRITES);
            if ($directMethod !== null) {
                $this->appendDirectSqlCall(
                    $directMethod,
                    $call->args[0]->value ?? null,
                    $call->getStartLine(),
                );
            }

            return null;
        }

        $table = $this->modelTable($call->class);
        if ($table === null) {
            return null;
        }
        if ($method === null) {
            $this->appendForTables([$table], '*dynamic_method*', $call->getStartLine());

            return ['kind' => 'model', 'tables' => [$table]];
        }
        $canonicalMethod = $this->canonicalMethod($method, self::ELOQUENT_WRITES);
        if ($canonicalMethod !== null) {
            $this->appendForTables([$table], $canonicalMethod, $call->getStartLine());
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
        $knownTables = $this->tablesFromExpression($foreach->expr);
        if ($knownTables !== []) {
            if ($foreach->keyVar instanceof Expr\Variable
                && is_string($foreach->keyVar->name)) {
                $environment['var:'.$foreach->keyVar->name] = [
                    'kind' => 'table-name',
                    'tables' => $knownTables,
                ];
            }
            if ($foreach->valueVar instanceof Expr\Variable
                && is_string($foreach->valueVar->name)) {
                $environment['var:'.$foreach->valueVar->name] = [
                    'kind' => 'table-record',
                    'tables' => $knownTables,
                ];
            }
        }
        if ((! $foreach->valueVar instanceof Expr\Array_
                && ! $foreach->valueVar instanceof Expr\List_)
            || ! $foreach->expr instanceof Expr\Array_) {
            return;
        }
        $tables = [];
        foreach ($foreach->expr->items as $item) {
            if (! $item?->value instanceof Expr\Array_) {
                continue;
            }
            $table = $this->literalString($item->value->items[0]?->value ?? null);
            if ($table !== null) {
                $tables[] = $table;
            }
        }
        $first = $foreach->valueVar->items[0]?->value ?? null;
        if ($first instanceof Expr\Variable && is_string($first->name) && $tables !== []) {
            $environment['var:'.$first->name] = [
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
            $origin = $this->mergeOrigins($origins);
            if ($origin !== null) {
                $merged[$name] = $origin;
            }
        }

        return $merged;
    }

    /**
     * @param  list<array{kind:string,tables:list<string>}|null>  $origins
     * @return array{kind:string,tables:list<string>}|null
     */
    private function mergeOrigins(array $origins): ?array
    {
        $known = array_values(array_filter($origins));
        if ($known === []) {
            return null;
        }
        $kinds = array_column($known, 'kind');
        $kind = in_array('model', $kinds, true)
            ? 'model'
            : (in_array('table', $kinds, true)
                ? 'table'
                : (in_array('connection', $kinds, true) ? 'connection' : $kinds[0]));
        $tables = [];
        foreach ($known as $origin) {
            $tables = [...$tables, ...$origin['tables']];
        }

        return [
            'kind' => $kind,
            'tables' => array_values(array_unique($tables)),
        ];
    }

    private function appendDirectSqlCall(string $method, mixed $sqlNode, int $line): void
    {
        $findingMethod = in_array($method, ['statement', 'unprepared', 'raw'], true)
            ? 'raw'
            : $method;
        $sql = $this->sqlValue($sqlNode);
        if ($sql !== null) {
            $this->appendSqlWrites($sql, $findingMethod, $line);

            return;
        }
        if ($method !== 'raw') {
            $this->appendForTables(['*dynamic*'], $findingMethod, $line);
        }
    }

    private function appendDynamicDirectMethod(mixed $sqlNode, int $line): void
    {
        $sql = $this->sqlValue($sqlNode);
        if ($sql === null) {
            $this->appendForTables(['*dynamic*'], '*dynamic_method*', $line);

            return;
        }
        $this->appendSqlWrites($sql, '*dynamic_method*', $line);
    }

    private function appendSqlWrites(string $sql, string $method, int $line): void
    {
        [$normalizedSql, $executableSql] = $this->sqlAnalysisViews($sql);
        if (preg_match(
            '/\b(?:insert|update|delete|truncate|replace|alter|drop|create|rename|merge)\b/i',
            $normalizedSql,
        ) !== 1) {
            return;
        }
        $targets = $this->sqlWriteTargets($normalizedSql);
        if ($targets === []) {
            if (str_contains($executableSql, self::SQL_EXPRESSION_SENTINEL)) {
                $this->append('*dynamic*', $method, $line);

                return;
            }
        }
        $explainedTables = [];
        foreach ($targets as $target) {
            if (str_contains($target, self::SQL_EXPRESSION_SENTINEL)) {
                $this->append('*dynamic*', $method, $line);

                continue;
            }
            $table = $this->normalizeSqlTarget($target);
            if (in_array($table, $this->registryTables, true)) {
                $this->append($table, $method, $line);
                $explainedTables[$table] = true;
            }
        }
        $fallbackTargets = implode(' ', $this->sqlWriteTargetScopes($executableSql));
        foreach ($this->registryTables as $table) {
            if (! isset($explainedTables[$table])
                && preg_match('/\b'.preg_quote($table, '/').'\b/i', $fallbackTargets) === 1) {
                $this->append($table, $method, $line);
            }
        }
    }

    /** @return list<string> */
    private function sqlWriteTargets(string $sql): array
    {
        $target = '(?<table>[^\s(;,]+)';
        $patterns = [
            '/\binsert\s+(?:(?:or\s+\w+|ignore)\s+)?into\s+'.$target.'/i',
            '/\breplace\s+(?:into\s+)?'.$target.'/i',
            '/\bupdate\s+(?:only\s+)?'.$target.'/i',
            '/\bdelete\s+from\s+(?:only\s+)?'.$target.'/i',
            '/\btruncate\s+(?:table\s+)?(?:only\s+)?'.$target.'/i',
            '/\balter\s+table\s+(?:if\s+exists\s+)?(?:only\s+)?'.$target.'/i',
            '/\bdrop\s+table\s+(?:if\s+exists\s+)?'.$target.'/i',
            '/\bcreate\s+table\s+(?:if\s+not\s+exists\s+)?'.$target.'/i',
            '/\brename\s+table\s+'.$target.'/i',
            '/\bmerge\s+into\s+'.$target.'/i',
        ];
        $targets = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches) > 0) {
                $targets = [...$targets, ...$matches['table']];
            }
        }

        return array_values(array_unique(array_map('strval', $targets)));
    }

    /** @return list<string> */
    private function sqlWriteTargetScopes(string $sql): array
    {
        $patterns = [
            '/\binsert\b(?<scope>[^;]*?)(?=\s*\(|\bvalues\b|\bselect\b|\bdefault\b|\bset\b|;|$)/is',
            '/\breplace\b(?<scope>[^;]*?)(?=\s*\(|\bvalues\b|\bselect\b|\bset\b|;|$)/is',
            '/\bupdate\b(?<scope>[^;]*?)(?=\bset\b|;|$)/is',
            '/\bdelete\b(?<scope>[^;]*?)(?=\bwhere\b|\busing\b|\breturning\b|\border\b|\blimit\b|;|$)/is',
            '/\btruncate\b(?<scope>[^;]*?)(?=\brestart\b|\bcontinue\b|\bidentity\b|\bcascade\b|\brestrict\b|;|$)/is',
            '/\balter\s+table\b(?<scope>[^;]*?)(?=\badd\b|\balter\b|\bdrop\b|\brename\b|\bset\b|\breset\b|\battach\b|\bdetach\b|\benable\b|\bdisable\b|\bvalidate\b|\bowner\b|;|$)/is',
            '/\bdrop\s+table\b(?<scope>[^;]*?)(?=\bcascade\b|\brestrict\b|;|$)/is',
            '/\bcreate\s+table\b(?<scope>[^;]*?)(?=\s*\(|\bas\b|;|$)/is',
            '/\bcreate\b[^;]*?\bindex\b[^;]*?\bon\b(?<scope>[^;]*?)(?=\s*\(|\busing\b|\bwhere\b|;|$)/is',
            '/\brename\s+table\b(?<scope>[^;]*?)(?=;|$)/is',
            '/\bmerge\s+into\b(?<scope>[^;]*?)(?=\busing\b|;|$)/is',
        ];
        $scopes = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches) > 0) {
                $scopes = [...$scopes, ...$matches['scope']];
            }
        }

        return array_values(array_unique(array_map('strval', $scopes)));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function sqlAnalysisViews(string $sql): array
    {
        $normalized = '';
        $executable = '';
        $length = strlen($sql);
        for ($offset = 0; $offset < $length;) {
            $remaining = substr($sql, $offset);
            if (str_starts_with($remaining, '/*')) {
                $end = strpos($sql, '*/', $offset + 2);
                $end = $end === false ? $length : $end + 2;
                $masked = $this->maskSqlSegment(substr($sql, $offset, $end - $offset));
                $normalized .= $masked;
                $executable .= $masked;
                $offset = $end;

                continue;
            }
            if (str_starts_with($remaining, '--') || $sql[$offset] === '#') {
                $lineEnd = strcspn($sql, "\r\n", $offset);
                $end = $offset + $lineEnd;
                $masked = str_repeat(' ', $end - $offset);
                $normalized .= $masked;
                $executable .= $masked;
                $offset = $end;

                continue;
            }
            if ($sql[$offset] === '$'
                && preg_match('/\A\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$/', $remaining, $match) === 1) {
                $delimiter = $match[0];
                $end = strpos($sql, $delimiter, $offset + strlen($delimiter));
                $end = $end === false ? $length : $end + strlen($delimiter);
                $segment = substr($sql, $offset, $end - $offset);
                $normalized .= $segment;
                $executable .= $this->maskSqlSegment($segment);
                $offset = $end;

                continue;
            }
            $closingQuote = match ($sql[$offset]) {
                "'", '"', '`' => $sql[$offset],
                '[' => ']',
                default => null,
            };
            if ($closingQuote !== null) {
                $end = $this->sqlQuotedSegmentEnd($sql, $offset, $closingQuote);
                $segment = substr($sql, $offset, $end - $offset);
                $normalized .= $segment;
                $executable .= $closingQuote === "'"
                    ? $this->maskSqlSegment($segment)
                    : $segment;
                $offset = $end;

                continue;
            }
            $normalized .= $sql[$offset];
            $executable .= $sql[$offset];
            $offset++;
        }

        return [$normalized, $executable];
    }

    private function sqlQuotedSegmentEnd(string $sql, int $offset, string $closingQuote): int
    {
        $length = strlen($sql);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($sql[$cursor] !== $closingQuote) {
                continue;
            }
            if ($cursor + 1 < $length && $sql[$cursor + 1] === $closingQuote) {
                $cursor++;

                continue;
            }
            $backslashes = 0;
            for ($previous = $cursor - 1; $previous >= $offset && $sql[$previous] === '\\'; $previous--) {
                $backslashes++;
            }
            if ($backslashes % 2 === 0) {
                return $cursor + 1;
            }
        }

        return $length;
    }

    private function maskSqlSegment(string $segment): string
    {
        return preg_replace('/[^\r\n]/', ' ', $segment) ?? str_repeat(' ', strlen($segment));
    }

    private function normalizeSqlTarget(string $target): string
    {
        $segments = explode('.', trim($target, " \t\n\r\0\x0B`\"[]"));

        return strtolower(trim((string) end($segments), '`"[]'));
    }

    /** @param list<string> $inventory */
    private function canonicalMethod(string $method, array $inventory): ?string
    {
        $method = strtolower($method);
        foreach ($inventory as $candidate) {
            if (strtolower($candidate) === $method) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param list<string> $tables */
    private function appendForTables(array $tables, string $method, int $line): void
    {
        foreach ($tables as $table) {
            if ($table === '*dynamic*' || in_array($table, $this->registryTables, true)) {
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
        if ($node instanceof InterpolatedString) {
            $sql = '';
            foreach ($node->parts as $part) {
                $sql .= $part instanceof InterpolatedStringPart
                    ? $part->value
                    : self::SQL_EXPRESSION_SENTINEL;
            }

            return $sql;
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
            && strtolower($node->name->toString()) === 'raw') {
            return $this->sqlValue($node->args[0]->value ?? null);
        }

        return null;
    }

    private function literalString(mixed $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }
        if ($node instanceof Expr\Cast\String_) {
            return $this->literalString($node->expr);
        }
        if ($node instanceof Expr\ClassConstFetch
            && $node->name instanceof Node\Identifier) {
            $tables = $this->constantTables[$node->name->toString()] ?? [];

            return count($tables) === 1 ? $tables[0] : null;
        }
        if ($node instanceof Expr\FuncCall
            && $node->name instanceof Name
            && strtolower($node->name->toString()) === 'config') {
            return $this->literalString($node->args[1]->value ?? null);
        }

        return null;
    }

    private function normalizeTable(string $table): string
    {
        return preg_split('/\s+(?:as\s+)?/i', trim($table), 2)[0] ?? $table;
    }

    /**
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     * @return array{kind:string,tables:list<string>}
     */
    private function tableOrigin(
        mixed $node,
        array $environment,
    ): array {
        $table = $this->literalString($node);
        if ($table !== null) {
            $normalized = $this->normalizeTable($table);

            return [
                'kind' => 'table',
                'tables' => in_array($normalized, $this->registryTables, true)
                    ? [$normalized]
                    : [],
            ];
        }
        if ($node instanceof Expr) {
            $key = $this->originKey($node);
            $origin = $key === null ? null : ($environment[$key] ?? null);
            if ($origin === null
                && $node instanceof Expr\ArrayDimFetch
                && $this->literalString($node->dim) === 'table') {
                $baseKey = $this->originKey($node->var);
                $base = $baseKey === null ? null : ($environment[$baseKey] ?? null);
                if ($base !== null && $base['kind'] === 'table-record') {
                    $origin = ['kind' => 'table-name', 'tables' => $base['tables']];
                }
            }
            if ($origin !== null && $origin['kind'] === 'table-name') {
                return ['kind' => 'table', 'tables' => $origin['tables']];
            }
        }

        return ['kind' => 'table', 'tables' => ['*dynamic*']];
    }

    private function originKey(mixed $expression): ?string
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return 'var:'.$expression->name;
        }
        if ($expression instanceof Expr\PropertyFetch
            && $expression->name instanceof Node\Identifier) {
            $base = $this->originKey($expression->var);

            return $base === null ? null : $base.'->'.$expression->name->toString();
        }
        if ($expression instanceof Expr\ArrayDimFetch) {
            $base = $this->originKey($expression->var);
            $dimension = $this->literalString($expression->dim);

            return $base === null || $dimension === null
                ? null
                : $base.'['.$dimension.']';
        }

        return null;
    }

    /** @param list<Stmt> $nodes */
    private function indexSourceTables(array $nodes): void
    {
        $this->constantTables = [];
        $this->methodTableReturns = [];
        $this->methodTupleReturns = [];
        $this->methodNodes = [];
        $this->internallyCalledMethods = [];
        $this->methodCallStack = [];
        $finder = new NodeFinder;

        /** @var Node\Const_ $constant */
        foreach ($finder->findInstanceOf($nodes, Node\Const_::class) as $constant) {
            $tables = $this->tableFieldValues($constant->value);
            if ($tables === []) {
                $tables = $this->tablesFromExpression($constant->value);
            }
            if ($tables !== []) {
                $this->constantTables[$constant->name->toString()] = $tables;
            }
        }

        /** @var Stmt\ClassMethod $method */
        foreach ($finder->findInstanceOf($nodes, Stmt\ClassMethod::class) as $method) {
            $methodName = $method->name->toString();
            $this->methodNodes[strtolower($methodName)] = $method;
            $tables = [];
            /** @var Stmt\Return_ $return */
            foreach ($finder->findInstanceOf($method->stmts ?? [], Stmt\Return_::class) as $return) {
                if ($return->expr === null) {
                    continue;
                }
                $tables = [
                    ...$tables,
                    ...$this->tableFieldValues($return->expr),
                    ...$this->tablesFromExpression($return->expr),
                ];
            }
            $tables = array_values(array_unique($tables));
            if ($tables === []) {
                /** @var Expr\ClassConstFetch $constantFetch */
                foreach ($finder->findInstanceOf($method->stmts ?? [], Expr\ClassConstFetch::class) as $constantFetch) {
                    if ($constantFetch->name instanceof Node\Identifier) {
                        $tables = [
                            ...$tables,
                            ...($this->constantTables[$constantFetch->name->toString()] ?? []),
                        ];
                    }
                }
                $tables = array_values(array_unique($tables));
            }
            if ($tables !== []) {
                $this->methodTableReturns[strtolower($methodName)] = $tables;
            }
            $tuple = $this->tupleTablesFromMethod($method);
            if ($tuple !== []) {
                $this->methodTupleReturns[strtolower($methodName)] = $tuple;
            }
        }

        foreach ([Expr\MethodCall::class, Expr\NullsafeMethodCall::class] as $callClass) {
            /** @var Expr\MethodCall|Expr\NullsafeMethodCall $call */
            foreach ($finder->findInstanceOf($nodes, $callClass) as $call) {
                if ($call->var instanceof Expr\Variable
                    && $call->var->name === 'this'
                    && $call->name instanceof Node\Identifier) {
                    $this->internallyCalledMethods[strtolower($call->name->toString())] = true;
                }
            }
        }
    }

    /** @return list<string> */
    private function tablesFromExpression(mixed $expression): array
    {
        if ($expression instanceof Expr\ClassConstFetch
            && $expression->name instanceof Node\Identifier) {
            return $this->constantTables[$expression->name->toString()] ?? [];
        }
        if ($expression instanceof Expr\ArrayDimFetch) {
            return $this->tablesFromExpression($expression->var);
        }
        if ($expression instanceof Expr\Array_) {
            $tables = $this->tableFieldValues($expression);
            if ($tables !== []) {
                return $tables;
            }
            foreach ($expression->items as $item) {
                if ($item === null) {
                    continue;
                }
                $key = $this->literalString($item->key);
                if ($key !== null) {
                    $tables[] = $this->normalizeTable($key);

                    continue;
                }
                $value = $this->literalString($item->value);
                if ($value !== null) {
                    $tables[] = $this->normalizeTable($value);
                }
            }

            return array_values(array_unique($tables));
        }
        if (($expression instanceof Expr\MethodCall
                || $expression instanceof Expr\NullsafeMethodCall)
            && $expression->name instanceof Node\Identifier) {
            return $this->methodTableReturns[strtolower($expression->name->toString())] ?? [];
        }

        $literal = $this->literalString($expression);

        return $literal === null ? [] : [$this->normalizeTable($literal)];
    }

    /** @return array<int, list<string>> */
    private function tupleTablesFromExpression(mixed $expression): array
    {
        if (($expression instanceof Expr\MethodCall
                || $expression instanceof Expr\NullsafeMethodCall)
            && $expression->name instanceof Node\Identifier) {
            return $this->methodTupleReturns[strtolower($expression->name->toString())] ?? [];
        }
        if ($expression instanceof Expr\Match_) {
            $tuple = [];
            foreach ($expression->arms as $arm) {
                if (! $arm->body instanceof Expr\Array_) {
                    continue;
                }
                foreach ($arm->body->items as $index => $item) {
                    if ($item === null) {
                        continue;
                    }
                    $literal = $this->literalString($item->value);
                    if ($literal !== null) {
                        $tuple[$index][] = $this->normalizeTable($literal);
                    }
                }
            }
            foreach ($tuple as $index => $tables) {
                $tuple[$index] = array_values(array_unique($tables));
            }

            return $tuple;
        }

        return [];
    }

    /** @return array<int, list<string>> */
    private function tupleTablesFromMethod(Stmt\ClassMethod $method): array
    {
        $finder = new NodeFinder;
        $variables = [];
        /** @var Expr\Assign $assignment */
        foreach ($finder->findInstanceOf($method->stmts ?? [], Expr\Assign::class) as $assignment) {
            if (! $assignment->var instanceof Expr\Array_
                && ! $assignment->var instanceof Expr\List_) {
                continue;
            }
            $tuple = $this->tupleTablesFromExpression($assignment->expr);
            foreach ($assignment->var->items as $index => $item) {
                if ($item?->value instanceof Expr\Variable
                    && is_string($item->value->name)
                    && isset($tuple[$index])) {
                    $variables[$item->value->name] = $tuple[$index];
                }
            }
        }
        /** @var Stmt\Return_ $return */
        foreach ($finder->findInstanceOf($method->stmts ?? [], Stmt\Return_::class) as $return) {
            if (! $return->expr instanceof Expr\Array_) {
                continue;
            }
            $tuple = [];
            foreach ($return->expr->items as $index => $item) {
                if (! $item?->value instanceof Expr\Variable
                    || ! is_string($item->value->name)
                    || ! isset($variables[$item->value->name])) {
                    continue;
                }
                $tuple[$index] = $variables[$item->value->name];
            }
            if ($tuple !== []) {
                return $tuple;
            }
        }

        return [];
    }

    /** @return list<string> */
    private function tableFieldValues(mixed $expression): array
    {
        if (! $expression instanceof Expr\Array_) {
            return [];
        }
        $tables = [];
        foreach ($expression->items as $item) {
            if ($item === null) {
                continue;
            }
            if ($this->literalString($item->key) === 'table') {
                $table = $this->literalString($item->value);
                if ($table !== null) {
                    $tables[] = $this->normalizeTable($table);
                }
            }
            $tables = [...$tables, ...$this->tableFieldValues($item->value)];
        }

        return array_values(array_unique($tables));
    }

    /**
     * @param  list<array{kind:string,tables:list<string>}|null>  $argumentOrigins
     * @param  array<string, array{kind:string,tables:list<string>}>  $environment
     */
    private function analyzeLocalMethod(
        string $method,
        array $argumentOrigins,
        array &$environment,
    ): void {
        $key = strtolower($method);
        $node = $this->methodNodes[$key] ?? null;
        if ($node === null || isset($this->methodCallStack[$key])) {
            return;
        }
        $local = $environment;
        foreach ($node->params as $index => $parameter) {
            if (! $parameter->var instanceof Expr\Variable
                || ! is_string($parameter->var->name)
                || ! isset($argumentOrigins[$index])
                || $argumentOrigins[$index] === null) {
                continue;
            }
            $local['var:'.$parameter->var->name] = $argumentOrigins[$index];
        }
        $this->methodCallStack[$key] = true;
        try {
            $this->analyzeStatements($node->stmts ?? [], $local);
        } finally {
            unset($this->methodCallStack[$key]);
        }
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
