<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpParser\Node;
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
        $reports = [
            'RevenueByMonthReport',
            'AvgOrderValueReport',
            'AttributionBySourceReport',
            'ChannelPerformanceReport',
            'CustomerLtvReport',
            'TopProductsReport',
            'RefundRateReport',
            'ChurnRateReport',
        ];

        foreach ($reports as $report) {
            $literals = array_map(
                static fn (String_ $literal): string => $literal->value,
                (new NodeFinder)->findInstanceOf(self::nodes($report), String_::class),
            );
            $source = implode("\n", $literals);

            self::assertStringNotContainsString('total_amount', $source, "$report cannot query the removed orders.total_amount column.");
            self::assertStringNotContainsString('orders.channel', $source, "$report cannot query the removed orders.channel column.");
            self::assertDoesNotMatchRegularExpression('/COALESCE\(\s*(?:orders\.)?channel\b/i', $source, "$report cannot read the removed orders.channel column through an unqualified raw expression.");
            self::assertStringNotContainsString('oi.subtotal', $source, "$report cannot query the removed order_items.subtotal column.");
            self::assertFalse(
                str_contains($source, 'subscription_payments') && str_contains($source, 'customer_email'),
                "$report cannot invent subscription customer identity.",
            );
            self::assertDoesNotMatchRegularExpression('/(?<!payment_)status\n(?:paid|refunded)|(?:paid|refunded)\n(?<!payment_)status/i', $source, "$report must use orders.payment_status for payment state.");
        }
    }

    /** @return list<Node> */
    private static function nodes(string $report): array
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Application/Reporting/Reports/'.$report.'.php');
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        self::assertIsArray($nodes);

        return $nodes;
    }
}
