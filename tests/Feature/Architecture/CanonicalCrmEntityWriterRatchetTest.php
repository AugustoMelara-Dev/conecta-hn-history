<?php

declare(strict_types=1);

use App\Application\Imports\Imports\CompaniesImport;
use App\Application\Imports\Imports\ContactsImport;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Canonical CRM writer architecture ratchet.
 *
 * The ratchet is AST based. It resolves canonical model/table receivers across
 * query chains and local variables, then checks persistence calls together
 * with sensitive assignments/array keys. Comments and imports alone can never
 * satisfy a delegation sentinel.
 */
it('keeps every canonical CRM mutation behind the writer or a proved exception', function (): void {
    $violations = [];
    $allowlisted = dovraCrmStructuralWritePaths();

    foreach (dovraCrmProductionPhpFiles() as $path) {
        $relative = dovraCrmRelativePath($path);
        $operations = dovraCrmAstOperations($path);

        if (in_array($relative, $allowlisted, true)) {
            continue;
        }

        $sensitiveOperations = array_values(array_filter(
            $operations,
            static fn (array $operation): bool => $operation['canonical'] && $operation['sensitive'],
        ));

        if ($sensitiveOperations === []) {
            continue;
        }

        if ($relative === 'app/Filament/App/Pages/WebToLeadPage.php') {
            $webToLead = dovraCrmWebToLeadSentinel($path);
            if ($webToLead['ok']) {
                continue;
            }
            $violations[] = $relative.' WEB_TO_LEAD_EXCEPTION_DRIFT: '.$webToLead['reason'];

            continue;
        }

        if (in_array($relative, dovraCrmDormantImportPaths(), true)) {
            $dormant = dovraCrmDormantImportSentinel($relative, $operations);
            if ($dormant['ok']) {
                continue;
            }
            $violations[] = $relative.' DORMANT_IMPORT_DRIFT: '.$dormant['reason'];

            continue;
        }

        foreach ($sensitiveOperations as $operation) {
            $violations[] = sprintf(
                '%s:%d %s receiver=%s keys=%s (%s)',
                $relative,
                $operation['line'],
                $operation['operation'],
                $operation['receiver'],
                implode(',', $operation['keys']),
                $operation['context'],
            );
        }
    }

    expect($violations)->toBeEmpty(
        "BLOCKED_WRITESET_DRIFT: canonical CRM mutation bypasses CanonicalCrmEntityWriter.\n".
        implode("\n", $violations),
    );
});

it('uses concrete AST sentinels for every authenticated CRM mutation boundary', function (): void {
    $violations = [];

    foreach (dovraCrmDelegationRequirements() as $relative => $requirements) {
        $path = base_path($relative);
        if (! is_file($path)) {
            $violations[] = $relative.' (missing)';

            continue;
        }

        foreach ($requirements as $method => $writerMethod) {
            if (! dovraCrmDelegationSentinel($path, $method, $writerMethod)) {
                $violations[] = "{$relative}::{$method} must call {$writerMethod} on CanonicalCrmEntityWriter";
            }
        }
    }

    $importRuntime = dovraCrmImportRuntimeSentinel();
    if (! $importRuntime['ok']) {
        $violations[] = 'import runtime sentinel: '.$importRuntime['reason'];
    }

    expect($violations)->toBeEmpty(
        "BLOCKED_WRITESET_DRIFT: a required CRM boundary is not proven by AST.\n".
        implode("\n", $violations),
    );
});

it('proves the AST ratchet detects bypass shapes while ignoring comments and imports', function (): void {
    $fixtures = [
        'static model create' => <<<'PHP'
            <?php
            use App\Models\Contact;
            Contact::create(['owner_id' => $ownerId]);
            PHP,
        'query chain update' => <<<'PHP'
            <?php
            use App\Models\Contact;
            $query = Contact::query()->where('tenant_id', $tenantId);
            $query->update(['company_id' => $companyId]);
            PHP,
        'local find and save' => <<<'PHP'
            <?php
            use App\Models\Contact;
            $contact = Contact::find($id);
            $contact->company_id = $companyId;
            $contact->save();
            PHP,
        'canonical raw table update' => <<<'PHP'
            <?php
            use Illuminate\Support\Facades\DB;
            DB::table('crm_contacts')->where('id', $id)->update(['owner_id' => $ownerId]);
            PHP,
        'relationship sync' => <<<'PHP'
            <?php
            use App\Models\Contact;
            $contact = Contact::find($id);
            $contact->tags()->sync($tagIds);
            PHP,
        'canonical tag upsert' => <<<'PHP'
            <?php
            use App\Models\Tag;
            Tag::upsert([['tenant_id' => $tenantId, 'slug' => $slug]], ['tenant_id', 'slug']);
            PHP,
    ];

    foreach ($fixtures as $name => $fixture) {
        expect(dovraCrmAstOperations($fixture, true))->not->toBeEmpty("fixture {$name} was not detected");
        expect(array_filter(
            dovraCrmAstOperations($fixture, true),
            static fn (array $operation): bool => $operation['canonical'] && $operation['sensitive'],
        ))->not->toBeEmpty("fixture {$name} was not sensitive");
    }

    $commentAndImportOnly = <<<'PHP'
        <?php
        // Contact::create(['owner_id' => $ownerId]);
        use App\Models\Contact;
        $message = 'DB::table("crm_contacts")->update(["owner_id" => $ownerId])';
        PHP;

    expect(array_filter(
        dovraCrmAstOperations($commentAndImportOnly, true),
        static fn (array $operation): bool => $operation['canonical'] && $operation['sensitive'],
    ))->toBeEmpty();

    $wrongDelegationReceiver = <<<'PHP'
        <?php
        use App\Application\Crm\CanonicalCrmEntityWriter;

        final class WrongDelegationReceiver
        {
            public function mutate(): void
            {
                CanonicalCrmEntityWriter::class;
                $other = new stdClass();
                $other->createActivity([], []);
            }
        }
        PHP;

    expect(dovraCrmDelegationSentinel($wrongDelegationReceiver, 'mutate', 'createActivity', true))
        ->toBeFalse('an unrelated object must not satisfy the writer delegation sentinel');

    $boundDelegationReceiver = <<<'PHP'
        <?php
        use App\Application\Crm\CanonicalCrmEntityWriter;

        final class BoundDelegationReceiver
        {
            public function __construct(private CanonicalCrmEntityWriter $writer) {}

            public function mutate(): void
            {
                $alias = $this->writer;
                $alias->createActivity([], []);
            }
        }
        PHP;

    expect(dovraCrmDelegationSentinel($boundDelegationReceiver, 'mutate', 'createActivity', true))
        ->toBeTrue('a constructor-bound writer property alias must satisfy the sentinel');

    $importBypass = <<<'PHP'
        <?php
        use App\Application\Imports\ImportService;
        use App\Application\Imports\Imports\ContactsImport as ContactCsv;
        use Maatwebsite\Excel\Facades\Excel as Spreadsheet;

        $service = app(ImportService::class);
        $candidate = $service->makeImporter('contacts', 'tenant');
        $alias = $candidate;
        $alias->model([]);
        Spreadsheet::import($alias, 'blocked.csv');
        $direct = new ContactCsv('tenant');
        $directAlias = $direct;
        $directAlias->model([]);
        PHP;

    $importBypassViolations = dovraCrmImportSourceViolations(
        $importBypass,
        'app/Tests/Fixtures/ImportBoundaryFixture.php',
    );
    expect(implode('; ', $importBypassViolations))->toContain('importer->model() bypass')
        ->and(implode('; ', $importBypassViolations))->toContain('direct Excel::import caller');

    $webToLeadStateBypass = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                Contact::create(['tenant_id' => $tenantId, 'company_id' => $this->data['company_id']]);
                CrmActivity::create(['tenant_id' => $tenantId, 'contact_id' => $this->data['contact_id']]);
            });
        }
        PHP;

    $webToLeadRequestBypass = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                $payload = request()->validated();
                Contact::create(['tenant_id' => $tenantId, 'owner_id' => $payload['owner_id']]);
                CrmActivity::create(['tenant_id' => $tenantId, 'body' => $payload['message'] ?? null]);
            });
        }
        PHP;

    $webToLeadWrongTenant = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                $wrongTenant = 'foreign-tenant';
                Contact::create(['tenant_id' => $wrongTenant]);
                CrmActivity::create(['tenant_id' => $wrongTenant]);
            });
        }
        PHP;

    expect(dovraCrmWebToLeadSentinel($webToLeadStateBypass, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadRequestBypass, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadWrongTenant, true)['ok'])->toBeFalse();
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
    return ltrim(str_replace('\\', '/', str_replace(base_path(), '', $path)), '/');
}

/** @return list<string> */
function dovraCrmStructuralWritePaths(): array
{
    return [
        'app/Application/Crm/CanonicalCrmEntityWriter.php',
        'app/Application/Crm/TenantIntegrity/CrmTenantIntegrity.php',
        'app/Application/Crm/TenantIntegrity/CrmTenantIntegrityReport.php',
        'app/Application/Crm/LegacyUuid/CrmLegacyUuidReconciliation.php',
        'app/Application/Crm/LegacyUuid/CrmLegacyUuidReconciliationReport.php',
        'app/Console/Commands/ReconcileCrmTenantIntegrityCommand.php',
        'app/Console/Commands/ReconcileLegacyUuidCrmCommand.php',
    ];
}

/** @return list<string> */
function dovraCrmDormantImportPaths(): array
{
    return [
        'app/Application/Imports/Imports/ContactsImport.php',
        'app/Application/Imports/Imports/CompaniesImport.php',
    ];
}

/** @return array<string, array<string, string>> */
function dovraCrmDelegationRequirements(): array
{
    return [
        'app/Http/Controllers/Api/V1/Crm/CompanyController.php' => [
            'store' => 'createCompany',
            'update' => 'updateCompany',
        ],
        'app/Http/Controllers/Api/V1/Crm/ContactController.php' => [
            'store' => 'createContact',
            'update' => 'updateContact',
        ],
        'app/Http/Controllers/Api/V1/Crm/ActivityController.php' => [
            'store' => 'createActivity',
            'update' => 'updateActivity',
        ],
        'app/Filament/App/Resources/Crm/CompanyResource/Pages/CreateCompany.php' => [
            'handleRecordCreation' => 'createCompany',
        ],
        'app/Filament/App/Resources/Crm/CompanyResource/Pages/EditCompany.php' => [
            'handleRecordUpdate' => 'updateCompany',
        ],
        'app/Filament/App/Resources/Crm/ContactResource/Pages/CreateContact.php' => [
            'handleRecordCreation' => 'createContact',
        ],
        'app/Filament/App/Resources/Crm/ContactResource/Pages/EditContact.php' => [
            'handleRecordUpdate' => 'updateContact',
        ],
        'app/Filament/App/Resources/Crm/ActivityResource/Pages/CreateActivity.php' => [
            'handleRecordCreation' => 'createActivity',
        ],
        'app/Filament/App/Resources/Crm/ActivityResource/Pages/EditActivity.php' => [
            'handleRecordUpdate' => 'updateActivity',
        ],
        'app/Filament/App/Resources/Crm/TagResource/Pages/CreateTag.php' => [
            'handleRecordCreation' => 'createTag',
        ],
        'app/Filament/App/Resources/Crm/TagResource/Pages/EditTag.php' => [
            'handleRecordUpdate' => 'updateTag',
        ],
        'app/Filament/App/Resources/Crm/ContactResource/RelationManagers/ActivitiesRelationManager.php' => [
            'table' => 'createActivity|updateActivity',
        ],
        'app/Application/Crm/BulkOperationsService.php' => [
            'bulkAssignOwner' => 'bulkAssignContactOwner',
        ],
        'app/Application/Crm/ContactEnrichmentService.php' => [
            'applyToContact' => 'updateContact',
        ],
    ];
}

/**
 * @return array{ok: bool, reason: string}
 */
function dovraCrmWebToLeadSentinel(string $sourceOrPath, bool $sourceInput = false): array
{
    $source = $sourceInput ? $sourceOrPath : (string) file_get_contents($sourceOrPath);
    $ast = dovraCrmParse($source);
    if ($ast === []) {
        return ['ok' => false, 'reason' => 'parse failed'];
    }

    $visitor = new class extends NodeVisitorAbstract
    {
        public int $transactions = 0;

        public int $canonicalWritesInTransaction = 0;

        public int $canonicalWritesOutsideTransaction = 0;

        public int $unboundTenantWrites = 0;

        public bool $sensitiveStateInput = false;

        public bool $sensitiveFormInput = false;

        public bool $tenantDerived = false;

        /** @var array<string, string> */
        private array $aliases = [];

        /** @var array<string, bool> */
        private array $derivedTenantVariables = [];

        private int $transactionDepth = 0;

        private const SENSITIVE = ['tenant_id', 'company_id', 'contact_id', 'deal_id', 'owner_id', 'tag_id'];

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
                foreach ($node->uses as $use) {
                    $this->aliases[$use->alias?->toString() ?? $use->name->getLast()] = ltrim($use->name->toString(), '\\');
                }
            }

            if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier) {
                $method = $node->name->toString();
                $class = $node->class instanceof Node\Name ? ltrim($node->class->toString(), '\\') : '';
                if ($method === 'transaction' && ($class === 'DB' || str_ends_with($class, '\\DB'))) {
                    $this->transactions++;
                    $this->transactionDepth++;
                }

                if ($this->transactionDepth > 0 && in_array($method, [
                    'create', 'firstOrCreate', 'updateOrCreate', 'update', 'save', 'forceFill', 'upsert',
                ], true)) {
                    $canonical = $node->class instanceof Node\Name
                        && dovraCrmCanonicalClassTarget(ltrim($node->class->toString(), '\\'), $this->aliases) !== null;
                    if ($canonical) {
                        $this->canonicalWritesInTransaction++;
                        if (! $this->hasDerivedTenantBinding($node)) {
                            $this->unboundTenantWrites++;
                        }
                    }
                } elseif ($this->transactionDepth === 0 && in_array($method, [
                    'create', 'firstOrCreate', 'updateOrCreate', 'update', 'save', 'forceFill', 'upsert',
                ], true)
                    && $node->class instanceof Node\Name
                    && dovraCrmCanonicalClassTarget(ltrim($node->class->toString(), '\\'), $this->aliases) !== null) {
                    $this->canonicalWritesOutsideTransaction++;
                }

                if ($class === 'Filament\\Facades\\Filament' && $method === 'getTenant') {
                    $this->tenantDerived = true;
                }
            }

            if ($node instanceof Node\Expr\ArrayDimFetch) {
                $key = $node->dim instanceof Node\Scalar\String_ ? $node->dim->value : null;
                if (in_array($key, self::SENSITIVE, true)) {
                    $this->sensitiveStateInput = true;
                }
            }

            if ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Node\Identifier
                && in_array($node->name->toString(), self::SENSITIVE, true)) {
                $this->sensitiveStateInput = true;
            }

            if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall)
                && $node->name instanceof Node\Identifier
                && in_array($node->name->toString(), ['input', 'get', 'validated', 'all'], true)) {
                $this->sensitiveStateInput = true;
            }

            if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier
                && in_array($node->name->toString(), ['input', 'get', 'validated', 'all'], true)) {
                $this->sensitiveStateInput = true;
            }

            if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
                && strtolower($node->name->toString()) === 'request' && $node->args !== []) {
                $key = $node->args[0]->value instanceof Node\Scalar\String_
                    ? $node->args[0]->value->value
                    : null;
                if ($key === null || in_array($key, self::SENSITIVE, true)) {
                    $this->sensitiveStateInput = true;
                }
            }

            if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier
                && $node->name->toString() === 'make' && $node->args !== []) {
                $class = $node->class instanceof Node\Name ? ltrim($node->class->toString(), '\\') : '';
                $field = $node->args[0]->value instanceof Node\Scalar\String_
                    ? $node->args[0]->value->value
                    : null;
                if (is_string($field) && in_array($field, self::SENSITIVE, true)) {
                    $this->sensitiveFormInput = true;
                }
                if (str_ends_with($class, 'TextInput') || str_ends_with($class, 'Select')
                    || str_ends_with($class, 'Textarea') || str_ends_with($class, 'Hidden')
                    || str_ends_with($class, 'Field')) {
                    if (! is_string($field) || in_array($field, self::SENSITIVE, true)) {
                        $this->sensitiveFormInput = true;
                    }
                }
            }

            return null;
        }

        public function leaveNode(Node $node): null
        {
            if ($node instanceof Node\Expr\Assign
                && $node->var instanceof Node\Expr\Variable
                && is_string($node->var->name)) {
                $derived = dovraCrmContainsFilamentTenant($node->expr)
                    || ($node->expr instanceof Node\Expr\Variable
                        && is_string($node->expr->name)
                        && ($this->derivedTenantVariables[$node->expr->name] ?? false));
                if ($derived) {
                    $this->derivedTenantVariables[$node->var->name] = $this->transactionDepth > 0;
                    $this->tenantDerived = $this->transactionDepth > 0;
                }
            }

            if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier
                && $node->name->toString() === 'transaction') {
                $this->transactionDepth = max(0, $this->transactionDepth - 1);
            }

            return null;
        }

        private function hasDerivedTenantBinding(Node $write): bool
        {
            $bindings = [];
            foreach ($write->getSubNodeNames() as $name) {
                $child = $write->{$name};
                if ($child instanceof Node) {
                    $this->collectTenantBindings($child, $bindings);
                } elseif (is_array($child)) {
                    foreach ($child as $item) {
                        if ($item instanceof Node) {
                            $this->collectTenantBindings($item, $bindings);
                        }
                    }
                }
            }

            return $bindings !== [] && ! in_array(false, $bindings, true);
        }

        /** @param list<bool> $bindings */
        private function collectTenantBindings(Node $node, array &$bindings): void
        {
            if ($node instanceof Node\Expr\Array_) {
                foreach ($node->items as $item) {
                    if ($item === null) {
                        continue;
                    }
                    if ($item->key instanceof Node\Scalar\String_
                        && $item->key->value === 'tenant_id') {
                        $bindings[] = $this->isDerivedTenantExpression($item->value);
                    } else {
                        $this->collectTenantBindings($item->value, $bindings);
                    }
                }

                return;
            }

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};
                if ($child instanceof Node) {
                    $this->collectTenantBindings($child, $bindings);
                } elseif (is_array($child)) {
                    foreach ($child as $item) {
                        if ($item instanceof Node) {
                            $this->collectTenantBindings($item, $bindings);
                        }
                    }
                }
            }
        }

        private function isDerivedTenantExpression(Node $node): bool
        {
            return ($node instanceof Node\Expr\Variable
                && is_string($node->name)
                && ($this->derivedTenantVariables[$node->name] ?? false))
                || dovraCrmContainsFilamentTenant($node);
        }
    };

    $traverser = new NodeTraverser;
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    if ($visitor->transactions !== 1 || $visitor->canonicalWritesInTransaction < 2
        || $visitor->canonicalWritesOutsideTransaction > 0
        || $visitor->unboundTenantWrites > 0) {
        return ['ok' => false, 'reason' => 'expected every canonical intake write bound to derived tenant inside one transaction'];
    }
    if (! $visitor->tenantDerived) {
        return ['ok' => false, 'reason' => 'tenant is not derived from Filament context'];
    }
    if ($visitor->sensitiveStateInput || $visitor->sensitiveFormInput) {
        return ['ok' => false, 'reason' => 'public state/form accepts a sensitive tenant or relationship ID'];
    }

    return ['ok' => true, 'reason' => ''];
}

function dovraCrmContainsFilamentTenant(Node $node): bool
{
    if ($node instanceof Node\Expr\StaticCall
        && $node->name instanceof Node\Identifier
        && $node->name->toString() === 'getTenant'
        && $node->class instanceof Node\Name
        && str_ends_with(ltrim($node->class->toString(), '\\'), '\\Filament')) {
        return true;
    }

    foreach ($node->getSubNodeNames() as $name) {
        $child = $node->{$name};
        if ($child instanceof Node && dovraCrmContainsFilamentTenant($child)) {
            return true;
        }
        if (is_array($child)) {
            foreach ($child as $item) {
                if ($item instanceof Node && dovraCrmContainsFilamentTenant($item)) {
                    return true;
                }
            }
        }
    }

    return false;
}

function dovraCrmDelegationSentinel(string $sourceOrPath, string $methodName, string $writerMethod, bool $sourceInput = false): bool
{
    $source = $sourceInput ? $sourceOrPath : (string) file_get_contents($sourceOrPath);
    $ast = dovraCrmParse($source);
    if ($ast === []) {
        return false;
    }

    $visitor = new class($methodName, $writerMethod) extends NodeVisitorAbstract
    {
        public bool $found = false;

        private string $currentMethod = '';

        /** @var array<string, string> */
        private array $aliases = [];

        /** @var array<string, bool> */
        private array $writerLocals = [];

        /** @var array<string, bool> */
        private array $writerProperties = [];

        /** @var list<array<string, bool>> */
        private array $classPropertyScopes = [];

        /** @var list<string> */
        private array $expectedWriters;

        /** @var list<string> */
        private array $matchedWriters = [];

        /** @var list<string> */
        private array $unexpectedWriters = [];

        private const WRITER_METHODS = [
            'createCompany', 'updateCompany', 'createContact', 'updateContact',
            'createActivity', 'updateActivity', 'createTag', 'updateTag',
            'bulkAssignContactOwner',
        ];

        public function __construct(private readonly string $expectedMethod, string $expectedWriter)
        {
            $this->expectedWriters = array_values(array_filter(explode('|', $expectedWriter)));
        }

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
                foreach ($node->uses as $use) {
                    $this->aliases[$use->alias?->toString() ?? $use->name->getLast()] = ltrim($use->name->toString(), '\\');
                }
            }

            if ($node instanceof Node\Stmt\Class_) {
                $this->classPropertyScopes[] = $this->writerProperties;
                $this->writerProperties = [];
            }

            if ($node instanceof Node\Stmt\Property && dovraCrmWriterTypeNode($node->type, $this->aliases)) {
                foreach ($node->props as $property) {
                    $this->writerProperties[$property->name->toString()] = true;
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                $this->currentMethod = $node->name->toString();
                $this->writerLocals = [];
                foreach ($node->params as $parameter) {
                    if (! $parameter->var instanceof Node\Expr\Variable || ! is_string($parameter->var->name)) {
                        continue;
                    }
                    if (dovraCrmWriterTypeNode($parameter->type, $this->aliases)) {
                        $this->writerLocals[$parameter->var->name] = true;
                        if ($node->name->toString() === '__construct' && $parameter->flags !== 0) {
                            $this->writerProperties[$parameter->var->name] = true;
                        }
                    }
                }
            }

            if ($this->currentMethod === $this->expectedMethod
                && $node instanceof Node\Expr\MethodCall
                && $node->name instanceof Node\Identifier) {
                $method = $node->name->toString();
                if (! in_array($method, self::WRITER_METHODS, true)
                    || ! $this->isWriterReceiver($node->var)) {
                    return null;
                }
                if (in_array($method, $this->expectedWriters, true)) {
                    $this->matchedWriters[] = $method;
                } else {
                    $this->unexpectedWriters[] = $method;
                }
            }

            return null;
        }

        public function leaveNode(Node $node): null
        {
            if ($node instanceof Node\Expr\Assign && $this->currentMethod === $this->expectedMethod) {
                $writer = $this->isWriterReceiver($node->expr);
                if ($node->var instanceof Node\Expr\Variable && is_string($node->var->name) && $writer) {
                    $this->writerLocals[$node->var->name] = true;
                }
                if ($node->var instanceof Node\Expr\PropertyFetch
                    && $node->var->var instanceof Node\Expr\Variable
                    && $node->var->var->name === 'this'
                    && $node->var->name instanceof Node\Identifier
                    && $writer) {
                    $this->writerProperties[$node->var->name->toString()] = true;
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                $this->currentMethod = '';
                $this->writerLocals = [];
            }

            if ($node instanceof Node\Stmt\Class_) {
                $this->writerProperties = array_pop($this->classPropertyScopes) ?? [];
            }

            return null;
        }

        public function satisfiesContract(): bool
        {
            return count(array_unique($this->matchedWriters)) === count($this->expectedWriters)
                && $this->unexpectedWriters === [];
        }

        private function isWriterReceiver(Node $node): bool
        {
            if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
                return isset($this->writerLocals[$node->name]);
            }

            if ($node instanceof Node\Expr\PropertyFetch
                && $node->var instanceof Node\Expr\Variable
                && $node->var->name === 'this'
                && $node->name instanceof Node\Identifier) {
                return isset($this->writerProperties[$node->name->toString()]);
            }

            if ($node instanceof Node\Expr\FuncCall
                && $node->name instanceof Node\Name
                && in_array(strtolower($node->name->toString()), ['app', 'resolve'], true)
                && isset($node->args[0])) {
                return dovraCrmWriterClassConst($node->args[0]->value, $this->aliases);
            }

            return false;
        }
    };

    $traverser = new NodeTraverser;
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    $visitor->found = $visitor->satisfiesContract();

    return $visitor->found;
}

function dovraCrmWriterClassConst(Node $node, array $aliases = []): bool
{
    return $node instanceof Node\Expr\ClassConstFetch
        && $node->name instanceof Node\Identifier
        && $node->name->toString() === 'class'
        && $node->class instanceof Node\Name
        && dovraCrmWriterClassName($node->class->toString(), $aliases);
}

function dovraCrmWriterClassName(string $name, array $aliases = []): bool
{
    $name = ltrim($name, '\\');
    $name = ltrim($aliases[$name] ?? $name, '\\');

    return in_array($name, [
        'CanonicalCrmEntityWriter',
        'App\\Application\\Crm\\CanonicalCrmEntityWriter',
    ], true);
}

function dovraCrmWriterTypeNode(?Node $type, array $aliases = []): bool
{
    if ($type instanceof Node\NullableType) {
        return dovraCrmWriterTypeNode($type->type, $aliases);
    }
    if ($type instanceof Node\Name) {
        return dovraCrmWriterClassName($type->toString(), $aliases);
    }

    return false;
}

/** @return array{ok: bool, reason: string} */
function dovraCrmDormantImportSentinel(string $relative, array $operations): array
{
    $contracts = [
        'app/Application/Imports/Imports/ContactsImport.php' => [
            'class' => ContactsImport::class,
            'receiver' => 'crm_contacts',
            'operation' => 'save',
            'totalParameters' => 2,
        ],
        'app/Application/Imports/Imports/CompaniesImport.php' => [
            'class' => CompaniesImport::class,
            'receiver' => 'crm_companies',
            'operation' => 'create',
            'totalParameters' => 1,
        ],
    ];
    if (! isset($contracts[$relative])) {
        return ['ok' => false, 'reason' => 'unexpected import exception path'];
    }

    $contract = $contracts[$relative];
    $reflection = new ReflectionClass($contract['class']);
    $constructor = $reflection->getConstructor();
    if ($constructor === null
        || $constructor->getNumberOfRequiredParameters() !== 1
        || $constructor->getNumberOfParameters() !== $contract['totalParameters']) {
        return ['ok' => false, 'reason' => 'constructor signature no longer requires only explicit tenant context'];
    }

    $expectedWrites = array_values(array_filter(
        $operations,
        static fn (array $operation): bool => $operation['receiver'] === $contract['receiver']
            && $operation['operation'] === $contract['operation'],
    ));
    if (count($operations) !== 1 || count($expectedWrites) !== 1) {
        return ['ok' => false, 'reason' => 'import write shape changed; dormant exception requires one exact direct writer'];
    }

    $runtime = dovraCrmImportRuntimeSentinel();
    if (! $runtime['ok']) {
        return $runtime;
    }

    return ['ok' => true, 'reason' => ''];
}

/** @return array{ok: bool, reason: string} */
function dovraCrmImportRuntimeSentinel(): array
{
    $excelPath = base_path('app/Infrastructure/Imports/ExcelImporter.php');
    $importCsvPath = base_path('app/Filament/Actions/ImportCsvAction.php');
    $wizardPath = base_path('app/Filament/App/Pages/ImportExport/ImportWizard.php');
    if (! is_file($excelPath) || ! is_file($importCsvPath) || ! is_file($wizardPath)) {
        return ['ok' => false, 'reason' => 'import boundary caller missing'];
    }

    $excelAst = dovraCrmParse((string) file_get_contents($excelPath));
    $excelVisitor = new class extends NodeVisitorAbstract
    {
        public bool $zeroArgClassStringNew = false;

        public bool $classStringExcelImport = false;

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Expr\New_
                && $node->class instanceof Node\Expr\Variable
                && $node->class->name === 'importClass'
                && $node->args === []) {
                $this->zeroArgClassStringNew = true;
            }

            if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier
                && $node->name->toString() === 'import' && $node->args !== []) {
                $class = $node->class instanceof Node\Name ? ltrim($node->class->toString(), '\\') : '';
                if ($class !== 'Excel' && ! str_ends_with($class, '\\Excel')) {
                    return null;
                }
                $first = $node->args[0]->value;
                $this->classStringExcelImport = $first instanceof Node\Expr\Variable
                    && $first->name === 'import';
            }

            return null;
        }
    };
    $traverser = new NodeTraverser;
    $traverser->addVisitor($excelVisitor);
    $traverser->traverse($excelAst);
    if (! $excelVisitor->zeroArgClassStringNew || ! $excelVisitor->classStringExcelImport) {
        return ['ok' => false, 'reason' => 'ExcelImporter no longer enforces class-string zero-arg construction'];
    }

    $importCsvAst = dovraCrmParse((string) file_get_contents($importCsvPath));
    $importCsvVisitor = new class extends NodeVisitorAbstract
    {
        public bool $unusedTenantConstruct = false;

        public bool $classStringPassedToExcel = false;

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Expr\New_
                && $node->class instanceof Node\Expr\Variable
                && $node->class->name === 'importClass'
                && count($node->args) === 1) {
                $this->unusedTenantConstruct = true;
            }

            if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier
                && $node->name->toString() === 'import' && $node->args !== []) {
                $first = $node->args[0]->value;
                if ($first instanceof Node\Expr\Variable && $first->name === 'importClass') {
                    $this->classStringPassedToExcel = true;
                }
                if ($first instanceof Node\Expr\Variable && $first->name === 'importer') {
                    $this->classStringPassedToExcel = false;
                }
            }

            return null;
        }
    };
    $traverser = new NodeTraverser;
    $traverser->addVisitor($importCsvVisitor);
    $traverser->traverse($importCsvAst);
    if (! $importCsvVisitor->unusedTenantConstruct || ! $importCsvVisitor->classStringPassedToExcel) {
        return ['ok' => false, 'reason' => 'ImportCsvAction must document unused tenant instance and pass class-string to ExcelImporter'];
    }

    $wizardAst = dovraCrmParse((string) file_get_contents($wizardPath));
    if ($wizardAst === []) {
        return ['ok' => false, 'reason' => 'ImportWizard parse failed'];
    }

    $callerViolations = dovraCrmImportCallerViolations();
    if ($callerViolations !== []) {
        return ['ok' => false, 'reason' => implode('; ', $callerViolations)];
    }

    return ['ok' => true, 'reason' => ''];
}

/** @return list<string> */
function dovraCrmImportCallerViolations(): array
{
    $violations = [];
    foreach (dovraCrmProductionPhpFiles() as $path) {
        $relative = dovraCrmRelativePath($path);
        $violations = [...$violations, ...dovraCrmImportSourceViolations((string) file_get_contents($path), $relative)];
    }

    return $violations;
}

/** @return list<string> */
function dovraCrmImportSourceViolations(string $source, string $relative): array
{
    $ast = dovraCrmParse($source);
    if ($ast === []) {
        return [$relative.':parse-error'];
    }

    $visitor = new class($relative) extends NodeVisitorAbstract
    {
        /** @var list<string> */
        public array $violations = [];

        /** @var array<string, string> */
        private array $aliases = [];

        /** @var array<string, bool> */
        private array $serviceReceivers = [];

        /** @var array<string, bool> */
        private array $importReceivers = [];

        /** @var array<string, bool> */
        private array $templateReceivers = [];

        private string $method = '';

        private bool $templateHeadersSeen = false;

        public function __construct(private readonly string $relative) {}

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
                foreach ($node->uses as $use) {
                    $this->aliases[$use->alias?->toString() ?? $use->name->getLast()] = ltrim($use->name->toString(), '\\');
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                $this->method = $node->name->toString();
                $this->templateHeadersSeen = false;
                foreach ($node->params as $parameter) {
                    if ($parameter->var instanceof Node\Expr\Variable
                        && is_string($parameter->var->name)
                        && dovraCrmImportServiceTypeNode($parameter->type, $this->aliases)) {
                        $this->serviceReceivers[$this->symbol($parameter->var)] = true;
                    }
                }
            }

            if ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier
                && $node->name->toString() === 'import'
                && dovraCrmIsExcelClass($node->class, $this->aliases)
                && $this->relative !== 'app/Infrastructure/Imports/ExcelImporter.php') {
                $this->violations[] = $this->relative.':direct Excel::import caller';
            }

            if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
                $name = $node->name->toString();
                if ($name === 'makeImporter'
                    && ($this->relative !== 'app/Filament/App/Pages/ImportExport/ImportWizard.php'
                        || $this->method !== 'getExpectedHeaders')) {
                    $this->violations[] = $this->relative.':makeImporter outside template-only path';
                }

                if ($name === 'model' && $this->isImportReceiver($node->var)) {
                    $this->violations[] = $this->relative.':importer->model() bypass';
                }

                if ($name === 'templateHeaders') {
                    if ($this->isImportReceiver($node->var)) {
                        $this->templateHeadersSeen = true;
                        if (! $this->isAllowedTemplateReceiver($node->var)) {
                            $this->violations[] = $this->relative.':templateHeaders outside exact template receiver';
                        }
                    }
                }
            }

            return null;
        }

        public function leaveNode(Node $node): null
        {
            if ($node instanceof Node\Expr\Assign) {
                $symbol = $this->symbol($node->var);
                if ($symbol !== null) {
                    if ($this->isServiceReceiver($node->expr)) {
                        $this->serviceReceivers[$symbol] = true;
                    }
                    if ($this->isImportReceiver($node->expr)) {
                        $this->importReceivers[$symbol] = true;
                    }
                    if ($this->isMakeImporterExpression($node->expr)) {
                        $this->importReceivers[$symbol] = true;
                        $this->templateReceivers[$symbol] = true;
                    }
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                if ($this->method === 'getExpectedHeaders'
                    && $this->relative === 'app/Filament/App/Pages/ImportExport/ImportWizard.php'
                    && ! $this->templateHeadersSeen) {
                    $this->violations[] = $this->relative.':template path lacks templateHeaders';
                }
                $this->method = '';
                $this->templateHeadersSeen = false;
            }

            return null;
        }

        private function symbol(Node $node): ?string
        {
            if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
                return '$'.$node->name;
            }
            if ($node instanceof Node\Expr\PropertyFetch
                && $node->var instanceof Node\Expr\Variable
                && $node->var->name === 'this'
                && $node->name instanceof Node\Identifier) {
                return '$this->'.$node->name->toString();
            }

            return null;
        }

        private function isServiceReceiver(Node $node): bool
        {
            $symbol = $this->symbol($node);
            if ($symbol !== null) {
                return isset($this->serviceReceivers[$symbol]);
            }
            if ($node instanceof Node\Expr\FuncCall
                && $node->name instanceof Node\Name
                && in_array(strtolower($node->name->toString()), ['app', 'resolve'], true)
                && isset($node->args[0])) {
                return dovraCrmImportServiceClassConst($node->args[0]->value, $this->aliases);
            }

            return false;
        }

        private function isMakeImporterExpression(Node $node): bool
        {
            return $node instanceof Node\Expr\MethodCall
                && $node->name instanceof Node\Identifier
                && $node->name->toString() === 'makeImporter'
                && $this->isServiceReceiver($node->var);
        }

        private function isImportReceiver(Node $node): bool
        {
            $symbol = $this->symbol($node);
            if ($symbol !== null) {
                return isset($this->importReceivers[$symbol]);
            }
            if ($this->isMakeImporterExpression($node)) {
                return true;
            }
            if ($node instanceof Node\Expr\New_
                && $node->class instanceof Node\Name) {
                return dovraCrmLegacyImportClassName($node->class->toString(), $this->aliases) !== null;
            }

            return false;
        }

        private function isAllowedTemplateReceiver(Node $node): bool
        {
            if ($this->relative !== 'app/Filament/App/Pages/ImportExport/ImportWizard.php'
                || $this->method !== 'getExpectedHeaders') {
                return false;
            }
            if ($this->isMakeImporterExpression($node)) {
                return true;
            }

            $symbol = $this->symbol($node);

            return $symbol !== null && isset($this->templateReceivers[$symbol]);
        }
    };

    $traverser = new NodeTraverser;
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    return $visitor->violations;
}

function dovraCrmLegacyImportClassName(string $name, array $aliases = []): ?string
{
    $name = ltrim($aliases[ltrim($name, '\\')] ?? ltrim($name, '\\'), '\\');

    return match ($name) {
        'App\\Application\\Imports\\Imports\\ContactsImport' => 'contacts',
        'App\\Application\\Imports\\Imports\\CompaniesImport' => 'companies',
        default => null,
    };
}

function dovraCrmImportServiceClassConst(Node $node, array $aliases = []): bool
{
    return $node instanceof Node\Expr\ClassConstFetch
        && $node->name instanceof Node\Identifier
        && $node->name->toString() === 'class'
        && $node->class instanceof Node\Name
        && dovraCrmImportServiceClassName($node->class->toString(), $aliases);
}

function dovraCrmImportServiceClassName(string $name, array $aliases = []): bool
{
    $name = ltrim($aliases[ltrim($name, '\\')] ?? ltrim($name, '\\'), '\\');

    return in_array($name, ['ImportService', 'App\\Application\\Imports\\ImportService'], true);
}

function dovraCrmImportServiceTypeNode(?Node $type, array $aliases = []): bool
{
    if ($type instanceof Node\NullableType) {
        return dovraCrmImportServiceTypeNode($type->type, $aliases);
    }
    if ($type instanceof Node\Name) {
        return dovraCrmImportServiceClassName($type->toString(), $aliases);
    }

    return false;
}

function dovraCrmIsExcelClass(?Node $class, array $aliases = []): bool
{
    if (! $class instanceof Node\Name) {
        return false;
    }
    $name = ltrim($class->toString(), '\\');
    $name = ltrim($aliases[$name] ?? $name, '\\');

    return $name === 'Maatwebsite\\Excel\\Facades\\Excel';
}

/** @return list<array{canonical: bool, sensitive: bool, operation: string, line: int, receiver: string, keys: list<string>, context: string}> */
function dovraCrmAstOperations(string $sourceOrPath, bool $sourceInput = false): array
{
    $source = $sourceInput ? $sourceOrPath : (string) file_get_contents($sourceOrPath);
    $ast = dovraCrmParse($source);
    if ($ast === []) {
        return [[
            'canonical' => true,
            'sensitive' => true,
            'operation' => 'parse-error',
            'line' => 1,
            'receiver' => 'unknown',
            'keys' => [],
            'context' => 'parser returned no AST',
        ]];
    }

    $relative = $sourceInput ? '<fixture>' : dovraCrmRelativePath($sourceOrPath);
    $collector = new DovraCrmCanonicalAstCollector($relative);
    $traverser = new NodeTraverser;
    $traverser->addVisitor($collector);
    $traverser->traverse($ast);

    return $collector->operations;
}

/** @return list<Node> */
function dovraCrmParse(string $source): array
{
    try {
        return (new ParserFactory)->createForHostVersion()->parse($source) ?? [];
    } catch (Throwable) {
        return [];
    }
}

final class DovraCrmCanonicalAstCollector extends NodeVisitorAbstract
{
    /** @var list<array{canonical: bool, sensitive: bool, operation: string, line: int, receiver: string, keys: list<string>, context: string}> */
    public array $operations = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, string> */
    private array $locals = [];

    /** @var array<string, list<string>> */
    private array $pendingAssignments = [];

    private string $method = '';

    /** @var list<array{method: string, locals: array<string, string>, pending: array<string, list<string>>}> */
    private array $scopes = [];

    private const PERSISTENCE = [
        'create', 'createOrFirst', 'firstOrCreate', 'updateOrCreate', 'update', 'save',
        'fill', 'forceFill', 'upsert', 'insert', 'insertOrIgnore', 'associate', 'sync',
        'syncWithoutDetaching', 'attach', 'detach',
    ];

    private const SENSITIVE = ['tenant_id', 'company_id', 'contact_id', 'deal_id', 'owner_id', 'tag_id'];

    public function __construct(private readonly string $relativePath) {}

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
            foreach ($node->uses as $use) {
                $name = ltrim($use->name->toString(), '\\');
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $canonical = dovraCrmCanonicalClassTarget($name);
                if ($canonical !== null) {
                    $this->aliases[$alias] = $name;
                } elseif ($name === 'Illuminate\\Support\\Facades\\DB') {
                    $this->aliases[$alias] = 'DB';
                }
            }
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->scopes[] = ['method' => $this->method, 'locals' => $this->locals, 'pending' => $this->pendingAssignments];
            $this->method = $node->name->toString();
            $this->locals = [];
            $this->pendingAssignments = [];
            foreach ($node->params as $parameter) {
                if ($parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name)
                    && $parameter->type instanceof Node\Name) {
                    $target = dovraCrmCanonicalClassTarget(ltrim($parameter->type->toString(), '\\'))
                        ?? ($this->aliases[$parameter->type->toString()] ?? null);
                    if ($target !== null) {
                        $this->locals[$parameter->var->name] = $target;
                    }
                }
            }
        }

        if ($node instanceof Node\Stmt\Foreach && $node->valueVar instanceof Node\Expr\Variable
            && is_string($node->valueVar->name)) {
            $target = $this->resolve($node->expr);
            if ($target !== null) {
                $this->locals[$node->valueVar->name] = $target;
            }
        }

        if ($node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\NullsafeMethodCall) {
            $method = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            if ($method === null || ! in_array($method, self::PERSISTENCE, true)) {
                return null;
            }

            $receiver = $this->resolve($node);
            if ($receiver === null) {
                return null;
            }

            $keys = $this->keysFromArguments($node->args);
            $pending = $this->pendingAssignments[$receiver] ?? [];
            $effectiveKeys = array_values(array_unique([...$keys, ...$pending]));
            $sensitive = $this->isSensitive($method, $effectiveKeys, $receiver);
            if ($sensitive && ! $this->isRetainedBulkMutation($method, $effectiveKeys)) {
                $this->operations[] = [
                    'canonical' => true,
                    'sensitive' => true,
                    'operation' => $method,
                    'line' => $node->getStartLine(),
                    'receiver' => $receiver,
                    'keys' => $effectiveKeys,
                    'context' => $this->method === '' ? 'global' : $this->method,
                ];
            }
            if ($method === 'save') {
                unset($this->pendingAssignments[$receiver]);
            }
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Node\Expr\Assign) {
            $target = $this->resolve($node->expr);
            if ($node->var instanceof Node\Expr\Variable && is_string($node->var->name) && $target !== null) {
                $this->locals[$node->var->name] = $target;
            }

            if ($node->var instanceof Node\Expr\PropertyFetch
                && $node->var->name instanceof Node\Identifier) {
                $receiver = $this->resolve($node->var->var);
                if ($receiver !== null) {
                    $field = $node->var->name->toString();
                    $this->pendingAssignments[$receiver][] = $field;
                }
            }
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $scope = array_pop($this->scopes);
            if ($scope !== null) {
                $this->method = $scope['method'];
                $this->locals = $scope['locals'];
                $this->pendingAssignments = $scope['pending'];
            }
        }

        return null;
    }

    private function resolve(Node $expression): ?string
    {
        if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
            return $this->locals[$expression->name] ?? null;
        }

        if ($expression instanceof Node\Expr\New_ && $expression->class instanceof Node\Name) {
            return dovraCrmCanonicalClassTarget(ltrim($expression->class->toString(), '\\'), $this->aliases);
        }

        if ($expression instanceof Node\Expr\StaticCall && $expression->class instanceof Node\Name) {
            $class = ltrim($expression->class->toString(), '\\');
            $target = dovraCrmCanonicalClassTarget($class, $this->aliases);
            $method = $expression->name instanceof Node\Identifier ? $expression->name->toString() : '';
            if ($target !== null) {
                return $target;
            }
            $dbAlias = $this->aliases[$class] ?? $class;
            if ($dbAlias === 'DB' && $method === 'table' && isset($expression->args[0])) {
                $table = $expression->args[0]->value instanceof Node\Scalar\String_
                    ? strtolower($expression->args[0]->value->value)
                    : null;

                return dovraCrmCanonicalTableTarget($table);
            }
        }

        if ($expression instanceof Node\Expr\MethodCall || $expression instanceof Node\Expr\NullsafeMethodCall) {
            return $this->resolve($expression->var);
        }

        if ($expression instanceof Node\Expr\Ternary) {
            return $this->resolve($expression->if ?? $expression->else)
                ?? $this->resolve($expression->else);
        }

        return null;
    }

    /** @param list<Node\Arg> $arguments @return list<string> */
    private function keysFromArguments(array $arguments): array
    {
        $keys = [];
        foreach ($arguments as $argument) {
            $keys = [...$keys, ...$this->keysFromNode($argument->value)];
        }

        return array_values(array_unique($keys));
    }

    /** @return list<string> */
    private function keysFromNode(Node $node): array
    {
        if ($node instanceof Node\Expr\Array_) {
            $keys = [];
            foreach ($node->items as $item) {
                if ($item === null) {
                    $keys[] = '*';

                    continue;
                }
                if ($item->key instanceof Node\Scalar\String_) {
                    $keys[] = $item->key->value;
                } else {
                    $keys[] = '*';
                }
                $keys = [...$keys, ...$this->keysFromNode($item->value)];
            }

            return $keys;
        }
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            return ['*'];
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && in_array($node->name->toString(), ['array_merge', 'array_replace'], true)) {
            $keys = [];
            foreach ($node->args as $argument) {
                $keys = [...$keys, ...$this->keysFromNode($argument->value)];
            }

            return $keys;
        }

        return [];
    }

    /** @param list<string> $keys */
    private function isSensitive(string $method, array $keys, string $receiver): bool
    {
        // Every canonical persistence call is a writer boundary. Retained
        // lifecycle/delete/tag mutations are filtered by isRetainedBulkMutation
        // after this strict classification; safe-looking fields must not hide a
        // direct model save/update bypass (e.g. enrichment writing job_title).
        return true;
    }

    /** @param list<string> $keys */
    private function containsSensitive(array $keys): bool
    {
        return (bool) array_intersect($keys, self::SENSITIVE);
    }

    /** @param list<string> $keys */
    private function isRetainedBulkMutation(string $method, array $keys): bool
    {
        if ($this->relativePath !== 'app/Application/Crm/BulkOperationsService.php') {
            return false;
        }

        $safe = match ($this->method) {
            'bulkUpdateLifecycleStage' => ['lifecycle_stage', 'updated_at'],
            'bulkDelete' => ['deleted_at', 'updated_at'],
            'bulkAddTag', 'bulkRemoveTag' => ['tags'],
            default => [],
        };

        return $safe !== [] && array_diff($keys, $safe) === [] && ! $this->containsSensitive($keys);
    }
}

function dovraCrmCanonicalClassTarget(string $name, array $aliases = []): ?string
{
    $name = ltrim($name, '\\');
    $name = $aliases[$name] ?? $name;

    return match ($name) {
        'CrmCompany', 'App\\Models\\CrmCompany' => 'crm_companies',
        'Contact', 'App\\Models\\Contact' => 'crm_contacts',
        'CrmActivity', 'App\\Models\\CrmActivity' => 'crm_activities',
        'Tag', 'App\\Models\\Tag' => 'tags',
        default => null,
    };
}

function dovraCrmCanonicalTableTarget(?string $table): ?string
{
    return in_array($table, ['crm_companies', 'crm_contacts', 'crm_activities', 'tags', 'contact_tag', 'deal_tag'], true)
        ? $table
        : null;
}
