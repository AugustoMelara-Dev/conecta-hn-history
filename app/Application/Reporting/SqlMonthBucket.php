<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use UnexpectedValueException;

final class SqlMonthBucket
{
    public static function expression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'mysql' => "DATE_FORMAT(created_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => throw new UnexpectedValueException("Unsupported reporting SQL driver: {$driver}"),
        };
    }
}
