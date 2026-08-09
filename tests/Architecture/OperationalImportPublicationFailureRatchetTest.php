<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OperationalImportPublicationFailureRatchetTest extends TestCase
{
    #[Test]
    public function operational_import_publication_has_one_redacted_per_item_failure_boundary(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertSecurePublication(
            (string) file_get_contents($root.'/app/Application/Imports/OperationalImportService.php'),
        );
    }

    #[Test]
    public function operational_import_publication_ratchet_rejects_raw_reads_and_second_failure_sinks(): void
    {
        $fixtures = [
            [
                self::fixture('Throwable $exception', '$raw = $exception->getMessage();'),
                'Operational import publication catch must remain unbound.',
            ],
            [
                self::fixture('Throwable', <<<'PHP'
                    $item->forceFill([
                        'status' => CatalogImportStatus::Failed,
                        'error_code' => 'publication_failed',
                        'error_message' => 'Operational import item could not be published.',
                    ])->save();
                    PHP),
                'Operational import publication failure must persist exactly once.',
            ],
        ];

        $failures = [];
        foreach ($fixtures as [$source]) {
            try {
                self::assertSecurePublication($source);
                $failures[] = 'An unsafe operational publication unexpectedly satisfied the ratchet.';
            } catch (AssertionFailedError $exception) {
                $failures[] = $exception->getMessage();
            }
        }

        self::assertSame(array_column($fixtures, 1), $failures);
    }

    private static function assertSecurePublication(string $source): void
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements);
        $class = (new NodeFinder)->findFirst(
            $statements,
            static fn (Node $node): bool => $node instanceof Class_
                && $node->name?->toString() === 'OperationalImportService',
        );
        self::assertInstanceOf(Class_::class, $class);
        $publish = $class->getMethod('publish');
        self::assertInstanceOf(ClassMethod::class, $publish);

        $boundaries = [];
        foreach ((new NodeFinder)->findInstanceOf($publish->stmts ?? [], TryCatch::class) as $tryCatch) {
            $publishCalls = array_values(array_filter(
                self::methodCalls($tryCatch->stmts, 'publishItem'),
                static fn (MethodCall $call): bool => self::isVariable($call->var, 'this'),
            ));
            if ($publishCalls !== []) {
                $boundaries[] = [$tryCatch, $publishCalls];
            }
        }
        self::assertCount(1, $boundaries, 'Operational import publish must have one per-item failure boundary.');
        [$boundary, $publishCalls] = $boundaries[0];
        self::assertCount(1, $publishCalls, 'Each operational item must be published exactly once.');
        self::assertCount(3, $publishCalls[0]->args);
        self::assertTrue(self::isVariable($publishCalls[0]->args[0]->value, 'batch'));
        self::assertTrue(self::isVariable($publishCalls[0]->args[1]->value, 'item'));
        self::assertTrue(self::isVariable($publishCalls[0]->args[2]->value, 'actorId'));

        $successSaves = self::itemForceFillSaves($boundary->stmts);
        self::assertCount(1, $successSaves, 'Operational import publication success must persist exactly once.');
        $success = self::forceFillItems($successSaves[0]);
        self::assertSame(
            ['status', 'aggregate_id', 'product_id', 'published_at', 'error_code', 'error_message'],
            array_keys($success),
        );
        self::assertCatalogStatus($success['status'], 'Published');
        self::assertTrue(self::isNullConstant($success['error_code']));
        self::assertTrue(self::isNullConstant($success['error_message']));

        self::assertCount(1, $boundary->catches);
        $catch = $boundary->catches[0];
        self::assertCount(1, $catch->types);
        self::assertSame('Throwable', $catch->types[0]->getLast());
        if ($catch->var !== null) {
            self::fail('Operational import publication catch must remain unbound.');
        }

        $failureSaves = self::itemForceFillSaves($catch->stmts);
        if (count($failureSaves) !== 1) {
            self::fail('Operational import publication failure must persist exactly once.');
        }
        $failure = self::forceFillItems($failureSaves[0]);
        self::assertSame(['status', 'error_code', 'error_message'], array_keys($failure));
        self::assertCatalogStatus($failure['status'], 'Failed');
        self::assertInstanceOf(String_::class, $failure['error_code']);
        self::assertSame('publication_failed', $failure['error_code']->value);
        self::assertInstanceOf(String_::class, $failure['error_message']);
        self::assertSame('Operational import item could not be published.', $failure['error_message']->value);
    }

    /** @return list<MethodCall> */
    private static function methodCalls(array $statements, string $method): array
    {
        return array_values(array_filter(
            (new NodeFinder)->findInstanceOf($statements, MethodCall::class),
            static fn (MethodCall $call): bool => $call->name instanceof Identifier
                && $call->name->toString() === $method,
        ));
    }

    /** @return list<MethodCall> */
    private static function itemForceFillSaves(array $statements): array
    {
        return array_values(array_filter(
            self::methodCalls($statements, 'save'),
            static fn (MethodCall $save): bool => $save->var instanceof MethodCall
                && $save->var->name instanceof Identifier
                && $save->var->name->toString() === 'forceFill'
                && self::isVariable($save->var->var, 'item'),
        ));
    }

    /** @return array<string, Node> */
    private static function forceFillItems(MethodCall $save): array
    {
        self::assertInstanceOf(MethodCall::class, $save->var);
        self::assertCount(1, $save->var->args);
        self::assertInstanceOf(Array_::class, $save->var->args[0]->value);
        $items = [];
        foreach ($save->var->args[0]->value->items as $item) {
            self::assertNotNull($item);
            self::assertInstanceOf(String_::class, $item->key);
            $items[$item->key->value] = $item->value;
        }

        return $items;
    }

    private static function assertCatalogStatus(Node $node, string $status): void
    {
        self::assertInstanceOf(ClassConstFetch::class, $node);
        self::assertInstanceOf(Name::class, $node->class);
        self::assertSame('CatalogImportStatus', $node->class->getLast());
        self::assertInstanceOf(Identifier::class, $node->name);
        self::assertSame($status, $node->name->toString());
    }

    private static function isVariable(Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === $name;
    }

    private static function isNullConstant(Node $node): bool
    {
        return $node instanceof ConstFetch && strtolower($node->name->toString()) === 'null';
    }

    private static function fixture(string $catch, string $extraCatch): string
    {
        return <<<PHP
            <?php
            final class OperationalImportService {
                public function publish(\$batch, \$item, \$actorId) {
                    try {
                        \$aggregate = \$this->publishItem(\$batch, \$item, \$actorId);
                        \$item->forceFill([
                            'status' => CatalogImportStatus::Published,
                            'aggregate_id' => (string) \$aggregate->getKey(),
                            'product_id' => \$aggregate->getKey(),
                            'published_at' => now(),
                            'error_code' => null,
                            'error_message' => null,
                        ])->save();
                    } catch ({$catch}) {
                        \$item->forceFill([
                            'status' => CatalogImportStatus::Failed,
                            'error_code' => 'publication_failed',
                            'error_message' => 'Operational import item could not be published.',
                        ])->save();
                        {$extraCatch}
                    }
                }
            }
            PHP;
    }
}
