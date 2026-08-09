<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReportingPagesRatchetTest extends TestCase
{
    /** @var list<string> */
    private const PAGES = [
        'ReportsOverview',
        'ReportsShowcase',
        'ReportsSchedule',
        'ReportsLibrary',
        'ReportsCompare',
    ];

    #[Test]
    public function every_reporting_page_has_the_exact_analytics_view_boundary(): void
    {
        foreach (self::PAGES as $page) {
            $class = self::pageClass($page);
            $method = $class->getMethod('canAccess');
            self::assertInstanceOf(ClassMethod::class, $method, "$page must define canAccess().");

            $calls = self::calls($method, 'allows');
            self::assertCount(1, $calls, "$page must use exactly one permission guard call.");
            self::assertCount(4, $calls[0]->args);
            self::assertClassConstant($calls[0]->args[2]->value, 'Capability', 'ANALYTICS_BASIC');
            self::assertClassConstant($calls[0]->args[3]->value, 'Permission', 'ANALYTICS_VIEW');
        }
    }

    #[Test]
    public function showcase_export_has_a_separate_exact_export_boundary_and_redacted_failure(): void
    {
        $export = self::pageClass('ReportsShowcase')->getMethod('export');
        self::assertInstanceOf(ClassMethod::class, $export);

        $calls = self::calls($export, 'allows');
        self::assertCount(1, $calls, 'Report export must have exactly one permission guard call.');
        self::assertClassConstant($calls[0]->args[2]->value, 'Capability', 'ANALYTICS_BASIC');
        self::assertClassConstant($calls[0]->args[3]->value, 'Permission', 'ANALYTICS_EXPORT');

        $source = self::methodSource('ReportsShowcase', $export);
        self::assertStringContainsString("title('No pudimos exportar el reporte.')", $source);
        self::assertStringNotContainsString('getMessage(', $source);
    }

    #[Test]
    public function overview_and_library_read_the_registry_without_enumerating_report_keys(): void
    {
        foreach (['ReportsOverview', 'ReportsLibrary'] as $page) {
            $method = self::pageClass($page)->getMethod('getViewData');
            self::assertInstanceOf(ClassMethod::class, $method);
            self::assertNotEmpty(self::calls($method, $page === 'ReportsOverview' ? 'grouped' : 'all'));

            $cases = array_filter(
                (new NodeFinder)->findInstanceOf($method->stmts ?? [], StaticCall::class),
                static fn (StaticCall $call): bool => $call->name instanceof Identifier
                    && $call->name->toString() === 'cases',
            );
            self::assertCount(0, $cases, "$page must expose registered reports only.");
        }
    }

    #[Test]
    public function schedule_delegates_once_and_never_persists_submitted_identity_directly(): void
    {
        $method = self::pageClass('ReportsSchedule')->getMethod('createSchedule');
        self::assertInstanceOf(ClassMethod::class, $method);
        self::assertCount(1, self::calls($method, 'schedule'));
        $guards = self::calls($method, 'allows');
        self::assertCount(1, $guards, 'Report scheduling must have exactly one export permission guard call.');
        self::assertClassConstant($guards[0]->args[2]->value, 'Capability', 'ANALYTICS_BASIC');
        self::assertClassConstant($guards[0]->args[3]->value, 'Permission', 'ANALYTICS_EXPORT');

        $source = self::methodSource('ReportsSchedule', $method);
        self::assertStringNotContainsString("['tenant_id']", $source);
        self::assertStringNotContainsString("['user_id']", $source);
        self::assertStringNotContainsString('ReportSchedule::create', $source);
        self::assertStringNotContainsString('Mail::', $source);
    }

    private static function pageClass(string $name): Class_
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/app/Filament/App/Pages/Reports/'.$name.'.php');
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements);
        $class = (new NodeFinder)->findFirst(
            $statements,
            static fn (Node $node): bool => $node instanceof Class_ && $node->name?->toString() === $name,
        );
        self::assertInstanceOf(Class_::class, $class);

        return $class;
    }

    /** @return list<MethodCall> */
    private static function calls(ClassMethod $method, string $name): array
    {
        return array_values(array_filter(
            (new NodeFinder)->findInstanceOf($method->stmts ?? [], MethodCall::class),
            static fn (MethodCall $call): bool => $call->name instanceof Identifier && $call->name->toString() === $name,
        ));
    }

    private static function assertClassConstant(Node $node, string $class, string $constant): void
    {
        self::assertInstanceOf(ClassConstFetch::class, $node);
        self::assertInstanceOf(Name::class, $node->class);
        self::assertSame($class, $node->class->getLast());
        self::assertInstanceOf(Identifier::class, $node->name);
        self::assertSame($constant, $node->name->toString());
    }

    private static function methodSource(string $page, ClassMethod $method): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/App/Pages/Reports/'.$page.'.php');

        return implode("\n", array_slice(
            preg_split('/\R/', $source) ?: [],
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));
    }
}
