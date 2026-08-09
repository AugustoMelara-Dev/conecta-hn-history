<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use App\Application\Reporting\SqlMonthBucket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class SqlMonthBucketTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function dialects(): iterable
    {
        yield 'sqlite' => ['sqlite', "strftime('%Y-%m', created_at)"];
        yield 'mysql' => ['mysql', "DATE_FORMAT(created_at, '%Y-%m')"];
        yield 'postgres' => ['pgsql', "TO_CHAR(created_at, 'YYYY-MM')"];
    }

    #[Test]
    #[DataProvider('dialects')]
    public function it_builds_the_fixed_created_at_month_bucket_for_supported_dialects(string $driver, string $expected): void
    {
        self::assertSame($expected, SqlMonthBucket::expression($driver));
    }

    #[Test]
    public function it_fails_closed_for_unknown_database_drivers(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unsupported reporting SQL driver: sqlsrv');

        SqlMonthBucket::expression('sqlsrv');
    }
}
