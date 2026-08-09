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

    $importServiceBypass = <<<'PHP'
        <?php
        use App\Application\Imports\ImportService;

        final class ImportServiceBypass
        {
            public function __construct(private ImportService $service) {}

            public function run(): void
            {
                $serviceAlias = $this->service;
                $candidate = $serviceAlias->makeImporter('contacts', 'tenant');
                $candidate->templateHeaders();
                $candidate->model([]);
                $factory = new ImportService();
                $factoryAlias = $factory;
                $fromNew = $factoryAlias->makeImporter('contacts', 'tenant');
                $fromNew->model([]);
            }
        }
        PHP;

    $importServiceBypassViolations = dovraCrmImportSourceViolations(
        $importServiceBypass,
        'app/Tests/Fixtures/ImportServiceBypass.php',
    );
    expect(implode('; ', $importServiceBypassViolations))->toContain('makeImporter outside template-only path')
        ->and(implode('; ', $importServiceBypassViolations))->toContain('importer->model() bypass');

    $importTemplateBinding = <<<'PHP'
        <?php
        use App\Application\Imports\ImportService;

        final class ImportWizardFixture
        {
            public function __construct(private ImportService $service) {}

            private function getExpectedHeaders(): string
            {
                $candidate = $this->service->makeImporter('contacts', '');
                return implode(', ', $candidate->templateHeaders());
            }
        }
        PHP;

    expect(dovraCrmImportSourceViolations(
        $importTemplateBinding,
        'app/Filament/App/Pages/ImportExport/ImportWizard.php',
    ))->toBeEmpty('typed/promoted ImportService receiver should allow only its matching templateHeaders call');

    $unrelatedMakeImporter = <<<'PHP'
        <?php
        final class UnrelatedFactory
        {
            public function makeImporter(): object { return new stdClass(); }
        }

        $other = new UnrelatedFactory();
        $other->makeImporter()->templateHeaders();
        PHP;

    expect(dovraCrmImportSourceViolations(
        $unrelatedMakeImporter,
        'app/Tests/Fixtures/UnrelatedFactory.php',
    ))->toBeEmpty('makeImporter on an unrelated object must not create an import-boundary false positive');

    $importScopeIsolation = <<<'PHP'
        <?php
        use App\Application\Imports\ImportService;
        use App\Application\Imports\Imports\ContactsImport as ContactCsv;

        final class ImportScopeA
        {
            public function seed(): void
            {
                $this->service = app(ImportService::class);
                $service = app(ImportService::class);
                $candidate = new ContactCsv('tenant');
            }

            public function check(): void
            {
                $this->service = new stdClass();
                $service = new stdClass();
                $candidate = new stdClass();
                $service->makeImporter('contacts', 'tenant');
                $candidate->model([]);
            }
        }

        final class ImportScopeB
        {
            public function check(): void
            {
                $this->service = new stdClass();
                $service = new stdClass();
                $candidate = new stdClass();
                $service->makeImporter('companies', 'tenant');
                $candidate->model([]);
            }
        }
        PHP;

    expect(dovraCrmImportSourceViolations(
        $importScopeIsolation,
        'app/Tests/Fixtures/ImportScopeIsolation.php',
    ))->toBeEmpty('import receiver state must be isolated by class and method scope');

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

    $webToLeadValidStatic = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
            });
        }
        PHP;

    $webToLeadInvalidInstance = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                $contact = Contact::find($id);
                $contact->update(['job_title' => 'drift']);
            });
        }
        PHP;

    $webToLeadInvalidQuery = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                $query = Contact::query()->where('id', $id);
                $query->update(['job_title' => 'drift']);
            });
        }
        PHP;

    $webToLeadInvalidTable = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                DB::table('crm_contacts')->where('id', $id)->update(['job_title' => 'drift']);
            });
        }
        PHP;

    $webToLeadInvalidRelation = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                $contact = Contact::find($id);
                $contact->tags()->sync($tagIds);
            });
        }
        PHP;

    expect(dovraCrmWebToLeadSentinel($webToLeadValidStatic, true)['ok'])->toBeTrue();
    expect(dovraCrmWebToLeadSentinel($webToLeadInvalidInstance, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadInvalidQuery, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadInvalidTable, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadInvalidRelation, true)['ok'])->toBeFalse();

    $webToLeadMaskedDelete = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                $contact = Contact::find($id);
                $contact->delete();
            });
        }
        PHP;

    $webToLeadScopedRelation = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                $contact = Contact::find($id);
                $contact->tags()->where('tenant_id', $tenantId)->sync($tagIds);
            });
        }
        PHP;

    $webToLeadQuietCreateFamily = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                Contact::createQuietly(['tenant_id' => $tenantId]);
                Contact::forceCreateQuietly(['tenant_id' => $tenantId]);
                $contact = Contact::find($id);
                $contact->activities()->createManyQuietly([
                    ['tenant_id' => $tenantId],
                ]);
                $contact->activities()->forceCreateManyQuietly([
                    ['tenant_id' => $tenantId],
                ]);
            });
        }
        PHP;

    $webToLeadAlternatePivotFamily = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                $contact = Contact::find($id);
                $contact->tags()->toggle($tagIds);
                $contact->tags()->toggleOrFail($tagIds);
                $contact->tags()->syncOrFail($tagIds);
                $contact->tags()->syncWithoutDetachingOrFail($tagIds);
                $contact->tags()->syncWithPivotValues($tagIds, ['tenant_id' => $tenantId]);
                $contact->tags()->syncWithPivotValuesOrFail($tagIds, ['tenant_id' => $tenantId]);
                $contact->tags()->attachOrFail($tagIds);
                $contact->tags()->detachOrFail($tagIds);
                $contact->tags()->updateExistingPivot($tagId, ['tenant_id' => $tenantId]);
                $contact->tags()->updateExistingPivotOrFail($tagId, ['tenant_id' => $tenantId]);
            });
        }
        PHP;

    $webToLeadFillInsertFamily = <<<'PHP'
        <?php
        use App\Models\Contact;
        use App\Models\CrmCompany;
        use App\Models\CrmActivity;
        use Illuminate\Support\Facades\DB;

        function submit(): void
        {
            DB::transaction(function (): void {
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                    ['tenant_id' => $tenantId, 'name' => 'Acme'],
                );
                Contact::create(['tenant_id' => $tenantId]);
                CrmActivity::create(['tenant_id' => $tenantId]);
                Contact::query()->fillAndInsert([
                    ['tenant_id' => $tenantId],
                ]);
                Contact::query()->fillAndInsertOrIgnore([
                    ['tenant_id' => $tenantId],
                ]);
                Contact::query()->fillAndInsertGetId([
                    'tenant_id' => $tenantId,
                ]);
            });
        }
        PHP;

    expect(dovraCrmWebToLeadSentinel($webToLeadMaskedDelete, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadScopedRelation, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadQuietCreateFamily, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadAlternatePivotFamily, true)['ok'])->toBeFalse();
    expect(dovraCrmWebToLeadSentinel($webToLeadFillInsertFamily, true)['ok'])->toBeFalse();
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

    $collector = new DovraCrmCanonicalAstCollector(
        $sourceInput ? '<fixture>' : dovraCrmRelativePath($sourceOrPath),
    );
    $collectorTraverser = new NodeTraverser;
    $collectorTraverser->addVisitor($collector);
    $collectorTraverser->traverse($ast);

    $visitor = new class extends NodeVisitorAbstract
    {
        public int $transactions = 0;

        public int $canonicalWritesInTransaction = 0;

        public int $canonicalWritesOutsideTransaction = 0;

        public int $unboundTenantWrites = 0;

        public bool $sensitiveStateInput = false;

        public bool $sensitiveFormInput = false;

        public bool $tenantDerived = false;

        /** @var list<string> */
        public array $canonicalWriterCalls = [];

        public bool $writerCallOutsideTransaction = false;

        public bool $writerCallWithoutTenant = false;

        /** @var array<string, string> */
        private array $aliases = [];

        /** @var array<string, bool> */
        private array $writerVariables = [];

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

            if ($node instanceof Node\Stmt\ClassMethod) {
                foreach ($node->params as $parameter) {
                    if ($parameter->var instanceof Node\Expr\Variable
                        && is_string($parameter->var->name)
                        && dovraCrmWriterTypeNode($parameter->type, $this->aliases)) {
                        $this->writerVariables[$parameter->var->name] = true;
                    }
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

            if ($node instanceof Node\Expr\MethodCall
                && $node->var instanceof Node\Expr\Variable
                && is_string($node->var->name)
                && isset($this->writerVariables[$node->var->name])
                && $node->name instanceof Node\Identifier
                && in_array($node->name->toString(), ['createCompany', 'createContact', 'createActivity'], true)) {
                $this->canonicalWriterCalls[] = $node->name->toString();
                $this->writerCallOutsideTransaction = $this->writerCallOutsideTransaction || $this->transactionDepth === 0;
                $tenantArgument = $node->args[0]->value ?? null;
                $this->writerCallWithoutTenant = $this->writerCallWithoutTenant
                    || ! $tenantArgument instanceof Node
                    || ! $this->isDerivedTenantExpression($tenantArgument);
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

    $approvedWriterCalls = ['createCompany', 'createContact', 'createActivity'];
    sort($approvedWriterCalls);
    $actualWriterCalls = $visitor->canonicalWriterCalls;
    sort($actualWriterCalls);

    if ($collector->transactions !== 1 || $actualWriterCalls !== $approvedWriterCalls) {
        return ['ok' => false, 'reason' => 'WebToLead must delegate exactly one company, contact and activity creation to CanonicalCrmEntityWriter'];
    }
    if ($collector->operationContexts !== []) {
        return ['ok' => false, 'reason' => 'WebToLead contains a direct CRM persistence operation'];
    }
    if ($visitor->writerCallOutsideTransaction || $visitor->writerCallWithoutTenant) {
        return ['ok' => false, 'reason' => 'WebToLead writer calls must be tenant-derived and inside one transaction'];
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
        private array $serviceProperties = [];

        /** @var array<string, bool> */
        private array $importProperties = [];

        /** @var array<string, bool> */
        private array $templateProperties = [];

        /** @var array<string, bool> */
        private array $importReceivers = [];

        /** @var array<string, bool> */
        private array $templateReceivers = [];

        private string $method = '';

        private bool $templateHeadersSeen = false;

        /**
         * Receiver maps are method-local. Property maps are class-local. The
         * explicit stacks make nested classes/methods restore their prior
         * state instead of leaking symbols into sibling scopes.
         *
         * @var list<array{
         *     serviceProperties: array<string, bool>,
         *     importProperties: array<string, bool>,
         *     templateProperties: array<string, bool>,
         *     serviceReceivers: array<string, bool>,
         *     importReceivers: array<string, bool>,
         *     templateReceivers: array<string, bool>,
         *     method: string,
         *     templateHeadersSeen: bool
         * }>
         */
        private array $classScopes = [];

        /**
         * @var list<array{
         *     serviceReceivers: array<string, bool>,
         *     importReceivers: array<string, bool>,
         *     templateReceivers: array<string, bool>,
         *     method: string,
         *     templateHeadersSeen: bool
         * }>
         */
        private array $methodScopes = [];

        public function __construct(private readonly string $relative) {}

        public function enterNode(Node $node): null
        {
            if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
                foreach ($node->uses as $use) {
                    $this->aliases[$use->alias?->toString() ?? $use->name->getLast()] = ltrim($use->name->toString(), '\\');
                }
            }

            if ($node instanceof Node\Stmt\Class_) {
                $this->classScopes[] = [
                    'serviceProperties' => $this->serviceProperties,
                    'importProperties' => $this->importProperties,
                    'templateProperties' => $this->templateProperties,
                    'serviceReceivers' => $this->serviceReceivers,
                    'importReceivers' => $this->importReceivers,
                    'templateReceivers' => $this->templateReceivers,
                    'method' => $this->method,
                    'templateHeadersSeen' => $this->templateHeadersSeen,
                ];
                $this->serviceProperties = [];
                $this->importProperties = [];
                $this->templateProperties = [];
                $this->serviceReceivers = [];
                $this->importReceivers = [];
                $this->templateReceivers = [];
                $this->method = '';
                $this->templateHeadersSeen = false;
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                $this->methodScopes[] = [
                    'serviceReceivers' => $this->serviceReceivers,
                    'importReceivers' => $this->importReceivers,
                    'templateReceivers' => $this->templateReceivers,
                    'method' => $this->method,
                    'templateHeadersSeen' => $this->templateHeadersSeen,
                ];
                $this->method = $node->name->toString();
                $this->serviceReceivers = [];
                $this->importReceivers = [];
                $this->templateReceivers = [];
                $this->templateHeadersSeen = false;
                foreach ($node->params as $parameter) {
                    if ($parameter->var instanceof Node\Expr\Variable
                        && is_string($parameter->var->name)
                        && dovraCrmImportServiceTypeNode($parameter->type, $this->aliases)) {
                        $this->serviceReceivers[$this->symbol($parameter->var)] = true;
                        if ($node->name->toString() === '__construct' && $parameter->flags !== 0) {
                            $this->serviceProperties['$this->'.$parameter->var->name] = true;
                        }
                    }
                }
            }

            if ($node instanceof Node\Stmt\Property
                && dovraCrmImportServiceTypeNode($node->type, $this->aliases)) {
                foreach ($node->props as $property) {
                    $this->serviceProperties['$this->'.$property->name->toString()] = true;
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
                    && $this->isServiceReceiver($node->var)
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
                    $isServiceReceiver = $this->isServiceReceiver($node->expr);
                    $isImportReceiver = $this->isImportReceiver($node->expr);
                    $isTemplateReceiver = $this->isTemplateReceiver($node->expr);

                    $this->assignReceiverState(
                        $symbol,
                        $isServiceReceiver,
                        $isImportReceiver || $isTemplateReceiver,
                        $isTemplateReceiver,
                    );
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                if ($this->method === 'getExpectedHeaders'
                    && $this->relative === 'app/Filament/App/Pages/ImportExport/ImportWizard.php'
                    && ! $this->templateHeadersSeen) {
                    $this->violations[] = $this->relative.':template path lacks templateHeaders';
                }

                $scope = array_pop($this->methodScopes);
                if ($scope !== null) {
                    $this->serviceReceivers = $scope['serviceReceivers'];
                    $this->importReceivers = $scope['importReceivers'];
                    $this->templateReceivers = $scope['templateReceivers'];
                    $this->method = $scope['method'];
                    $this->templateHeadersSeen = $scope['templateHeadersSeen'];
                } else {
                    $this->serviceReceivers = [];
                    $this->importReceivers = [];
                    $this->templateReceivers = [];
                    $this->method = '';
                    $this->templateHeadersSeen = false;
                }
            }

            if ($node instanceof Node\Stmt\Class_) {
                $scope = array_pop($this->classScopes);
                if ($scope !== null) {
                    $this->serviceProperties = $scope['serviceProperties'];
                    $this->importProperties = $scope['importProperties'];
                    $this->templateProperties = $scope['templateProperties'];
                    $this->serviceReceivers = $scope['serviceReceivers'];
                    $this->importReceivers = $scope['importReceivers'];
                    $this->templateReceivers = $scope['templateReceivers'];
                    $this->method = $scope['method'];
                    $this->templateHeadersSeen = $scope['templateHeadersSeen'];
                }
            }

            return null;
        }

        private function assignReceiverState(
            string $symbol,
            bool $isServiceReceiver,
            bool $isImportReceiver,
            bool $isTemplateReceiver,
        ): void {
            if (str_starts_with($symbol, '$this->')) {
                if ($isServiceReceiver) {
                    $this->serviceProperties[$symbol] = true;
                } else {
                    unset($this->serviceProperties[$symbol]);
                }
                if ($isImportReceiver) {
                    $this->importProperties[$symbol] = true;
                } else {
                    unset($this->importProperties[$symbol]);
                }
                if ($isTemplateReceiver) {
                    $this->templateProperties[$symbol] = true;
                } else {
                    unset($this->templateProperties[$symbol]);
                }

                return;
            }

            if ($isServiceReceiver) {
                $this->serviceReceivers[$symbol] = true;
            } else {
                unset($this->serviceReceivers[$symbol]);
            }
            if ($isImportReceiver) {
                $this->importReceivers[$symbol] = true;
            } else {
                unset($this->importReceivers[$symbol]);
            }
            if ($isTemplateReceiver) {
                $this->templateReceivers[$symbol] = true;
            } else {
                unset($this->templateReceivers[$symbol]);
            }
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
                return isset($this->serviceReceivers[$symbol]) || isset($this->serviceProperties[$symbol]);
            }
            if ($node instanceof Node\Expr\FuncCall
                && $node->name instanceof Node\Name
                && in_array(strtolower($node->name->toString()), ['app', 'resolve'], true)
                && isset($node->args[0])) {
                return dovraCrmImportServiceClassConst($node->args[0]->value, $this->aliases);
            }
            if ($node instanceof Node\Expr\New_
                && $node->class instanceof Node\Name) {
                return dovraCrmImportServiceClassName($node->class->toString(), $this->aliases);
            }

            return false;
        }

        private function isTemplateReceiver(Node $node): bool
        {
            $symbol = $this->symbol($node);
            if ($symbol !== null) {
                return isset($this->templateReceivers[$symbol]) || isset($this->templateProperties[$symbol]);
            }

            return $this->isMakeImporterExpression($node);
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
                return isset($this->importReceivers[$symbol]) || isset($this->importProperties[$symbol]);
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

            return $symbol !== null
                && (isset($this->templateReceivers[$symbol]) || isset($this->templateProperties[$symbol]));
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

    public int $transactions = 0;

    /** @var list<array{operation: string, line: int, receiver: string, context: string, inTransaction: bool, tenantBound: bool}> */
    public array $operationContexts = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, string> */
    private array $locals = [];

    /** @var array<string, list<string>> */
    private array $pendingAssignments = [];

    /** @var array<string, bool> */
    private array $derivedTenantVariables = [];

    /** @var array<string, bool> */
    private array $tenantScopedLocals = [];

    private int $transactionDepth = 0;

    private string $method = '';

    /** @var list<array{method: string, locals: array<string, string>, pending: array<string, list<string>>, derived: array<string, bool>, scoped: array<string, bool>}> */
    private array $scopes = [];

    /**
     * Union of the installed Eloquent Model/Builder, relation and pivot-table
     * mutation APIs (installed Laravel 13.x), plus the Query Builder write variants.
     * Keep this explicit so adding a quiet/fail/or-pivot sibling cannot bypass
     * the canonical writer ratchet.
     */
    private const PERSISTENCE = [
        // Model/Builder create and upsert family.
        'create', 'createQuietly', 'createMany', 'createManyQuietly', 'forceCreate', 'forceCreateQuietly',
        'forceCreateMany', 'forceCreateManyQuietly', 'createOrFirst', 'firstOrCreate', 'updateOrCreate',
        'incrementOrCreate', 'update', 'updateOrFail', 'updateQuietly', 'upsert', 'fillAndInsert',
        'fillAndInsertOrIgnore', 'fillAndInsertGetId',
        // Model lifecycle and save/update variants.
        'save', 'saveQuietly', 'saveMany', 'saveManyQuietly', 'saveOrFail', 'saveOrIgnore', 'delete',
        'deleteQuietly', 'deleteOrFail', 'forceDelete', 'forceDeleteQuietly', 'restore', 'restoreQuietly',
        'push', 'pushQuietly', 'increment', 'incrementEach', 'incrementQuietly', 'incrementEachQuietly',
        'decrement', 'decrementEach', 'decrementQuietly', 'decrementEachQuietly', 'touch', 'touchQuietly',
        'touchIfTouching',
        'fill', 'forceFill', 'associate', 'dissociate', 'disassociate',
        // Pivot-table mutation family (including fail/quiet siblings).
        'sync', 'syncWithoutDetaching', 'syncOrFail', 'syncWithoutDetachingOrFail', 'syncWithPivotValues',
        'syncWithPivotValuesOrFail', 'toggle', 'toggleOrFail', 'attach', 'attachOrFail', 'detach',
        'detachOrFail', 'updateExistingPivot', 'updateExistingPivotOrFail',
        // Query Builder write variants.
        'insert', 'insertGetId', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertUsing',
        'insertOrIgnoreUsing', 'updateFrom', 'updateOrInsert', 'truncate',
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
            $this->scopes[] = [
                'method' => $this->method,
                'locals' => $this->locals,
                'pending' => $this->pendingAssignments,
                'derived' => $this->derivedTenantVariables,
                'scoped' => $this->tenantScopedLocals,
            ];
            $this->method = $node->name->toString();
            $this->locals = [];
            $this->pendingAssignments = [];
            $this->derivedTenantVariables = [];
            $this->tenantScopedLocals = [];
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

        if ($node instanceof Node\Expr\StaticCall
            && $node->name instanceof Node\Identifier
            && $node->name->toString() === 'transaction'
            && $node->class instanceof Node\Name
            && (($class = ltrim($node->class->toString(), '\\')) === 'DB'
                || str_ends_with($class, '\\DB'))) {
            $this->transactions++;
            $this->transactionDepth++;
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
            $operationContext = [
                'operation' => $method,
                'line' => $node->getStartLine(),
                'receiver' => $receiver,
                'context' => $this->method === '' ? 'global' : $this->method,
                'inTransaction' => $this->transactionDepth > 0,
                'tenantBound' => $this->hasTenantBinding($node),
            ];
            $this->operationContexts[] = $operationContext;
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

            if ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) {
                $derived = dovraCrmContainsFilamentTenant($node->expr)
                    || ($node->expr instanceof Node\Expr\Variable
                        && is_string($node->expr->name)
                        && ($this->derivedTenantVariables[$node->expr->name] ?? false));
                if ($derived) {
                    $this->derivedTenantVariables[$node->var->name] = $this->transactionDepth > 0;
                }

                $scoped = $this->hasTenantScope($node->expr)
                    || ($node->expr instanceof Node\Expr\Variable
                        && is_string($node->expr->name)
                        && ($this->tenantScopedLocals[$node->expr->name] ?? false));
                if ($scoped) {
                    $this->tenantScopedLocals[$node->var->name] = true;
                }
            }
        }

        if ($node instanceof Node\Expr\StaticCall
            && $node->name instanceof Node\Identifier
            && $node->name->toString() === 'transaction'
            && $node->class instanceof Node\Name
            && (($class = ltrim($node->class->toString(), '\\')) === 'DB'
                || str_ends_with($class, '\\DB'))) {
            $this->transactionDepth = max(0, $this->transactionDepth - 1);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $scope = array_pop($this->scopes);
            if ($scope !== null) {
                $this->method = $scope['method'];
                $this->locals = $scope['locals'];
                $this->pendingAssignments = $scope['pending'];
                $this->derivedTenantVariables = $scope['derived'];
                $this->tenantScopedLocals = $scope['scoped'];
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

    private function hasTenantBinding(Node $write): bool
    {
        if ($this->hasTenantScope($write)
            || ($write instanceof Node\Expr\MethodCall
                && $write->var instanceof Node\Expr\Variable
                && is_string($write->var->name)
                && ($this->tenantScopedLocals[$write->var->name] ?? false))) {
            return true;
        }

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

    private function hasTenantScope(Node $node): bool
    {
        if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall)
            && $node->name instanceof Node\Identifier
            && in_array($node->name->toString(), ['where', 'whereIn', 'whereIntegerInRaw'], true)
            && isset($node->args[0])
            && $node->args[0]->value instanceof Node\Scalar\String_
            && strtolower($node->args[0]->value->value) === 'tenant_id'
            && isset($node->args[1])) {
            return $this->isDerivedTenantExpression($node->args[1]->value);
        }

        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            if ($child instanceof Node && $this->hasTenantScope($child)) {
                return true;
            }
            if (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node && $this->hasTenantScope($item)) {
                        return true;
                    }
                }
            }
        }

        return false;
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
        if (in_array($method, [
            'delete', 'deleteQuietly', 'forceDelete', 'forceDeleteQuietly', 'restore', 'restoreQuietly',
        ], true)) {
            // Delete/restore remain policy-scoped outside this writer contract;
            // WebToLead still rejects them through its exact operation shape.
            return false;
        }

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
