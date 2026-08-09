<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReportingSqlDialectRatchetTest extends TestCase
{
    #[Test]
    public function monthly_reports_delegate_dialect_sql_to_the_fixed_adapter(): void
    {
        foreach (['RevenueByMonthReport', 'MrrArrReport'] as $report) {
            $nodes = self::nodes($report);
            $calls = array_filter(
                (new NodeFinder)->findInstanceOf($nodes, StaticCall::class),
                static fn (StaticCall $call): bool => $call->class instanceof Name
                    && $call->class->getLast() === 'SqlMonthBucket'
                    && $call->name instanceof Identifier
                    && $call->name->toString() === 'expression',
            );
            self::assertCount(1, $calls, "$report must delegate its month SQL exactly once.");

            foreach ((new NodeFinder)->findInstanceOf($nodes, String_::class) as $literal) {
                self::assertDoesNotMatchRegularExpression('/\b(?:TO_CHAR|DATE_FORMAT|strftime)\s*\(/i', $literal->value);
            }
        }
    }

    #[Test]
    public function portable_coalesce_queries_do_not_use_double_quoted_sql_literals(): void
    {
        foreach (['AttributionBySourceReport', 'ChannelPerformanceReport'] as $report) {
            foreach ((new NodeFinder)->findInstanceOf(self::nodes($report), String_::class) as $literal) {
                if (stripos($literal->value, 'COALESCE') !== false) {
                    self::assertStringNotContainsString('"', $literal->value, "$report must bind its COALESCE fallback.");
                }
            }
        }
    }

    #[Test]
    public function canonical_order_reports_cannot_reintroduce_legacy_reporting_columns(): void
    {
        $expectedOrderReports = [
            'AttributionBySourceReport',
            'AvgOrderValueReport',
            'ChannelPerformanceReport',
            'ChurnRateReport',
            'CustomerLtvReport',
            'RefundRateReport',
            'RevenueByMonthReport',
            'TopProductsReport',
        ];
        $discoveredOrderReports = [];

        foreach (glob(dirname(__DIR__, 2).'/app/Application/Reporting/Reports/*Report.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            if (preg_match("/(?:table|join|from)\\(['\"]orders(?:\\s+as\\s+[^'\"]+)?['\"]\\s*[,)]/i", $source) === 1) {
                $discoveredOrderReports[] = pathinfo($path, PATHINFO_FILENAME);
            }
        }
        sort($discoveredOrderReports);
        self::assertSame($expectedOrderReports, $discoveredOrderReports, 'Every registered report that queries orders must remain inside the canonical-schema ratchet owner set.');

        $registeredReports = array_values(array_filter(array_map(
            static fn (New_ $new): ?string => $new->class instanceof Name ? $new->class->getLast() : null,
            (new NodeFinder)->findInstanceOf(self::parse((string) file_get_contents(dirname(__DIR__, 2).'/app/Providers/ReportRegistryServiceProvider.php')), New_::class),
        ), static fn (?string $class): bool => $class !== null && str_ends_with($class, 'Report')));
        sort($registeredReports);
        self::assertCount(25, $registeredReports);
        self::assertSame($expectedOrderReports, array_values(array_intersect($discoveredOrderReports, $registeredReports)), 'The frozen order-report owner set must be registered.');

        foreach ($expectedOrderReports as $report) {
            self::assertSame([], self::legacyOrderSchemaViolations((string) file_get_contents(self::reportPath($report))), $report);
        }
    }

    #[Test]
    public function canonical_order_schema_scanner_rejects_each_legacy_bypass_for_the_targeted_reason(): void
    {
        $fixtures = [
            'total_amount' => "<?php DB::table('orders')->selectRaw('SUM(total_amount)');",
            'orders_channel' => "<?php DB::table('orders')->selectRaw('COALESCE(channel, ?) as channel', ['direct']);",
            'order_items_subtotal' => "<?php DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')->selectRaw('SUM(oi.subtotal)');",
            'subscription_customer_email' => "<?php DB::table('orders')->join('subscription_payments', 'subscription_payments.customer_email', '=', 'orders.customer_email');",
            'legacy_payment_status' => "<?php DB::table('orders as o')->where('o.status', 'paid');",
            'legacy_payment_status_unqualified' => "<?php DB::table('orders')->whereIn('status', ['paid', 'refunded']);",
        ];

        foreach ($fixtures as $expected => $source) {
            self::assertSame([$expected], self::legacyOrderSchemaViolations($source), $expected);
        }
    }

    /** @return list<string> */
    private static function legacyOrderSchemaViolations(string $source): array
    {
        $nodes = self::parse($source);
        $literals = array_map(
            static fn (String_ $literal): string => $literal->value,
            (new NodeFinder)->findInstanceOf($nodes, String_::class),
        );
        $joined = implode("\n", $literals);
        $violations = [];

        if (str_contains($joined, 'total_amount')) {
            $violations[] = 'total_amount';
        }
        if (preg_match('/COALESCE\(\s*(?:orders\.)?channel\b/i', $joined) === 1) {
            $violations[] = 'orders_channel';
        }
        if (str_contains($joined, 'oi.subtotal')) {
            $violations[] = 'order_items_subtotal';
        }
        if (str_contains($joined, 'subscription_payments') && str_contains($joined, 'customer_email')) {
            $violations[] = 'subscription_customer_email';
        }

        foreach ((new NodeFinder)->findInstanceOf($nodes, MethodCall::class) as $call) {
            if (! $call->name instanceof Identifier || ! in_array($call->name->toString(), ['where', 'orWhere', 'whereIn', 'orWhereIn'], true)) {
                continue;
            }
            $column = $call->args[0]->value ?? null;
            if (! $column instanceof String_ || ! preg_match('/^(?:(?:o|orders)\.)?status$/', $column->value)) {
                continue;
            }
            $value = $call->args[1]->value ?? null;
            $paymentStates = $value instanceof String_
                ? [$value->value]
                : array_map(
                    static fn ($item): ?string => $item?->value instanceof String_ ? $item->value->value : null,
                    $value instanceof Array_ ? $value->items : [],
                );
            if (array_intersect(['paid', 'refunded'], $paymentStates) !== []) {
                $violations[] = 'legacy_payment_status'.($column->value === 'status' ? '_unqualified' : '');
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<Node> */
    private static function nodes(string $report): array
    {
        return self::parse((string) file_get_contents(self::reportPath($report)));
    }

    private static function reportPath(string $report): string
    {
        return dirname(__DIR__, 2).'/app/Application/Reporting/Reports/'.$report.'.php';
    }

    /** @return list<Node> */
    private static function parse(string $source): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($nodes);

        return $nodes;
    }
}
