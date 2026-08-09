<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Canonical CRM writer architecture ratchet.
 *
 * This test is intentionally source-oriented: it protects the write boundary
 * without booting a database or relying on comments/string snippets. The
 * parser records actual PHP call nodes, then the test applies an explicit
 * path-level policy. It must fail closed when a new canonical mutation appears
 * outside the writer or a contracted exception.
 */
it('keeps canonical CRM relationship and ownership writes behind the writer', function (): void {
    $productionFiles = dovraCrmProductionPhpFiles();
    $violations = [];

    foreach ($productionFiles as $path) {
        $relative = dovraCrmRelativePath($path);

        if (dovraCrmWriteAllowlist()[$relative] ?? false) {
            continue;
        }

        foreach (dovraCrmAstOperations($path) as $operation) {
            if (! $operation['canonical']) {
                continue;
            }

            $violations[] = sprintf(
                '%s:%d %s (%s)',
                $relative,
                $operation['line'],
                $operation['operation'],
                $operation['context'],
            );
        }
    }

    expect($violations)->toBeEmpty(
        "BLOCKED_WRITESET_DRIFT: canonical CRM mutation bypasses CanonicalCrmEntityWriter.\n".
        implode("\n", $violations),
    );
});

it('requires every authenticated canonical API and Filament mutation boundary to delegate', function (): void {
    $required = [
        'app/Http/Controllers/Api/V1/Crm/CompanyController.php',
        'app/Http/Controllers/Api/V1/Crm/ContactController.php',
        'app/Http/Controllers/Api/V1/Crm/ActivityController.php',
        'app/Filament/App/Resources/Crm/CompanyResource/Pages/CreateCompany.php',
        'app/Filament/App/Resources/Crm/CompanyResource/Pages/EditCompany.php',
        'app/Filament/App/Resources/Crm/ContactResource/Pages/CreateContact.php',
        'app/Filament/App/Resources/Crm/ContactResource/Pages/EditContact.php',
        'app/Filament/App/Resources/Crm/ActivityResource/Pages/CreateActivity.php',
        'app/Filament/App/Resources/Crm/ActivityResource/Pages/EditActivity.php',
        'app/Filament/App/Resources/Crm/TagResource/Pages/CreateTag.php',
        'app/Filament/App/Resources/Crm/TagResource/Pages/EditTag.php',
        'app/Filament/App/Resources/Crm/ContactResource/RelationManagers/ActivitiesRelationManager.php',
        'app/Application/Crm/BulkOperationsService.php',
    ];

    $violations = [];
    foreach ($required as $relative) {
        $path = base_path($relative);
        if (! is_file($path)) {
            $violations[] = "{$relative} (missing)";

            continue;
        }

        $source = (string) file_get_contents($path);
        if (! str_contains($source, 'CanonicalCrmEntityWriter')) {
            $violations[] = "{$relative} (does not reference CanonicalCrmEntityWriter)";
        }

        foreach (dovraCrmAstOperations($path) as $operation) {
            if ($operation['canonical']) {
                $violations[] = sprintf(
                    '%s:%d direct %s (%s)',
                    $relative,
                    $operation['line'],
                    $operation['operation'],
                    $operation['context'],
                );
            }
        }
    }

    expect($violations)->toBeEmpty(
        "BLOCKED_WRITESET_DRIFT: API/Filament boundary is not delegated.\n".
        implode("\n", $violations),
    );
});

/** @return list<string> */
function dovraCrmProductionPhpFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function dovraCrmRelativePath(string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace('\\', '/', base_path()).'/', '/')) === ''
        ? $path
        : ltrim(str_replace('\\', '/', str_replace(base_path(), '', $path)), '/');
}

/**
 * Exact exceptions only. Migrations are outside app_path and therefore not
 * scanned. Imports remain exceptions solely because the import boundary test
 * proves their constructor mismatch is fail-closed; this is not a readiness
 * claim.
 *
 * @return array<string, bool>
 */
function dovraCrmWriteAllowlist(): array
{
    return array_fill_keys([
        'app/Application/Crm/CanonicalCrmEntityWriter.php',
        'app/Application/Crm/TenantIntegrity/CrmTenantIntegrity.php',
        'app/Application/Crm/TenantIntegrity/CrmTenantIntegrityReport.php',
        'app/Application/Crm/LegacyUuid/CrmLegacyUuidReconciliation.php',
        'app/Application/Crm/LegacyUuid/CrmLegacyUuidReconciliationReport.php',
        'app/Console/Commands/ReconcileCrmTenantIntegrityCommand.php',
        'app/Console/Commands/ReconcileLegacyUuidCrmCommand.php',
        'app/Filament/App/Pages/WebToLeadPage.php',
        'app/Application/Imports/Imports/ContactsImport.php',
        'app/Application/Imports/Imports/CompaniesImport.php',
    ], true);
}

/**
 * @return list<array{canonical: bool, operation: string, line: int, context: string}>
 */
function dovraCrmAstOperations(string $path): array
{
    $source = (string) file_get_contents($path);
    $parser = (new ParserFactory)->createForHostVersion();

    try {
        $ast = $parser->parse($source) ?? [];
    } catch (Throwable $exception) {
        return [[
            'canonical' => true,
            'operation' => 'parse-error',
            'line' => 1,
            'context' => $exception->getMessage(),
        ]];
    }

    $visitor = new class($source) extends NodeVisitorAbstract
    {
        /** @var list<string> */
        private array $canonicalAliases = [];

        /** @var list<array{canonical: bool, operation: string, line: int, context: string}> */
        public array $operations = [];

        private string $method = '';

        private bool $canonicalParameter = false;

        public function __construct(private readonly string $source) {}

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
                foreach ($node->uses as $use) {
                    $name = $use->name->toString();
                    $alias = $use->alias?->toString() ?? $use->name->getLast();
                    if (in_array($name, [
                        'App\\Models\\CrmCompany',
                        'App\\Models\\Contact',
                        'App\\Models\\CrmActivity',
                        'App\\Models\\Tag',
                    ], true)) {
                        $this->canonicalAliases[] = $alias;
                    }
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                $this->method = $node->name->toString();
                $this->canonicalParameter = false;
                foreach ($node->params as $parameter) {
                    $type = $parameter->type;
                    if ($type instanceof Node\Name && in_array($type->toString(), [
                        ...$this->canonicalAliases,
                        'CrmCompany',
                        'Contact',
                        'CrmActivity',
                        'Tag',
                    ], true)) {
                        $this->canonicalParameter = true;
                        break;
                    }
                }
            }

            if ($node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall) {
                $method = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
                if ($method === null || ! in_array($method, [
                    'create', 'update', 'save', 'fill', 'forceFill', 'upsert',
                    'associate', 'sync', 'syncWithoutDetaching', 'attach', 'detach',
                ], true)) {
                    return null;
                }

                $canonical = false;
                $context = $this->method === '' ? 'global' : $this->method;
                if ($node instanceof Node\Expr\StaticCall) {
                    $class = $node->class instanceof Node\Name ? $node->class->toString() : '';
                    $canonical = in_array($class, $this->canonicalAliases, true)
                        || in_array($class, ['CrmCompany', 'Contact', 'CrmActivity', 'Tag'], true)
                        || in_array($class, [
                            'App\\Models\\CrmCompany',
                            'App\\Models\\Contact',
                            'App\\Models\\CrmActivity',
                            'App\\Models\\Tag',
                        ], true);
                } else {
                    // A typed canonical model parameter establishes the
                    // receiver's identity. This avoids classifying retained
                    // lifecycle/tag bulk operations as relationship writes
                    // while still catching a direct Contact save.
                    $canonical = $this->canonicalParameter;
                }

                if ($canonical) {
                    $this->operations[] = [
                        'canonical' => true,
                        'operation' => $method,
                        'line' => $node->getStartLine(),
                        'context' => $context,
                    ];
                }
            }

            return null;
        }

        public function leaveNode(Node $node): null
        {
            if ($node instanceof Node\Stmt\ClassMethod) {
                $this->canonicalParameter = false;
            }

            return null;
        }
    };

    $traverser = new NodeTraverser;
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    return $visitor->operations;
}
