<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

use Illuminate\Database\QueryException;

final readonly class AiHandoffUniqueViolation
{
    public function matches(string $driver, QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return match ($driver) {
            'pgsql' => $sqlState === '23505',
            'mysql', 'mariadb' => $driverCode === 1062,
            'sqlite' => $sqlState === '23000'
                && $driverCode === 19
                && str_contains($message, 'unique constraint failed'),
            default => false,
        };
    }
}
