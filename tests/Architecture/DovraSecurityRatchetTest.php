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
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
