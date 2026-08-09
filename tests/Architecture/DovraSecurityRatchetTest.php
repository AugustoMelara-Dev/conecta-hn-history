<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\Cast\String_ as StringCast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DovraSecurityRatchetTest extends TestCase
{
    #[Test]
    public function dovra_security_slice_documents_the_required_shared_authority_seams(): void
    {
        $registry = new DovraIntegrationSeamRegistry;
        $keys = array_column($registry->all(), 'key');

        self::assertSame([
            'legacy_account_privacy',
            'sensitive_log_scrubber',
            'safe_catalog_image_normalizer',
            'ai_capability_policy',
            'postgres_rls_runtime',
            'sales_export_tenant_guard',
            'audit_context',
            'postgres_rls_inventory_registration',
        ], $keys);

        self::assertSame('incomplete', (new DovraIntegrationSeamRegistry)->status('postgres_rls_inventory_registration'));
        self::assertSame(
            ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            $registry->seamsForEntrypoint('directory_guide_chat'),
        );
        self::assertSame(
            ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            $registry->seamsForEntrypoint('tenant_agent_chat'),
        );
        self::assertFalse($registry->entrypointRequiresTenantContext('directory_guide_chat'));
        self::assertTrue($registry->entrypointRequiresTenantContext('tenant_agent_chat'));
    }

    #[Test]
    public function the_two_ai_http_owners_guard_before_runtime(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'app/Http/Controllers/Api/V1/DirectoryGuideChatController.php' => 'public',
            'app/Http/Controllers/Api/V1/TenantAgentChatController.php' => 'private',
        ] as $relativePath => $runtimeMethod) {
            $source = (string) file_get_contents($root.'/'.$relativePath);

            self::assertStringContainsString('DovraDataIngressGuard', $source);
            self::assertStringContainsString('DOVRA_SOURCE', $source);
            self::assertStringContainsString('DataEnvelope', $source);
            self::assertStringContainsString(
                $runtimeMethod === 'public'
                    ? "private const DOVRA_SOURCE = 'directory_guide_chat';"
                    : "private const DOVRA_SOURCE = 'tenant_agent_chat';",
                $source,
            );

            $guardPosition = strpos($source, '->inspect(new DataEnvelope');
            $runtimePosition = strpos($source, 'runtime->'.$runtimeMethod.'(');

            self::assertIsInt($guardPosition);
            self::assertIsInt($runtimePosition);
            self::assertLessThan($runtimePosition, $guardPosition === false ? PHP_INT_MAX : $guardPosition);
            // The inspected envelope intentionally contains the validated request
            // payload. Check only the controller code after the guard's decision
            // handling begins, so this ratchet does not flag that legitimate use.
            $postGuardPosition = strpos($source, '$failure = $this->ingressFailureResponse($decision);', $guardPosition);

            self::assertIsInt($postGuardPosition);
            self::assertStringNotContainsString('$validated', substr($source, $postGuardPosition));
        }
    }

    #[Test]
    public function profile_optimization_authorizes_then_guards_and_dispatches_only_redacted_input(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/app/Http/Controllers/Api/V1/AiOptimizationController.php');

        self::assertSecureProfileOptimizationMethod($source);
    }

    #[Test]
    public function profile_optimization_ratchet_rejects_comments_strings_reversed_guards_and_raw_aliases(): void
    {
        $fixtures = [
            'A real $this->privateAiAccess->allows() call is required.' => <<<'PHP'
                <?php
                final class AiOptimizationController {
                    private const DOVRA_SOURCE = 'private_ai_prompt';
                    public function optimizeProfile($request) {
                        // $this->privateAiAccess->allows($user, $tenantId);
                        $bait = '$this->dovraIngress->inspect(new DataEnvelope); GenerateBusinessProfileJob::dispatch';
                        $validated = $request->validated();
                        $rawAlias = $validated;
                        GenerateBusinessProfileJob::dispatch(
                            businessName: $rawAlias['business_name'], description: $rawAlias['description'],
                            userId: $actorId, tenantId: $tenantId, targetAudience: $rawAlias['target_audience'],
                            tone: $rawAlias['tone'], keywords: $rawAlias['keywords'],
                        );
                    }
                }
                PHP,
            'Private AI authorization must precede Dovra inspection.' => <<<'PHP'
                <?php
                final class AiOptimizationController {
                    private const DOVRA_SOURCE = 'private_ai_prompt';
                    public function optimizeProfile($request) {
                        $decision = $this->dovraIngress->inspect(new DataEnvelope(payload: $validated));
                        $this->privateAiAccess->allows($user, $tenantId);
                        $rawAlias = $request->validated();
                        GenerateBusinessProfileJob::dispatch(
                            businessName: $rawAlias['business_name'], description: $rawAlias['description'],
                            userId: $actorId, tenantId: $tenantId, targetAudience: $rawAlias['target_audience'],
                            tone: $rawAlias['tone'], keywords: $rawAlias['keywords'],
                        );
                    }
                }
                PHP,
            '$redactedPayload must come directly from $decision->redactedPayload.' => <<<'PHP'
                <?php
                final class AiOptimizationController {
                    private const DOVRA_SOURCE = 'private_ai_prompt';
                    public function optimizeProfile($request) {
                        $this->privateAiAccess->allows($user, $tenantId);
                        $validated = $request->validated();
                        $decision = $this->dovraIngress->inspect(new DataEnvelope(
                            sourceSystem: self::DOVRA_SOURCE, payload: $validated, tenantId: $tenantId,
                            actorId: $actorId, channel: 'private_ai_web',
                            context: ['business_type' => $policyBusinessType],
                        ));
                        $rawAlias = $validated;
                        $redactedPayload = $rawAlias;
                        GenerateBusinessProfileJob::dispatch(
                            businessName: $redactedPayload['business_name'], description: $redactedPayload['description'],
                            userId: $actorId, tenantId: $tenantId, targetAudience: $redactedPayload['target_audience'],
                            tone: $redactedPayload['tone'], keywords: $redactedPayload['keywords'],
                        );
                    }
                }
                PHP,
            'DOVRA_SOURCE must equal private_ai_prompt.' => <<<'PHP'
                <?php
                final class AiOptimizationController {
                    private const DOVRA_SOURCE = 'changed_private_ai_source';
                    public function optimizeProfile($request) {
                        $this->privateAiAccess->allows($user, $tenantId);
                        $validated = $request->validated();
                        $decision = $this->dovraIngress->inspect(new DataEnvelope(
                            sourceSystem: self::DOVRA_SOURCE, payload: $validated, tenantId: $tenantId,
                            actorId: $actorId, channel: 'private_ai_web',
                            context: ['business_type' => $policyBusinessType],
                        ));
                        $redactedPayload = $decision->redactedPayload;
                        GenerateBusinessProfileJob::dispatch(
                            businessName: $redactedPayload['business_name'], description: $redactedPayload['description'],
                            userId: $actorId, tenantId: $tenantId, targetAudience: $redactedPayload['target_audience'],
                            tone: $redactedPayload['tone'], keywords: $redactedPayload['keywords'],
                        );
                    }
                }
                PHP,
            '$decision must not be overwritten after Dovra inspection.' => <<<'PHP'
                <?php
                final class AiOptimizationController {
                    private const DOVRA_SOURCE = 'private_ai_prompt';
                    public function optimizeProfile($request) {
                        $this->privateAiAccess->allows($user, $tenantId);
                        $validated = $request->validated();
                        $decision = $this->dovraIngress->inspect(new DataEnvelope(
                            sourceSystem: self::DOVRA_SOURCE, payload: $validated, tenantId: $tenantId,
                            actorId: $actorId, channel: 'private_ai_web',
                            context: ['business_type' => $policyBusinessType],
                        ));
                        $rawAlias = $validated;
                        $decision = $rawAlias;
                        $redactedPayload = $decision->redactedPayload;
                        $redactedPayload += $rawAlias;
                        GenerateBusinessProfileJob::dispatch(
                            businessName: $redactedPayload['business_name'], description: $redactedPayload['description'],
                            userId: $actorId, tenantId: $tenantId, targetAudience: $redactedPayload['target_audience'],
                            tone: $redactedPayload['tone'], keywords: $redactedPayload['keywords'],
                        );
                    }
                }
                PHP,
        ];

        foreach ($fixtures as $expectedFailure => $fixture) {
            try {
                self::assertSecureProfileOptimizationMethod($fixture);
            } catch (AssertionFailedError $failure) {
                self::assertStringContainsString($expectedFailure, $failure->getMessage());

                continue;
            }

            self::fail('Adversarial source must not satisfy the profile ingress ratchet.');
        }
    }

    #[Test]
    public function catalog_vision_worker_guards_stored_descriptor_before_io_and_provider(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSecureCatalogVisionJob(
            (string) file_get_contents($root.'/app/Infrastructure/Jobs/AI/ProcessCatalogImportItem.php'),
            (string) file_get_contents($root.'/app/Application/Ai/Catalog/CatalogVisionIngressGuard.php'),
        );
    }

    #[Test]
    public function catalog_vision_ratchet_rejects_comment_bait_io_before_guard_and_raw_provider_alias(): void
    {
        $guard = <<<'PHP'
            <?php
            final class CatalogVisionIngressGuard {
                private const DOVRA_SOURCE = 'upload';
                private const CHANNEL = 'catalog_vision_queue';
                public function inspect($tenantId, $actorId, $descriptor) {
                    return $this->ingress->inspect(new DataEnvelope(
                        sourceSystem: self::DOVRA_SOURCE, payload: $descriptor, tenantId: $tenantId,
                        actorId: $actorId, channel: self::CHANNEL, context: [],
                    ));
                }
            }
            PHP;
        $fixtures = [
            <<<'PHP'
                <?php
                final class ProcessCatalogImportItem {
                    public function handle($provider, $catalogIngress) {
                        // $decision = $catalogIngress->inspect($tenantId, $actorId, $descriptor);
                        $bait = 'Storage::disk()->get(); $provider->analyze(new CatalogVisionImage)';
                    }
                }
                PHP,
            <<<'PHP'
                <?php
                final class ProcessCatalogImportItem {
                    public function handle($provider, $catalogIngress) {
                        $contents = Storage::disk($item->source_disk)->get($item->source_path);
                        $decision = $catalogIngress->inspect($tenantId, $actorId, $descriptor);
                        $redactedDescriptor = $catalogIngress->validatedDescriptor($decision);
                        $provider->analyze(new CatalogVisionImage(
                            originalName: $redactedDescriptor['filename'], mimeType: $redactedDescriptor['mime'],
                            contents: $contents,
                        ));
                    }
                }
                PHP,
            <<<'PHP'
                <?php
                final class ProcessCatalogImportItem {
                    public function handle($provider, $catalogIngress) {
                        $decision = $catalogIngress->inspect($tenantId, $actorId, $descriptor);
                        $redactedDescriptor = $catalogIngress->validatedDescriptor($decision);
                        $contents = Storage::disk($item->source_disk)->get($item->source_path);
                        $rawAlias = $item;
                        $provider->analyze(new CatalogVisionImage(
                            originalName: $rawAlias->original_name, mimeType: $rawAlias->mime_type,
                            contents: $contents,
                        ));
                    }
                }
                PHP,
        ];

        foreach ($fixtures as $fixture) {
            $rejected = false;
            try {
                self::assertSecureCatalogVisionJob($fixture, $guard);
            } catch (AssertionFailedError) {
                $rejected = true;
            }

            self::assertTrue($rejected, 'Adversarial catalog vision worker must not satisfy the ingress ratchet.');
        }
    }

    #[Test]
    public function catalog_vision_ratchet_rejects_a_second_provider_sink_using_raw_metadata(): void
    {
        $guard = <<<'PHP'
            <?php
            final class CatalogVisionIngressGuard {
                private const DOVRA_SOURCE = 'upload';
                private const CHANNEL = 'catalog_vision_queue';
                public function inspect($tenantId, $actorId, $descriptor) {
                    return $this->ingress->inspect(new DataEnvelope(
                        sourceSystem: self::DOVRA_SOURCE, payload: $descriptor, tenantId: $tenantId,
                        actorId: $actorId, channel: self::CHANNEL, context: [],
                    ));
                }
            }
            PHP;
        $job = <<<'PHP'
            <?php
            final class ProcessCatalogImportItem {
                public function handle($provider, $catalogIngress) {
                    $work = DB::transaction(function () {
                        return ['item' => $item, 'actor_id' => (string) $batch->created_by];
                    });
                    $item = $work['item'];
                    $descriptor = [
                        'filename' => $item->original_name,
                        'mime' => $item->mime_type,
                        'size_bytes' => $item->size_bytes,
                        'sha256' => $item->sha256,
                    ];
                    $decision = $catalogIngress->inspect($this->tenantId, $work['actor_id'], $descriptor);
                    $redactedDescriptor = $catalogIngress->validatedDescriptor($decision);
                    $contents = Storage::disk($item->source_disk)->get($item->source_path);
                    $provider->analyze(new CatalogVisionImage(
                        tenantId: $this->tenantId, itemId: $this->itemId,
                        originalName: $redactedDescriptor['filename'], mimeType: $redactedDescriptor['mime'],
                        contents: $contents,
                    ));
                    $provider->analyze(new CatalogVisionImage(
                        tenantId: $this->tenantId, itemId: $this->itemId,
                        originalName: $item->original_name, mimeType: $item->mime_type,
                        contents: $contents,
                    ));
                }
            }
            PHP;

        try {
            self::assertSecureCatalogVisionJob($job, $guard);
            self::fail('A second catalog provider sink unexpectedly satisfied the ratchet.');
        } catch (AssertionFailedError $exception) {
            self::assertSame(
                'Catalog vision worker must have exactly one provider analyze sink.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function knowledge_indexer_guards_each_resource_before_embedding_and_upsert(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSecureKnowledgeIndexer(
            (string) file_get_contents($root.'/app/Application/Ai/Knowledge/CatalogKnowledgeIndexer.php'),
            (string) file_get_contents($root.'/app/Application/Ai/Knowledge/KnowledgeIngressGuard.php'),
        );
    }

    #[Test]
    public function knowledge_indexer_ratchet_rejects_a_second_embedding_sink_using_raw_content(): void
    {
        $guard = <<<'PHP'
            <?php
            final class KnowledgeIngressGuard {
                private const DOVRA_SOURCE = 'knowledge_ingestion';
                private const CHANNEL = 'knowledge_index_queue';
                public function inspect($tenantId, $payload) {
                    $businessType = $this->businessContext->resolvePolicyBusinessType($tenantId);
                    foreach (['resource_type', 'resource_id', 'visibility'] as $authority) {
                        hash_equals(hash('sha256', $payload[$authority]), hash('sha256', $payload[$authority]));
                    }
                    return $this->ingress->inspect(new DataEnvelope(
                        sourceSystem: self::DOVRA_SOURCE, payload: $payload, tenantId: $tenantId,
                        actorId: null, channel: self::CHANNEL, context: ['business_type' => $businessType],
                    ));
                }
            }
            PHP;
        $indexer = <<<'PHP'
            <?php
            final class CatalogKnowledgeIndexer {
                public function indexTenant($tenantId) {
                    Product::query()->where('tenant_id', $tenantId);
                    Service::query()->where('tenant_id', $tenantId);
                }
                private function store($tenant, $resourceType, $resource, $visibility, $content) {
                    $tenantId = (string) $tenant->getKey();
                    $resourceId = (string) $resource->getKey();
                    $redacted = $this->ingress->inspect($tenantId, [
                        'resource_type' => $resourceType,
                        'resource_id' => $resourceId,
                        'visibility' => $visibility,
                        'content' => $content,
                        'business_name' => $this->plainText((string) $tenant->name),
                    ]);
                    $embedding = $this->embeddings->embed($tenantId, 'knowledge.index.catalog', $redacted['content']);
                    $this->index->upsert(
                        $tenantId, $redacted['resource_type'], $redacted['resource_id'], $redacted['visibility'],
                        $redacted['content'], $embedding->vector, $embedding->model,
                        ['business_name' => $redacted['business_name'], 'input_tokens' => $embedding->inputTokens],
                    );
                    $this->embeddings->embed($tenantId, 'knowledge.index.catalog', $content);
                }
            }
            PHP;

        try {
            self::assertSecureKnowledgeIndexer($indexer, $guard);
            self::fail('A second raw knowledge embedding unexpectedly satisfied the ratchet.');
        } catch (AssertionFailedError $exception) {
            self::assertSame(
                'Knowledge indexer must have exactly one embedding sink.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function semantic_knowledge_retriever_guards_public_and_private_queries_before_sinks(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSecureKnowledgeRetriever(
            (string) file_get_contents($root.'/app/Application/Ai/Knowledge/SemanticKnowledgeRetriever.php'),
            (string) file_get_contents($root.'/app/Application/Ai/Knowledge/KnowledgeQueryIngressGuard.php'),
        );
    }

    #[Test]
    public function semantic_knowledge_ratchet_rejects_additional_embedding_and_index_sinks(): void
    {
        $guard = self::knowledgeQueryGuardFixture();
        $base = <<<'PHP'
            <?php
            final class SemanticKnowledgeRetriever {
                private function search($query, $tenantId, $includePublic, $limit) {
                    $redactedQuery = $tenantId === null
                        ? $this->ingress->inspectPublic($query)
                        : $this->ingress->inspectTenant($query, $tenantId);
                    $embedding = $this->embeddings->embed($billingTenantId, $operation, $redactedQuery);
                    REPLACE_SINK
                    return $this->index->search($embedding->vector, $tenantId, $includePublic, $boundedLimit);
                }
            }
            PHP;
        $fixtures = [
            [
                str_replace('REPLACE_SINK', '$this->embeddings->embed($billingTenantId, $operation, $query);', $base),
                'Semantic retriever must have exactly one embedding sink.',
            ],
            [
                str_replace('REPLACE_SINK', '$this->index->search($embedding->vector, $tenantId, true, 1);', $base),
                'Semantic retriever must have exactly one index search sink.',
            ],
        ];

        foreach ($fixtures as [$source, $message]) {
            try {
                self::assertSecureKnowledgeRetriever($source, $guard);
                self::fail('An additional semantic knowledge sink unexpectedly satisfied the ratchet.');
            } catch (AssertionFailedError $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function semantic_knowledge_ratchet_rejects_guard_bait_raw_provenance_and_post_guard_overwrite(): void
    {
        $base = <<<'PHP'
            <?php
            final class SemanticKnowledgeRetriever {
                private function search($query, $tenantId, $includePublic, $limit) {
                    REPLACE_ASSIGNMENT
                    $embedding = $this->embeddings->embed($billingTenantId, $operation, $redactedQuery);
                    return $this->index->search($embedding->vector, $tenantId, $includePublic, $boundedLimit);
                }
            }
            PHP;
        $fixtures = [
            [
                str_replace('REPLACE_ASSIGNMENT', <<<'PHP'
                    $publicBait = $this->ingress->inspectPublic($query);
                    $privateBait = $this->ingress->inspectTenant($query, $tenantId);
                    $redactedQuery = $query;
                    PHP, $base),
                'Semantic redacted query must come from the exact tenant ternary.',
            ],
            [
                str_replace('REPLACE_ASSIGNMENT', <<<'PHP'
                    $redactedQuery = $tenantId === null
                        ? $this->ingress->inspectPublic($query)
                        : $this->ingress->inspectTenant($query, $tenantId);
                    $redactedQuery = $query;
                    PHP, $base),
                '$redactedQuery must not be overwritten after query ingress.',
            ],
        ];

        $failures = [];
        foreach ($fixtures as [$source]) {
            try {
                self::assertSecureKnowledgeRetriever($source, self::knowledgeQueryGuardFixture());
                $failures[] = 'Raw semantic query provenance unexpectedly satisfied the ratchet.';
            } catch (AssertionFailedError $exception) {
                $failures[] = $exception->getMessage();
            }
        }
        self::assertSame(array_column($fixtures, 1), $failures);
    }

    #[Test]
    public function dovra_rls_contract_cannot_pass_by_missing_tables_or_unregistered_inventory(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root.'/database/migrations/2026_08_01_000006_expand_program11_privacy_tenancy.php');

        foreach ([
            'dovra_privacy_requests',
            'dovra_privacy_request_events',
            'dovra_legal_holds',
            'dovra_data_retention_records',
        ] as $table) {
            self::assertStringContainsString("'{$table}'", $migration);
        }

        self::assertStringContainsString('ALTER TABLE {$identifier} ENABLE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('ALTER TABLE {$identifier} FORCE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString("current_setting('app.tenant_id', true)", $migration);
        self::assertStringContainsString('assertTablesExist', $migration);
        self::assertSame('incomplete', (new DovraIntegrationSeamRegistry)->status('postgres_rls_inventory_registration'));
    }

    #[Test]
    public function application_slice_does_not_reference_forbidden_shared_entry_points_directly(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];
        $forbidden = [
            'SecurityHeaders',
            'CatalogImportController',
            'PlatformAiRegistryController',
            'routes/api.php',
            'routes/web.php',
        ];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Application/Privacy/Dovra')) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach ($forbidden as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)).": {$needle}";
                }
            }
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    private static function assertSecureCatalogVisionJob(string $jobSource, string $guardSource): void
    {
        $guard = self::parsedClass($guardSource, 'CatalogVisionIngressGuard');
        self::assertStringClassConstant($guard, 'DOVRA_SOURCE', 'upload');
        self::assertStringClassConstant($guard, 'CHANNEL', 'catalog_vision_queue');
        $guardMethod = $guard->getMethod('inspect');
        self::assertInstanceOf(ClassMethod::class, $guardMethod);
        $guardCalls = array_values(array_filter(
            self::flattenExecutableNodes($guardMethod->stmts),
            static fn (Node $node): bool => self::isThisReceiverCall($node, 'ingress', 'inspect'),
        ));
        self::assertCount(1, $guardCalls, 'Catalog ingress must call the real Dovra guard exactly once.');
        self::assertInstanceOf(MethodCall::class, $guardCalls[0]);
        self::assertCatalogGuardEnvelope($guardCalls[0]);

        $job = self::parsedClass($jobSource, 'ProcessCatalogImportItem');
        $handle = $job->getMethod('handle');
        self::assertInstanceOf(ClassMethod::class, $handle);
        $nodes = self::flattenExecutableNodes($handle->stmts);
        $inspectIndex = null;
        $storageIndex = null;
        $providerIndex = null;
        $inspect = null;
        $provider = null;
        $descriptorAssignments = [];
        $redactedAssignments = [];
        $inspectionAssignments = [];
        $inspectCalls = [];
        $storageReads = [];
        $providerCalls = [];

        foreach ($nodes as $index => $node) {
            if (self::isVariableReceiverCall($node, 'catalogIngress', 'inspect')) {
                $inspectCalls[] = $node;
                if ($inspectIndex === null) {
                    $inspectIndex = $index;
                    $inspect = $node;
                }
            }
            if (self::isStorageRead($node)) {
                $storageReads[] = $node;
                if ($storageIndex === null) {
                    $storageIndex = $index;
                }
            }
            if (self::isVariableReceiverCall($node, 'provider', 'analyze')) {
                $providerCalls[] = $node;
                if ($providerIndex === null) {
                    $providerIndex = $index;
                    $provider = $node;
                }
            }
            if ($node instanceof Assign && self::isVariable($node->var, 'descriptor')) {
                $descriptorAssignments[] = $node;
            }
            if ($node instanceof Assign && self::isVariable($node->var, 'redactedDescriptor')) {
                $redactedAssignments[] = $node;
            }
            if ($inspectIndex !== null
                && $index > $inspectIndex
                && ($node instanceof Assign || $node instanceof AssignOp)
                && self::isVariable($node->var, 'decision')) {
                self::fail('$decision must not be overwritten after catalog ingress inspection.');
            }
            if (($node instanceof Assign || $node instanceof AssignOp)
                && $node->var instanceof ArrayDimFetch
                && (self::arrayRootIsVariable($node->var, 'descriptor')
                    || self::arrayRootIsVariable($node->var, 'redactedDescriptor'))) {
                self::fail('Catalog descriptors must not be mutated after their closed assignment.');
            }
            if ($node instanceof AssignOp
                && (self::isVariable($node->var, 'descriptor')
                    || self::isVariable($node->var, 'redactedDescriptor'))) {
                self::fail('Catalog descriptors must not be mutated with assignment operators.');
            }
        }
        if ($inspect !== null) {
            foreach ($nodes as $node) {
                if ($node instanceof Assign && $node->expr === $inspect) {
                    $inspectionAssignments[] = $node;
                }
            }
        }

        if (count($inspectCalls) !== 1) {
            self::fail('Catalog vision worker must have exactly one ingress inspect call.');
        }
        if (count($storageReads) !== 1) {
            self::fail('Catalog vision worker must have exactly one storage read.');
        }
        if (count($providerCalls) !== 1) {
            self::fail('Catalog vision worker must have exactly one provider analyze sink.');
        }
        self::assertIsInt($inspectIndex, 'A real $catalogIngress->inspect() call is required.');
        self::assertIsInt($storageIndex, 'A real Storage::disk()->get() call is required.');
        self::assertIsInt($providerIndex, 'A real $provider->analyze() call is required.');
        self::assertLessThan($storageIndex, $inspectIndex, 'Catalog ingress must run before storage reads.');
        self::assertLessThan($providerIndex, $storageIndex, 'Storage read must precede provider analysis.');
        self::assertInstanceOf(MethodCall::class, $inspect);
        self::assertInstanceOf(MethodCall::class, $provider);
        self::assertCount(1, $inspectionAssignments, 'Catalog inspect() must have one parent assignment.');
        self::assertTrue(
            self::isVariable($inspectionAssignments[0]->var, 'decision'),
            'Catalog inspect() must be assigned directly to $decision.',
        );
        self::assertCount(1, $descriptorAssignments, 'The stored descriptor must have one closed assignment.');
        self::assertStoredCatalogDescriptor($descriptorAssignments[0]->expr);
        self::assertCount(1, $redactedAssignments, 'The redacted descriptor must have one assignment.');
        self::assertTrue(self::isValidatedCatalogDescriptorCall($redactedAssignments[0]->expr));
        self::assertCatalogInspectArguments($inspect);
        self::assertCatalogProviderArguments($provider);
        self::assertBatchActorSource($handle->stmts ?? []);
    }

    private static function assertSecureKnowledgeIndexer(string $indexerSource, string $guardSource): void
    {
        $guard = self::parsedClass($guardSource, 'KnowledgeIngressGuard');
        self::assertStringClassConstant($guard, 'DOVRA_SOURCE', 'knowledge_ingestion');
        self::assertStringClassConstant($guard, 'CHANNEL', 'knowledge_index_queue');
        $guardMethod = $guard->getMethod('inspect');
        self::assertInstanceOf(ClassMethod::class, $guardMethod);
        $guardNodes = self::flattenExecutableNodes($guardMethod->stmts);
        $businessCalls = array_values(array_filter(
            $guardNodes,
            static fn (Node $node): bool => self::isThisReceiverCall($node, 'businessContext', 'resolvePolicyBusinessType'),
        ));
        $guardCalls = array_values(array_filter(
            $guardNodes,
            static fn (Node $node): bool => self::isThisReceiverCall($node, 'ingress', 'inspect'),
        ));
        $hashChecks = array_values(array_filter(
            $guardNodes,
            static fn (Node $node): bool => $node instanceof FuncCall
                && $node->name instanceof Name
                && $node->name->toString() === 'hash_equals',
        ));
        self::assertCount(1, $businessCalls, 'Knowledge guard must resolve canonical business context exactly once.');
        self::assertCount(1, $guardCalls, 'Knowledge guard must call the real Dovra ingress exactly once.');
        self::assertCount(1, $hashChecks, 'Knowledge guard must hash-compare authoritative fields.');
        self::assertInstanceOf(MethodCall::class, $guardCalls[0]);
        self::assertKnowledgeEnvelope($guardCalls[0]);

        $indexer = self::parsedClass($indexerSource, 'CatalogKnowledgeIndexer');
        $store = $indexer->getMethod('store');
        self::assertInstanceOf(ClassMethod::class, $store);
        $nodes = self::flattenExecutableNodes($store->stmts);
        $inspectionCalls = [];
        $embeddingCalls = [];
        $upsertCalls = [];
        $inspectionIndex = null;
        $embeddingIndex = null;
        $upsertIndex = null;

        foreach ($nodes as $index => $node) {
            if ($node instanceof MethodCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'inspect') {
                $inspectionCalls[] = $node;
                $inspectionIndex ??= $index;
            }
            if ($node instanceof MethodCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'embed') {
                $embeddingCalls[] = $node;
                $embeddingIndex ??= $index;
            }
            if ($node instanceof MethodCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'upsert') {
                $upsertCalls[] = $node;
                $upsertIndex ??= $index;
            }
        }

        if (count($inspectionCalls) !== 1) {
            self::fail('Knowledge indexer must have exactly one ingress inspection.');
        }
        if (count($embeddingCalls) !== 1) {
            self::fail('Knowledge indexer must have exactly one embedding sink.');
        }
        if (count($upsertCalls) !== 1) {
            self::fail('Knowledge indexer must have exactly one knowledge upsert sink.');
        }
        self::assertIsInt($inspectionIndex);
        self::assertIsInt($embeddingIndex);
        self::assertIsInt($upsertIndex);
        self::assertLessThan($embeddingIndex, $inspectionIndex, 'Knowledge inspection must precede embedding.');
        self::assertLessThan($upsertIndex, $embeddingIndex, 'Embedding must precede knowledge upsert.');
        self::assertInstanceOf(MethodCall::class, $inspectionCalls[0]);
        self::assertInstanceOf(MethodCall::class, $embeddingCalls[0]);
        self::assertInstanceOf(MethodCall::class, $upsertCalls[0]);
        self::assertTrue(self::isThisReceiverCall($inspectionCalls[0], 'ingress', 'inspect'));
        self::assertTrue(self::isThisReceiverCall($embeddingCalls[0], 'embeddings', 'embed'));
        self::assertTrue(self::isThisReceiverCall($upsertCalls[0], 'index', 'upsert'));
        self::assertKnowledgeInspection($inspectionCalls[0], $nodes);
        self::assertKnowledgeEmbedding($embeddingCalls[0]);
        self::assertKnowledgeUpsert($upsertCalls[0]);
        self::assertTenantFilteredKnowledgeQueries($indexer);
    }

    private static function assertSecureKnowledgeRetriever(string $retrieverSource, string $guardSource): void
    {
        $guard = self::parsedClass($guardSource, 'KnowledgeQueryIngressGuard');
        self::assertStringClassConstant($guard, 'PUBLIC_SOURCE', 'directory_guide_chat');
        self::assertStringClassConstant($guard, 'PRIVATE_SOURCE', 'tenant_agent_chat');
        self::assertStringClassConstant($guard, 'CHANNEL', 'knowledge_query_runtime');

        $public = $guard->getMethod('inspectPublic');
        $private = $guard->getMethod('inspectTenant');
        $inspection = $guard->getMethod('inspect');
        self::assertInstanceOf(ClassMethod::class, $public);
        self::assertInstanceOf(ClassMethod::class, $private);
        self::assertInstanceOf(ClassMethod::class, $inspection);
        $publicCalls = self::directThisMethodCalls($public, 'inspect');
        $privateCalls = self::directThisMethodCalls($private, 'inspect');
        self::assertCount(1, $publicCalls);
        self::assertCount(1, $privateCalls);
        self::assertPublicKnowledgeInspection($publicCalls[0]);
        self::assertPrivateKnowledgeInspection($private, $privateCalls[0]);

        $inspectionNodes = self::flattenExecutableNodes($inspection->stmts);
        $ingressCalls = array_values(array_filter(
            $inspectionNodes,
            static fn (Node $node): bool => self::isThisReceiverCall($node, 'ingress', 'inspect'),
        ));
        $bounds = array_values(array_filter(
            $inspectionNodes,
            static fn (Node $node): bool => $node instanceof FuncCall
                && $node->name instanceof Name
                && $node->name->toString() === 'mb_substr',
        ));
        self::assertCount(1, $ingressCalls, 'Query guard must call Dovra ingress exactly once.');
        self::assertCount(1, $bounds, 'Query guard must bound the normalized payload exactly once.');
        self::assertCount(3, $bounds[0]->args);
        self::assertInstanceOf(Int_::class, $bounds[0]->args[2]->value);
        self::assertSame(500, $bounds[0]->args[2]->value->value);
        self::assertInstanceOf(MethodCall::class, $ingressCalls[0]);
        self::assertKnowledgeQueryEnvelope($ingressCalls[0]);

        $retriever = self::parsedClass($retrieverSource, 'SemanticKnowledgeRetriever');
        $search = $retriever->getMethod('search');
        self::assertInstanceOf(ClassMethod::class, $search);
        $nodes = self::flattenExecutableNodes($search->stmts);
        $publicGuards = [];
        $privateGuards = [];
        $embeddingSinks = [];
        $indexSinks = [];
        $publicIndex = null;
        $privateIndex = null;
        $embeddingIndex = null;
        $indexSearchIndex = null;
        $redactedAssignments = [];

        foreach ($nodes as $index => $node) {
            if (($node instanceof Assign || $node instanceof AssignOp)
                && self::isVariable($node->var, 'redactedQuery')) {
                $redactedAssignments[] = [$index, $node];
            }
            if ($node instanceof MethodCall && $node->name instanceof Identifier) {
                $method = $node->name->toString();
                if ($method === 'inspectPublic') {
                    $publicGuards[] = $node;
                    $publicIndex ??= $index;
                } elseif ($method === 'inspectTenant') {
                    $privateGuards[] = $node;
                    $privateIndex ??= $index;
                } elseif ($method === 'embed') {
                    $embeddingSinks[] = $node;
                    $embeddingIndex ??= $index;
                } elseif ($method === 'search') {
                    $indexSinks[] = $node;
                    $indexSearchIndex ??= $index;
                }
            }
        }

        if (count($publicGuards) !== 1 || count($privateGuards) !== 1) {
            self::fail('Semantic retriever must have one public and one private query guard call.');
        }
        if (count($embeddingSinks) !== 1) {
            self::fail('Semantic retriever must have exactly one embedding sink.');
        }
        if (count($indexSinks) !== 1) {
            self::fail('Semantic retriever must have exactly one index search sink.');
        }
        $canonicalAssignments = array_values(array_filter(
            $redactedAssignments,
            static fn (array $entry): bool => $entry[1] instanceof Assign
                && self::isExactSemanticQueryTernary($entry[1]->expr),
        ));
        if (count($canonicalAssignments) !== 1) {
            self::fail('Semantic redacted query must come from the exact tenant ternary.');
        }
        [$canonicalIndex, $canonicalAssignment] = $canonicalAssignments[0];
        foreach ($redactedAssignments as [$assignmentIndex, $assignment]) {
            if ($assignment === $canonicalAssignment) {
                continue;
            }
            if ($assignmentIndex > $canonicalIndex) {
                self::fail('$redactedQuery must not be overwritten after query ingress.');
            }

            self::fail('Semantic redacted query must come from the exact tenant ternary.');
        }
        self::assertIsInt($publicIndex);
        self::assertIsInt($privateIndex);
        self::assertIsInt($embeddingIndex);
        self::assertIsInt($indexSearchIndex);
        self::assertLessThan($embeddingIndex, $publicIndex);
        self::assertLessThan($embeddingIndex, $privateIndex);
        self::assertLessThan($indexSearchIndex, $embeddingIndex);
        self::assertTrue(self::isThisReceiverCall($publicGuards[0], 'ingress', 'inspectPublic'));
        self::assertTrue(self::isThisReceiverCall($privateGuards[0], 'ingress', 'inspectTenant'));
        self::assertTrue(self::isThisReceiverCall($embeddingSinks[0], 'embeddings', 'embed'));
        self::assertTrue(self::isThisReceiverCall($indexSinks[0], 'index', 'search'));
        self::assertSemanticGuardArguments($publicGuards[0], $privateGuards[0]);
        self::assertSemanticEmbeddingArguments($embeddingSinks[0]);
        self::assertSemanticIndexArguments($indexSinks[0]);
    }

    private static function isExactSemanticQueryTernary(Node $node): bool
    {
        if (! $node instanceof Ternary
            || ! $node->cond instanceof Identical
            || ! self::isVariable($node->cond->left, 'tenantId')
            || ! self::isNullConstant($node->cond->right)
            || ! $node->if instanceof MethodCall
            || ! $node->else instanceof MethodCall
            || ! self::isThisReceiverCall($node->if, 'ingress', 'inspectPublic')
            || ! self::isThisReceiverCall($node->else, 'ingress', 'inspectTenant')) {
            return false;
        }

        return count($node->if->args) === 1
            && self::isVariable($node->if->args[0]->value, 'query')
            && count($node->else->args) === 2
            && self::isVariable($node->else->args[0]->value, 'query')
            && self::isVariable($node->else->args[1]->value, 'tenantId');
    }

    private static function isNullConstant(Node $node): bool
    {
        return $node instanceof ConstFetch && strtolower($node->name->toString()) === 'null';
    }

    private static function knowledgeQueryGuardFixture(): string
    {
        return <<<'PHP'
            <?php
            final class KnowledgeQueryIngressGuard {
                private const PUBLIC_SOURCE = 'directory_guide_chat';
                private const PRIVATE_SOURCE = 'tenant_agent_chat';
                private const CHANNEL = 'knowledge_query_runtime';
                public function inspectPublic($query) {
                    return $this->inspect($query, self::PUBLIC_SOURCE, null, []);
                }
                public function inspectTenant($query, $tenantId) {
                    $businessType = $this->businessContext->resolvePolicyBusinessType($tenantId);
                    return $this->inspect($query, self::PRIVATE_SOURCE, $tenantId, ['business_type' => $businessType]);
                }
                private function inspect($query, $source, $tenantId, $context) {
                    $normalized = $this->normalize($query);
                    $normalized = mb_substr($normalized, 0, 500);
                    return $this->ingress->inspect(new DataEnvelope(
                        sourceSystem: $source, payload: ['query' => $normalized], tenantId: $tenantId,
                        actorId: null, channel: self::CHANNEL, context: $context,
                    ));
                }
            }
            PHP;
    }

    /** @return list<MethodCall> */
    private static function directThisMethodCalls(ClassMethod $method, string $name): array
    {
        return array_values(array_filter(
            self::flattenExecutableNodes($method->stmts),
            static fn (Node $node): bool => $node instanceof MethodCall
                && self::isVariable($node->var, 'this')
                && $node->name instanceof Identifier
                && $node->name->toString() === $name,
        ));
    }

    private static function assertPublicKnowledgeInspection(MethodCall $call): void
    {
        self::assertCount(4, $call->args);
        self::assertTrue(self::isVariable($call->args[0]->value, 'query'));
        self::assertTrue(self::isSelfConstant($call->args[1]->value, 'PUBLIC_SOURCE'));
        self::assertInstanceOf(ConstFetch::class, $call->args[2]->value);
        self::assertSame('null', strtolower($call->args[2]->value->name->toString()));
        self::assertInstanceOf(Array_::class, $call->args[3]->value);
        self::assertSame([], $call->args[3]->value->items);
    }

    private static function assertPrivateKnowledgeInspection(ClassMethod $method, MethodCall $call): void
    {
        $resolver = array_values(array_filter(
            self::flattenExecutableNodes($method->stmts),
            static fn (Node $node): bool => self::isThisReceiverCall(
                $node,
                'businessContext',
                'resolvePolicyBusinessType',
            ),
        ));
        self::assertCount(1, $resolver);
        self::assertCount(1, $resolver[0]->args);
        self::assertTrue(self::isVariable($resolver[0]->args[0]->value, 'tenantId'));
        self::assertCount(4, $call->args);
        self::assertTrue(self::isVariable($call->args[0]->value, 'query'));
        self::assertTrue(self::isSelfConstant($call->args[1]->value, 'PRIVATE_SOURCE'));
        self::assertTrue(self::isVariable($call->args[2]->value, 'tenantId'));
        self::assertInstanceOf(Array_::class, $call->args[3]->value);
        $context = self::namedArrayItems($call->args[3]->value);
        self::assertSame(['business_type'], array_keys($context));
        self::assertTrue(self::isVariable($context['business_type'], 'businessType'));
    }

    private static function assertKnowledgeQueryEnvelope(MethodCall $call): void
    {
        self::assertCount(1, $call->args);
        $envelope = $call->args[0]->value;
        self::assertInstanceOf(New_::class, $envelope);
        self::assertInstanceOf(Name::class, $envelope->class);
        self::assertSame('DataEnvelope', $envelope->class->getLast());
        $arguments = self::namedArguments($envelope->args);
        self::assertSame(['sourceSystem', 'payload', 'tenantId', 'actorId', 'channel', 'context'], array_keys($arguments));
        self::assertTrue(self::isVariable($arguments['sourceSystem'], 'source'));
        self::assertInstanceOf(Array_::class, $arguments['payload']);
        $payload = self::namedArrayItems($arguments['payload']);
        self::assertSame(['query'], array_keys($payload));
        self::assertTrue(self::isVariable($payload['query'], 'normalized'));
        self::assertTrue(self::isVariable($arguments['tenantId'], 'tenantId'));
        self::assertInstanceOf(ConstFetch::class, $arguments['actorId']);
        self::assertSame('null', strtolower($arguments['actorId']->name->toString()));
        self::assertTrue(self::isSelfConstant($arguments['channel'], 'CHANNEL'));
        self::assertTrue(self::isVariable($arguments['context'], 'context'));
    }

    private static function assertSemanticGuardArguments(MethodCall $public, MethodCall $private): void
    {
        self::assertCount(1, $public->args);
        self::assertTrue(self::isVariable($public->args[0]->value, 'query'));
        self::assertCount(2, $private->args);
        self::assertTrue(self::isVariable($private->args[0]->value, 'query'));
        self::assertTrue(self::isVariable($private->args[1]->value, 'tenantId'));
    }

    private static function assertSemanticEmbeddingArguments(MethodCall $embedding): void
    {
        self::assertCount(3, $embedding->args);
        self::assertTrue(self::isVariable($embedding->args[0]->value, 'billingTenantId'));
        self::assertTrue(self::isVariable($embedding->args[2]->value, 'redactedQuery'));
    }

    private static function assertSemanticIndexArguments(MethodCall $search): void
    {
        self::assertCount(4, $search->args);
        self::assertTrue(self::isPropertyRead($search->args[0]->value, 'embedding', 'vector'));
        self::assertTrue(self::isVariable($search->args[1]->value, 'tenantId'));
        self::assertTrue(self::isVariable($search->args[2]->value, 'includePublic'));
        self::assertTrue(self::isVariable($search->args[3]->value, 'boundedLimit'));
    }

    private static function assertKnowledgeEnvelope(MethodCall $inspection): void
    {
        self::assertCount(1, $inspection->args);
        $envelope = $inspection->args[0]->value;
        self::assertInstanceOf(New_::class, $envelope);
        self::assertInstanceOf(Name::class, $envelope->class);
        self::assertSame('DataEnvelope', $envelope->class->getLast());
        $arguments = self::namedArguments($envelope->args);
        self::assertSame(['sourceSystem', 'payload', 'tenantId', 'actorId', 'channel', 'context'], array_keys($arguments));
        self::assertTrue(self::isSelfConstant($arguments['sourceSystem'], 'DOVRA_SOURCE'));
        self::assertTrue(self::isVariable($arguments['payload'], 'payload'));
        self::assertTrue(self::isVariable($arguments['tenantId'], 'tenantId'));
        self::assertInstanceOf(ConstFetch::class, $arguments['actorId']);
        self::assertSame('null', strtolower($arguments['actorId']->name->toString()));
        self::assertTrue(self::isSelfConstant($arguments['channel'], 'CHANNEL'));
        self::assertInstanceOf(Array_::class, $arguments['context']);
        self::assertCount(1, $arguments['context']->items);
        $context = $arguments['context']->items[0];
        self::assertInstanceOf(String_::class, $context?->key);
        self::assertSame('business_type', $context->key->value);
        self::assertTrue(self::isVariable($context->value, 'businessType'));
    }

    /** @param list<Node> $nodes */
    private static function assertKnowledgeInspection(MethodCall $inspection, array $nodes): void
    {
        self::assertCount(2, $inspection->args);
        self::assertTrue(self::isVariable($inspection->args[0]->value, 'tenantId'));
        $payload = $inspection->args[1]->value;
        self::assertInstanceOf(Array_::class, $payload);
        $items = self::namedArrayItems($payload);
        self::assertSame(['resource_type', 'resource_id', 'visibility', 'content', 'business_name'], array_keys($items));
        self::assertTrue(self::isVariable($items['resource_type'], 'resourceType'));
        self::assertTrue(self::isVariable($items['resource_id'], 'resourceId'));
        self::assertTrue(self::isVariable($items['visibility'], 'visibility'));
        self::assertTrue(self::isVariable($items['content'], 'content'));
        self::assertInstanceOf(MethodCall::class, $items['business_name']);
        self::assertTrue(
            $items['business_name']->var instanceof Variable
            && $items['business_name']->var->name === 'this'
            && $items['business_name']->name instanceof Identifier
            && $items['business_name']->name->toString() === 'plainText',
        );
        self::assertCount(1, $items['business_name']->args);
        self::assertInstanceOf(StringCast::class, $items['business_name']->args[0]->value);
        self::assertTrue(self::isPropertyRead($items['business_name']->args[0]->value->expr, 'tenant', 'name'));

        $parents = array_values(array_filter(
            $nodes,
            static fn (Node $node): bool => $node instanceof Assign && $node->expr === $inspection,
        ));
        self::assertCount(1, $parents, 'Knowledge inspect() must have one parent assignment.');
        self::assertTrue(self::isVariable($parents[0]->var, 'redacted'));
    }

    private static function assertKnowledgeEmbedding(MethodCall $embedding): void
    {
        self::assertCount(3, $embedding->args);
        self::assertTrue(self::isVariable($embedding->args[0]->value, 'tenantId'));
        self::assertInstanceOf(String_::class, $embedding->args[1]->value);
        self::assertSame('knowledge.index.catalog', $embedding->args[1]->value->value);
        self::assertTrue(self::isArrayRead($embedding->args[2]->value, 'redacted', 'content'));
    }

    private static function assertKnowledgeUpsert(MethodCall $upsert): void
    {
        self::assertCount(8, $upsert->args);
        self::assertTrue(self::isVariable($upsert->args[0]->value, 'tenantId'));
        foreach ([1 => 'resource_type', 2 => 'resource_id', 3 => 'visibility', 4 => 'content'] as $index => $field) {
            self::assertTrue(self::isArrayRead($upsert->args[$index]->value, 'redacted', $field));
        }
        $metadata = $upsert->args[7]->value;
        self::assertInstanceOf(Array_::class, $metadata);
        $items = self::namedArrayItems($metadata);
        self::assertSame(['business_name', 'input_tokens'], array_keys($items));
        self::assertTrue(self::isArrayRead($items['business_name'], 'redacted', 'business_name'));
    }

    private static function assertTenantFilteredKnowledgeQueries(Class_ $indexer): void
    {
        $method = $indexer->getMethod('indexTenant');
        self::assertInstanceOf(ClassMethod::class, $method);
        $matches = array_values(array_filter(
            self::flattenExecutableNodes($method->stmts),
            static fn (Node $node): bool => $node instanceof MethodCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'where'
                && count($node->args) === 2
                && $node->args[0]->value instanceof String_
                && $node->args[0]->value->value === 'tenant_id'
                && self::isVariable($node->args[1]->value, 'tenantId'),
        ));
        self::assertCount(2, $matches, 'Products and services must both retain exact tenant filters.');
    }

    /** @return array<string, Node> */
    private static function namedArrayItems(Array_ $array): array
    {
        $items = [];
        foreach ($array->items as $item) {
            self::assertNotNull($item);
            self::assertInstanceOf(String_::class, $item->key);
            $items[$item->key->value] = $item->value;
        }

        return $items;
    }

    private static function assertCatalogGuardEnvelope(MethodCall $inspection): void
    {
        self::assertCount(1, $inspection->args);
        $envelope = $inspection->args[0]->value;
        self::assertInstanceOf(New_::class, $envelope);
        self::assertInstanceOf(Name::class, $envelope->class);
        self::assertSame('DataEnvelope', $envelope->class->getLast());
        $arguments = self::namedArguments($envelope->args);
        self::assertSame(['sourceSystem', 'payload', 'tenantId', 'actorId', 'channel', 'context'], array_keys($arguments));
        self::assertTrue(self::isSelfConstant($arguments['sourceSystem'], 'DOVRA_SOURCE'));
        self::assertTrue(self::isVariable($arguments['payload'], 'descriptor'));
        self::assertTrue(self::isVariable($arguments['tenantId'], 'tenantId'));
        self::assertTrue(self::isVariable($arguments['actorId'], 'actorId'));
        self::assertTrue(self::isSelfConstant($arguments['channel'], 'CHANNEL'));
        self::assertInstanceOf(Array_::class, $arguments['context']);
        self::assertSame([], $arguments['context']->items);
    }

    private static function assertCatalogInspectArguments(MethodCall $inspect): void
    {
        self::assertCount(3, $inspect->args);
        self::assertTrue(self::isThisProperty($inspect->args[0]->value, 'tenantId'));
        self::assertTrue(self::isArrayRead($inspect->args[1]->value, 'work', 'actor_id'));
        self::assertTrue(self::isVariable($inspect->args[2]->value, 'descriptor'));
    }

    private static function assertStoredCatalogDescriptor(Node $descriptor): void
    {
        self::assertInstanceOf(Array_::class, $descriptor);
        $fields = [];
        foreach ($descriptor->items as $item) {
            self::assertNotNull($item);
            self::assertInstanceOf(String_::class, $item->key);
            self::assertInstanceOf(PropertyFetch::class, $item->value);
            self::assertTrue(self::isVariable($item->value->var, 'item'));
            self::assertInstanceOf(Identifier::class, $item->value->name);
            $fields[$item->key->value] = $item->value->name->toString();
        }
        self::assertSame([
            'filename' => 'original_name',
            'mime' => 'mime_type',
            'size_bytes' => 'size_bytes',
            'sha256' => 'sha256',
        ], $fields);
    }

    private static function assertCatalogProviderArguments(MethodCall $provider): void
    {
        self::assertCount(1, $provider->args);
        $image = $provider->args[0]->value;
        self::assertInstanceOf(New_::class, $image);
        self::assertInstanceOf(Name::class, $image->class);
        self::assertSame('CatalogVisionImage', $image->class->getLast());
        $arguments = self::namedArguments($image->args);
        self::assertSame(['tenantId', 'itemId', 'originalName', 'mimeType', 'contents'], array_keys($arguments));
        self::assertTrue(self::isThisProperty($arguments['tenantId'], 'tenantId'));
        self::assertTrue(self::isThisProperty($arguments['itemId'], 'itemId'));
        self::assertTrue(self::isArrayRead($arguments['originalName'], 'redactedDescriptor', 'filename'));
        self::assertTrue(self::isArrayRead($arguments['mimeType'], 'redactedDescriptor', 'mime'));
        self::assertTrue(self::isVariable($arguments['contents'], 'contents'));
    }

    /** @param list<Node\Stmt> $statements */
    private static function assertBatchActorSource(array $statements): void
    {
        foreach ((new NodeFinder)->findInstanceOf($statements, Array_::class) as $node) {
            foreach ($node->items as $item) {
                if ($item?->key instanceof String_
                    && $item->key->value === 'actor_id'
                    && $item->value instanceof StringCast
                    && self::isPropertyRead($item->value->expr, 'batch', 'created_by')) {
                    return;
                }
            }
        }

        self::fail('Catalog actor_id must come from the same-tenant batch.created_by value.');
    }

    private static function isValidatedCatalogDescriptorCall(Node $node): bool
    {
        return $node instanceof MethodCall
            && self::isVariable($node->var, 'catalogIngress')
            && $node->name instanceof Identifier
            && $node->name->toString() === 'validatedDescriptor'
            && count($node->args) === 1
            && self::isVariable($node->args[0]->value, 'decision');
    }

    private static function isStorageRead(Node $node): bool
    {
        return $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'get'
            && $node->var instanceof StaticCall
            && $node->var->class instanceof Name
            && $node->var->class->getLast() === 'Storage'
            && $node->var->name instanceof Identifier
            && $node->var->name->toString() === 'disk';
    }

    private static function parsedClass(string $source, string $name): Class_
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements);
        $class = (new NodeFinder)->findFirst(
            $statements,
            static fn (Node $node): bool => $node instanceof Class_ && $node->name?->toString() === $name,
        );
        self::assertInstanceOf(Class_::class, $class, "{$name} AST is required.");

        return $class;
    }

    private static function assertStringClassConstant(Class_ $class, string $name, string $expected): void
    {
        $values = [];
        foreach ($class->getConstants() as $declaration) {
            foreach ($declaration->consts as $constant) {
                if ($constant->name->toString() === $name) {
                    self::assertTrue($declaration->isPrivate());
                    $values[] = $constant->value;
                }
            }
        }
        self::assertCount(1, $values);
        self::assertInstanceOf(String_::class, $values[0]);
        self::assertSame($expected, $values[0]->value);
    }

    private static function assertSecureProfileOptimizationMethod(string $source): void
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements, 'Controller source must parse as PHP.');

        $finder = new NodeFinder;
        $controller = $finder->findFirst($statements, static fn (Node $node): bool => $node instanceof Class_
            && $node->name?->toString() === 'AiOptimizationController');
        self::assertInstanceOf(Class_::class, $controller, 'AiOptimizationController AST is required.');
        self::assertPrivateAiSourceConstant($controller);
        $method = $controller->getMethod('optimizeProfile');
        self::assertInstanceOf(ClassMethod::class, $method, 'optimizeProfile AST is required.');

        $nodes = self::flattenExecutableNodes($method->stmts);
        $authorizationIndex = null;
        $inspectionIndex = null;
        $dispatchIndex = null;
        $inspection = null;
        $dispatch = null;
        $redactedAssignments = [];

        foreach ($nodes as $index => $node) {
            if ($authorizationIndex === null && self::isThisReceiverCall($node, 'privateAiAccess', 'allows')) {
                $authorizationIndex = $index;
            }
            if ($inspectionIndex === null && self::isThisReceiverCall($node, 'dovraIngress', 'inspect')) {
                $inspectionIndex = $index;
                $inspection = $node;
            }
            if ($dispatchIndex === null && self::isStaticCall($node, 'GenerateBusinessProfileJob', 'dispatch')) {
                $dispatchIndex = $index;
                $dispatch = $node;
            }
            if ($node instanceof Assign && self::isVariable($node->var, 'redactedPayload')) {
                $redactedAssignments[] = [$index, $node];
            }
            if (($node instanceof Assign || $node instanceof AssignOp)
                && $node->var instanceof ArrayDimFetch
                && self::arrayRootIsVariable($node->var, 'redactedPayload')) {
                self::fail('The redacted payload must not be overwritten from a post-inspection alias.');
            }
        }

        self::assertIsInt($authorizationIndex, 'A real $this->privateAiAccess->allows() call is required.');
        self::assertIsInt($inspectionIndex, 'A real $this->dovraIngress->inspect() call is required.');
        self::assertIsInt($dispatchIndex, 'A real GenerateBusinessProfileJob::dispatch() call is required.');
        self::assertLessThan(
            $inspectionIndex,
            $authorizationIndex,
            'Private AI authorization must precede Dovra inspection.',
        );
        self::assertLessThan(
            $dispatchIndex,
            $inspectionIndex,
            'Dovra inspection must precede profile job dispatch.',
        );
        self::assertInstanceOf(MethodCall::class, $inspection);
        self::assertInstanceOf(StaticCall::class, $dispatch);

        $inspectionAssignments = [];
        foreach ($nodes as $index => $node) {
            if ($node instanceof Assign && $node->expr === $inspection) {
                $inspectionAssignments[] = [$index, $node];
            }
            if ($index > $inspectionIndex
                && ($node instanceof Assign || $node instanceof AssignOp)
                && self::isVariable($node->var, 'decision')) {
                self::fail('$decision must not be overwritten after Dovra inspection.');
            }
            if ($node instanceof AssignOp && self::isVariable($node->var, 'redactedPayload')) {
                self::fail('$redactedPayload must not be mutated with an assignment operator.');
            }
        }
        self::assertCount(1, $inspectionAssignments, 'inspect() must have exactly one parent assignment.');
        [$inspectionAssignmentIndex, $inspectionAssignment] = $inspectionAssignments[0];
        self::assertLessThan($inspectionIndex, $inspectionAssignmentIndex);
        self::assertTrue(
            self::isVariable($inspectionAssignment->var, 'decision'),
            'inspect() must be assigned directly to $decision.',
        );

        self::assertInspectsCanonicalEnvelope($inspection);
        self::assertCount(1, $redactedAssignments, 'Exactly one redacted payload assignment is required.');
        [$redactedAssignmentIndex, $redactedAssignment] = $redactedAssignments[0];
        self::assertGreaterThan($inspectionIndex, $redactedAssignmentIndex);
        self::assertLessThan($dispatchIndex, $redactedAssignmentIndex);
        self::assertTrue(
            self::isDecisionRedactedPayload($redactedAssignment->expr),
            '$redactedPayload must come directly from $decision->redactedPayload.',
        );
        self::assertDispatchUsesOnlyRedactedPayload($dispatch);
        self::assertNoValidatedAliasAfterInspection(
            $nodes,
            $inspectionAssignment,
            $redactedAssignment,
        );

        foreach (array_slice($nodes, $inspectionIndex + 1) as $postInspectionNode) {
            self::assertFalse(
                self::isVariableReceiverCall($postInspectionNode, 'request', 'validated'),
                '$request->validated() must not be called after Dovra inspection.',
            );
        }
    }

    private static function assertPrivateAiSourceConstant(Class_ $controller): void
    {
        $matches = [];
        foreach ($controller->getConstants() as $declaration) {
            foreach ($declaration->consts as $constant) {
                if ($constant->name->toString() === 'DOVRA_SOURCE') {
                    $matches[] = [$declaration, $constant->value];
                }
            }
        }

        self::assertCount(1, $matches, 'Exactly one DOVRA_SOURCE class constant is required.');
        [$declaration, $value] = $matches[0];
        self::assertInstanceOf(ClassConst::class, $declaration);
        self::assertTrue($declaration->isPrivate(), 'DOVRA_SOURCE must remain private.');
        self::assertInstanceOf(String_::class, $value);
        self::assertSame(
            'private_ai_prompt',
            $value->value,
            'DOVRA_SOURCE must equal private_ai_prompt.',
        );
    }

    /**
     * @param  list<Node>  $nodes
     */
    private static function assertNoValidatedAliasAfterInspection(
        array $nodes,
        Assign $inspectionAssignment,
        Assign $redactedAssignment,
    ): void {
        $tainted = ['validated' => true];
        do {
            $changed = false;
            foreach ($nodes as $node) {
                if ((! $node instanceof Assign && ! $node instanceof AssignOp)
                    || ! $node->var instanceof Variable
                    || ! is_string($node->var->name)
                    || $node === $inspectionAssignment
                    || $node === $redactedAssignment) {
                    continue;
                }
                if (! isset($tainted[$node->var->name]) && self::containsTaintedVariable($node->expr, $tainted)) {
                    $tainted[$node->var->name] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        $inspectionEnd = $inspectionAssignment->getEndTokenPos();
        self::assertGreaterThanOrEqual(0, $inspectionEnd, 'AST token positions are required for taint ordering.');
        foreach ($nodes as $node) {
            if (! $node instanceof Variable
                || ! is_string($node->name)
                || ! isset($tainted[$node->name])
                || $node->getStartTokenPos() <= $inspectionEnd) {
                continue;
            }

            self::fail('Raw validated input or alias $'.$node->name.' is used after Dovra inspection.');
        }
    }

    /** @param array<string, true> $tainted */
    private static function containsTaintedVariable(Node $node, array $tainted): bool
    {
        if ($node instanceof Variable && is_string($node->name) && isset($tainted[$node->name])) {
            return true;
        }
        if ($node instanceof FunctionLike) {
            return false;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            if ($child instanceof Node && self::containsTaintedVariable($child, $tainted)) {
                return true;
            }
            if (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node && self::containsTaintedVariable($item, $tainted)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function assertInspectsCanonicalEnvelope(MethodCall $inspection): void
    {
        self::assertCount(1, $inspection->args);
        $envelope = $inspection->args[0]->value;
        self::assertInstanceOf(New_::class, $envelope);
        self::assertInstanceOf(Name::class, $envelope->class);
        self::assertSame('DataEnvelope', $envelope->class->getLast());

        $arguments = self::namedArguments($envelope->args);
        self::assertSame(
            ['sourceSystem', 'payload', 'tenantId', 'actorId', 'channel', 'context'],
            array_keys($arguments),
        );
        self::assertTrue(self::isSelfConstant($arguments['sourceSystem'], 'DOVRA_SOURCE'));
        self::assertTrue(self::isVariable($arguments['payload'], 'validated'));
        self::assertTrue(self::isVariable($arguments['tenantId'], 'tenantId'));
        self::assertTrue(self::isVariable($arguments['actorId'], 'actorId'));
        self::assertInstanceOf(String_::class, $arguments['channel']);
        self::assertSame('private_ai_web', $arguments['channel']->value);
        self::assertTrue(self::isBusinessContextArray($arguments['context']));
    }

    private static function assertDispatchUsesOnlyRedactedPayload(StaticCall $dispatch): void
    {
        $arguments = self::namedArguments($dispatch->args);
        self::assertSame(
            ['businessName', 'description', 'userId', 'tenantId', 'targetAudience', 'tone', 'keywords'],
            array_keys($arguments),
        );

        foreach ([
            'businessName' => 'business_name',
            'description' => 'description',
            'targetAudience' => 'target_audience',
            'tone' => 'tone',
            'keywords' => 'keywords',
        ] as $argument => $key) {
            self::assertTrue(
                self::isArrayRead($arguments[$argument], 'redactedPayload', $key),
                "{$argument} must read the exact redacted payload key.",
            );
        }
        self::assertTrue(self::isVariable($arguments['userId'], 'actorId'));
        self::assertTrue(self::isVariable($arguments['tenantId'], 'tenantId'));
    }

    /** @param list<Arg> $arguments @return array<string, Node\Expr> */
    private static function namedArguments(array $arguments): array
    {
        $named = [];
        foreach ($arguments as $argument) {
            self::assertInstanceOf(Identifier::class, $argument->name, 'All guarded arguments must be named.');
            self::assertFalse($argument->unpack, 'Guarded arguments must not be unpacked.');
            $named[$argument->name->toString()] = $argument->value;
        }

        return $named;
    }

    /** @param array<Node>|null $nodes @return list<Node> */
    private static function flattenExecutableNodes(?array $nodes): array
    {
        $flattened = [];
        foreach ($nodes ?? [] as $node) {
            self::appendExecutableNode($node, $flattened);
        }

        return $flattened;
    }

    /** @param list<Node> $flattened */
    private static function appendExecutableNode(Node $node, array &$flattened): void
    {
        $flattened[] = $node;
        if ($node instanceof FunctionLike) {
            return;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            if ($child instanceof Node) {
                self::appendExecutableNode($child, $flattened);
            } elseif (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node) {
                        self::appendExecutableNode($item, $flattened);
                    }
                }
            }
        }
    }

    private static function isThisReceiverCall(Node $node, string $property, string $method): bool
    {
        return $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === $method
            && $node->var instanceof PropertyFetch
            && $node->var->name instanceof Identifier
            && $node->var->name->toString() === $property
            && self::isVariable($node->var->var, 'this');
    }

    private static function isVariableReceiverCall(Node $node, string $variable, string $method): bool
    {
        return $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === $method
            && self::isVariable($node->var, $variable);
    }

    private static function isStaticCall(Node $node, string $class, string $method): bool
    {
        return $node instanceof StaticCall
            && $node->class instanceof Name
            && $node->class->getLast() === $class
            && $node->name instanceof Identifier
            && $node->name->toString() === $method;
    }

    private static function isVariable(Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === $name;
    }

    private static function isThisProperty(Node $node, string $property): bool
    {
        return self::isPropertyRead($node, 'this', $property);
    }

    private static function isPropertyRead(Node $node, string $variable, string $property): bool
    {
        return $node instanceof PropertyFetch
            && self::isVariable($node->var, $variable)
            && $node->name instanceof Identifier
            && $node->name->toString() === $property;
    }

    private static function isArrayRead(Node $node, string $variable, string $key): bool
    {
        return $node instanceof ArrayDimFetch
            && self::isVariable($node->var, $variable)
            && $node->dim instanceof String_
            && $node->dim->value === $key;
    }

    private static function arrayRootIsVariable(ArrayDimFetch $node, string $variable): bool
    {
        $root = $node->var;
        while ($root instanceof ArrayDimFetch) {
            $root = $root->var;
        }

        return self::isVariable($root, $variable);
    }

    private static function isDecisionRedactedPayload(Node $node): bool
    {
        return $node instanceof PropertyFetch
            && self::isVariable($node->var, 'decision')
            && $node->name instanceof Identifier
            && $node->name->toString() === 'redactedPayload';
    }

    private static function isSelfConstant(Node $node, string $constant): bool
    {
        return $node instanceof ClassConstFetch
            && $node->class instanceof Name
            && strtolower($node->class->toString()) === 'self'
            && $node->name instanceof Identifier
            && $node->name->toString() === $constant;
    }

    private static function isBusinessContextArray(Node $node): bool
    {
        if (! $node instanceof Array_ || count($node->items) !== 1) {
            return false;
        }
        $item = $node->items[0];

        return $item !== null
            && $item->key instanceof String_
            && $item->key->value === 'business_type'
            && self::isVariable($item->value, 'policyBusinessType');
    }
}
