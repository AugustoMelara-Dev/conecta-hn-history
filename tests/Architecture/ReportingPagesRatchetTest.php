<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\AssertionFailedError;
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
            self::assertPageAccessBoundary($class, $page);
        }
    }

    #[Test]
    public function showcase_export_has_a_separate_exact_export_boundary_and_redacted_failure(): void
    {
        self::assertSecureExport(self::pageClass('ReportsShowcase'));
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
        self::assertSecureSchedule(self::pageClass('ReportsSchedule'));
    }

    #[Test]
    public function reporting_ratchet_rejects_non_vacuous_access_export_and_schedule_bypasses(): void
    {
        $fixtures = [
            [self::accessFixture('ANALYTICS_EXPORT'), 'Reporting canAccess must require Permission::ANALYTICS_VIEW.'],
            [self::exportFixture('$raw = $exception->getMessage();'), 'Report export failure must not read Throwable::getMessage().'],
            [self::scheduleFixture("ReportSchedule::create(['tenant_id' => 'safe']);"), 'Report scheduling must delegate through ReportScheduler without direct model creation.'],
            [self::scheduleFixture('$tenant = $data[\'tenant_id\'];'), 'Report scheduling must not trust submitted tenant_id or user_id.'],
        ];

        $failures = [];
        foreach ($fixtures as [$source]) {
            try {
                $class = self::classFromSource($source);
                match ($class->name?->toString()) {
                    'ReportsOverview' => self::assertPageAccessBoundary($class, 'ReportsOverview'),
                    'ReportsShowcase' => self::assertSecureExport($class),
                    'ReportsSchedule' => self::assertSecureSchedule($class),
                    default => self::fail('Unexpected reporting fixture.'),
                };
                $failures[] = 'Unsafe reporting fixture unexpectedly passed.';
            } catch (AssertionFailedError $exception) {
                $failures[] = $exception->getMessage();
            }
        }

        self::assertSame(array_column($fixtures, 1), $failures);
    }

    private static function assertPageAccessBoundary(Class_ $class, string $page): void
    {
        $method = $class->getMethod('canAccess');
        self::assertInstanceOf(ClassMethod::class, $method, "$page must define canAccess().");
        $calls = self::calls($method, 'allows');
        self::assertCount(1, $calls, "$page must use exactly one permission guard call.");
        self::assertCount(4, $calls[0]->args);
        self::assertClassConstant($calls[0]->args[2]->value, 'Capability', 'ANALYTICS_BASIC');
        if (! self::isClassConstant($calls[0]->args[3]->value, 'Permission', 'ANALYTICS_VIEW')) {
            self::fail('Reporting canAccess must require Permission::ANALYTICS_VIEW.');
        }
    }

    private static function assertSecureExport(Class_ $class): void
    {
        $export = $class->getMethod('export');
        self::assertInstanceOf(ClassMethod::class, $export);
        $calls = self::calls($export, 'allows');
        self::assertCount(1, $calls, 'Report export must have exactly one permission guard call.');
        self::assertClassConstant($calls[0]->args[2]->value, 'Capability', 'ANALYTICS_BASIC');
        self::assertClassConstant($calls[0]->args[3]->value, 'Permission', 'ANALYTICS_EXPORT');
        $titles = self::calls($export, 'title');
        self::assertCount(1, $titles, 'Report export failure must emit exactly one generic notification.');
        self::assertCount(1, $titles[0]->args);
        self::assertInstanceOf(String_::class, $titles[0]->args[0]->value);
        self::assertSame('No pudimos exportar el reporte.', $titles[0]->args[0]->value->value);
        if (self::calls($export, 'getMessage') !== []) {
            self::fail('Report export failure must not read Throwable::getMessage().');
        }
    }

    private static function assertSecureSchedule(Class_ $class): void
    {
        $method = $class->getMethod('createSchedule');
        self::assertInstanceOf(ClassMethod::class, $method);
        self::assertCount(1, self::calls($method, 'schedule'));
        $guards = self::calls($method, 'allows');
        self::assertCount(1, $guards, 'Report scheduling must have exactly one export permission guard call.');
        self::assertClassConstant($guards[0]->args[2]->value, 'Capability', 'ANALYTICS_BASIC');
        self::assertClassConstant($guards[0]->args[3]->value, 'Permission', 'ANALYTICS_EXPORT');

        $directCreates = array_filter(
            (new NodeFinder)->findInstanceOf($method->stmts ?? [], StaticCall::class),
            static fn (StaticCall $call): bool => $call->class instanceof Name
                && $call->class->getLast() === 'ReportSchedule'
                && $call->name instanceof Identifier
                && $call->name->toString() === 'create',
        );
        if ($directCreates !== []) {
            self::fail('Report scheduling must delegate through ReportScheduler without direct model creation.');
        }

        $submittedAuthority = array_filter(
            (new NodeFinder)->findInstanceOf($method->stmts ?? [], ArrayDimFetch::class),
            static fn (ArrayDimFetch $fetch): bool => $fetch->var instanceof Variable
                && $fetch->var->name === 'data'
                && $fetch->dim instanceof String_
                && in_array($fetch->dim->value, ['tenant_id', 'user_id'], true),
        );
        if ($submittedAuthority !== []) {
            self::fail('Report scheduling must not trust submitted tenant_id or user_id.');
        }
    }

    private static function pageClass(string $name): Class_
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/app/Filament/App/Pages/Reports/'.$name.'.php');

        return self::classFromSource($source, $name);
    }

    private static function classFromSource(string $source, ?string $expectedName = null): Class_
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($statements);
        $class = (new NodeFinder)->findFirst(
            $statements,
            static fn (Node $node): bool => $node instanceof Class_
                && ($expectedName === null || $node->name?->toString() === $expectedName),
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

    private static function isClassConstant(Node $node, string $class, string $constant): bool
    {
        return $node instanceof ClassConstFetch
            && $node->class instanceof Name
            && $node->class->getLast() === $class
            && $node->name instanceof Identifier
            && $node->name->toString() === $constant;
    }

    private static function accessFixture(string $permission): string
    {
        return str_replace('__PERMISSION__', $permission, <<<'PHP'
            <?php
            final class ReportsOverview {
                public static function canAccess(): bool {
                    return app(Guard::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::__PERMISSION__);
                }
            }
            PHP);
    }

    private static function exportFixture(string $extra): string
    {
        return str_replace('__EXTRA__', $extra, <<<'PHP'
            <?php
            final class ReportsShowcase {
                public function export(): void {
                    if (! app(Guard::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_EXPORT)) { return; }
                    try { $this->exporter->export(); }
                    catch (Throwable $exception) {
                        Notification::make()->title('No pudimos exportar el reporte.')->danger()->send();
                        __EXTRA__
                    }
                }
            }
            PHP);
    }

    private static function scheduleFixture(string $extra): string
    {
        return str_replace('__EXTRA__', $extra, <<<'PHP'
            <?php
            final class ReportsSchedule {
                public function createSchedule(array $data): void {
                    if (! app(Guard::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_EXPORT)) { return; }
                    app(ReportScheduler::class)->schedule($key, $tenantId, $frequency, $recipients, $format);
                    __EXTRA__
                }
            }
            PHP);
    }
}
