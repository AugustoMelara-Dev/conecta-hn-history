<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
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

final class ProductMediaCleanupQueueRatchetTest extends TestCase
{
    #[Test]
    public function cleanup_drain_retains_a_stable_failure_contract_and_success_path(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertSecureDrain(
            (string) file_get_contents($root.'/app/Application/Products/ProductMediaCleanupQueue.php'),
        );
    }

    #[Test]
    public function cleanup_drain_ratchet_rejects_raw_exception_reads_and_second_update_sinks(): void
    {
        $fixtures = [
            [
                self::fixture('Throwable $exception', <<<'PHP'
                    $raw = $exception->getMessage();
                    PHP),
                'Product media cleanup catch must remain unbound.',
            ],
            [
                self::fixture('Throwable', <<<'PHP'
                    DB::table('product_media_cleanup_tasks')->where('id', $task->id)->update([
                        'attempts' => $attempts,
                        'available_at' => now(),
                        'last_error' => 'product_media_cleanup_unavailable',
                        'updated_at' => now(),
                    ]);
                    PHP),
                'Product media cleanup failure must update the task exactly once.',
            ],
            [
                self::fixture('Throwable', '', '$repository->update'),
                'Product media cleanup failure update must target the cleanup task by id.',
            ],
            [
                self::fixture(
                    'Throwable',
                    '',
                    "DB::table('foreign_cleanup_tasks')->where('id', \$task->id)->update",
                ),
                'Product media cleanup failure update must target the cleanup task by id.',
            ],
            [
                self::fixture(
                    'Throwable',
                    '',
                    "DB::table('product_media_cleanup_tasks')->where('tenant_id', \$task->tenant_id)->update",
                ),
                'Product media cleanup failure update must target the cleanup task by id.',
            ],
            [
                self::fixture('Throwable', '', null, '$repository->delete'),
                'Product media cleanup success delete must target the cleanup task by id.',
            ],
            [
                self::fixture(
                    'Throwable',
                    '',
                    null,
                    "DB::table('foreign_cleanup_tasks')->where('id', \$task->id)->delete",
                ),
                'Product media cleanup success delete must target the cleanup task by id.',
            ],
            [
                self::fixture(
                    'Throwable',
                    '',
                    null,
                    "DB::table('product_media_cleanup_tasks')->where('tenant_id', \$task->tenant_id)->delete",
                ),
                'Product media cleanup success delete must target the cleanup task by id.',
            ],
        ];

        $failures = [];
        foreach ($fixtures as [$source]) {
            try {
                self::assertSecureDrain($source);
                $failures[] = 'An unsafe cleanup drain unexpectedly satisfied the ratchet.';
            } catch (AssertionFailedError $exception) {
                $failures[] = $exception->getMessage();
            }
        }

        self::assertSame(array_column($fixtures, 1), $failures);
    }

    private static function assertSecureDrain(string $source): void
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements);
        $class = (new NodeFinder)->findFirst(
            $statements,
            static fn (Node $node): bool => $node instanceof Class_
                && $node->name?->toString() === 'ProductMediaCleanupQueue',
        );
        self::assertInstanceOf(Class_::class, $class);
        $drain = $class->getMethod('drain');
        self::assertInstanceOf(ClassMethod::class, $drain);

        $boundaries = [];
        foreach ((new NodeFinder)->findInstanceOf($drain->stmts ?? [], TryCatch::class) as $tryCatch) {
            $ownedPathCalls = self::methodCalls($tryCatch->stmts, 'assertOwnedPath');
            if ($ownedPathCalls !== []) {
                $boundaries[] = [$tryCatch, $ownedPathCalls];
            }
        }
        self::assertCount(1, $boundaries, 'Cleanup drain must have one owned-path failure boundary.');
        [$boundary, $ownedPathCalls] = $boundaries[0];
        self::assertCount(1, $ownedPathCalls, 'Owned path must be asserted exactly once per task.');
        self::assertTrue(self::isThisReceiver($ownedPathCalls[0]));
        self::assertCount(1, self::variableMethodCalls($boundary->stmts, 'filesystem', 'exists'));
        self::assertCount(1, self::variableMethodCalls($boundary->stmts, 'filesystem', 'delete'));
        self::assertCount(1, self::postIncrements($boundary->stmts, 'processed'));

        $deletes = self::methodCalls($boundary->stmts, 'delete');
        self::assertCount(2, $deletes, 'Success must retain one filesystem delete and one task delete sink.');
        $taskDeletes = array_values(array_filter(
            $deletes,
            static fn (MethodCall $call): bool => ! self::isVariable($call->var, 'filesystem'),
        ));
        self::assertCount(1, $taskDeletes, 'Success must delete the cleanup task exactly once.');
        if (! self::isCleanupTaskMutation($taskDeletes[0], 'delete')) {
            self::fail('Product media cleanup success delete must target the cleanup task by id.');
        }
        self::assertCount(1, $boundary->catches);
        $catch = $boundary->catches[0];
        self::assertCount(1, $catch->types);
        self::assertSame('Throwable', $catch->types[0]->getLast());
        if ($catch->var !== null) {
            self::fail('Product media cleanup catch must remain unbound.');
        }

        $updates = self::methodCalls($catch->stmts, 'update');
        if (count($updates) !== 1) {
            self::fail('Product media cleanup failure must update the task exactly once.');
        }
        if (! self::isCleanupTaskMutation($updates[0], 'update')) {
            self::fail('Product media cleanup failure update must target the cleanup task by id.');
        }
        self::assertCount(1, $updates[0]->args);
        self::assertInstanceOf(Array_::class, $updates[0]->args[0]->value);
        self::assertCount(4, $updates[0]->args[0]->value->items);
        $update = self::namedItems($updates[0]->args[0]->value);
        self::assertSame(['attempts', 'available_at', 'last_error', 'updated_at'], array_keys($update));
        self::assertTrue(self::isVariable($update['attempts'], 'attempts'));
        self::assertInstanceOf(String_::class, $update['last_error']);
        self::assertSame('product_media_cleanup_unavailable', $update['last_error']->value);

        $warnings = array_values(array_filter(
            (new NodeFinder)->findInstanceOf($catch->stmts, StaticCall::class),
            static fn (StaticCall $call): bool => $call->class instanceof Name
                && $call->class->getLast() === 'Log'
                && $call->name instanceof Identifier
                && $call->name->toString() === 'warning',
        ));
        self::assertCount(1, $warnings, 'Product media cleanup failure must warn exactly once.');
        self::assertCount(2, $warnings[0]->args);
        self::assertInstanceOf(String_::class, $warnings[0]->args[0]->value);
        self::assertSame('Product media cleanup task failed and will be retried.', $warnings[0]->args[0]->value->value);
        self::assertInstanceOf(Array_::class, $warnings[0]->args[1]->value);
        self::assertCount(5, $warnings[0]->args[1]->value->items);
        $context = self::namedItems($warnings[0]->args[1]->value);
        self::assertSame(['task_id', 'tenant_id', 'disk', 'attempts', 'error_code'], array_keys($context));
        self::assertTrue(self::isTaskProperty($context['task_id'], 'id'));
        self::assertTrue(self::isTaskProperty($context['tenant_id'], 'tenant_id'));
        self::assertTrue(self::isTaskProperty($context['disk'], 'disk'));
        self::assertTrue(self::isVariable($context['attempts'], 'attempts'));
        self::assertInstanceOf(String_::class, $context['error_code']);
        self::assertSame('product_media_cleanup_unavailable', $context['error_code']->value);
        self::assertCount(1, self::postIncrements($catch->stmts, 'failed'));
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
    private static function variableMethodCalls(array $statements, string $variable, string $method): array
    {
        return array_values(array_filter(
            self::methodCalls($statements, $method),
            static fn (MethodCall $call): bool => self::isVariable($call->var, $variable),
        ));
    }

    /** @return list<PostInc> */
    private static function postIncrements(array $statements, string $variable): array
    {
        return array_values(array_filter(
            (new NodeFinder)->findInstanceOf($statements, PostInc::class),
            static fn (PostInc $increment): bool => self::isVariable($increment->var, $variable),
        ));
    }

    /** @return array<string, Node> */
    private static function namedItems(Array_ $array): array
    {
        $items = [];
        foreach ($array->items as $item) {
            self::assertNotNull($item);
            self::assertInstanceOf(String_::class, $item->key);
            $items[$item->key->value] = $item->value;
        }

        return $items;
    }

    private static function isVariable(Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === $name;
    }

    private static function isThisReceiver(MethodCall $call): bool
    {
        return self::isVariable($call->var, 'this');
    }

    private static function isTaskProperty(Node $node, string $property): bool
    {
        return $node instanceof PropertyFetch
            && self::isVariable($node->var, 'task')
            && $node->name instanceof Identifier
            && $node->name->toString() === $property;
    }

    private static function isCleanupTaskMutation(MethodCall $call, string $method): bool
    {
        if (! $call->name instanceof Identifier
            || $call->name->toString() !== $method
            || ! $call->var instanceof MethodCall
            || ! $call->var->name instanceof Identifier
            || $call->var->name->toString() !== 'where'
            || count($call->var->args) !== 2
            || ! $call->var->args[0]->value instanceof String_
            || $call->var->args[0]->value->value !== 'id'
            || ! self::isTaskProperty($call->var->args[1]->value, 'id')
            || ! $call->var->var instanceof StaticCall
            || ! $call->var->var->class instanceof Name
            || $call->var->var->class->getLast() !== 'DB'
            || ! $call->var->var->name instanceof Identifier
            || $call->var->var->name->toString() !== 'table'
            || count($call->var->var->args) !== 1
            || ! $call->var->var->args[0]->value instanceof String_) {
            return false;
        }

        return $call->var->var->args[0]->value->value === 'product_media_cleanup_tasks';
    }

    private static function fixture(
        string $catch,
        string $extraCatch,
        ?string $update = null,
        ?string $successDelete = null,
    ): string {
        $update ??= "DB::table('product_media_cleanup_tasks')->where('id', \$task->id)->update";
        $successDelete ??= "DB::table('product_media_cleanup_tasks')->where('id', \$task->id)->delete";

        return <<<PHP
            <?php
            final class ProductMediaCleanupQueue {
                public function drain() {
                    try {
                        \$this->assertOwnedPath(\$tenantId, \$disk, \$path, \$productId);
                        \$filesystem = Storage::disk(\$disk);
                        if (\$filesystem->exists(\$path)) {
                            \$filesystem->delete(\$path);
                        }
                        {$successDelete}();
                        \$processed++;
                    } catch ({$catch}) {
                        \$attempts = (int) \$task->attempts + 1;
                        {$update}([
                            'attempts' => \$attempts,
                            'available_at' => now(),
                            'last_error' => 'product_media_cleanup_unavailable',
                            'updated_at' => now(),
                        ]);
                        Log::warning('Product media cleanup task failed and will be retried.', [
                            'task_id' => \$task->id,
                            'tenant_id' => \$task->tenant_id,
                            'disk' => \$task->disk,
                            'attempts' => \$attempts,
                            'error_code' => 'product_media_cleanup_unavailable',
                        ]);
                        \$failed++;
                        {$extraCatch}
                    }
                }
            }
            PHP;
    }
}
