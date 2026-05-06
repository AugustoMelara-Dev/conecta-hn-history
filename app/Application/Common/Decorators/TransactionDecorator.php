<?php

declare(strict_types=1);

namespace App\Application\Common\Decorators;

use Illuminate\Support\Facades\DB;

/**
 * TransactionDecorator - Standardized ACID Compliance
 *
 * Wraps the inner handler execution in a Database Transaction.
 *
 * 1. Blind Execution: Does not inspect the command.
 * 2. Auto-Rollback: On any Throwable.
 * 3. Commit: Only on success.
 */
final class TransactionDecorator
{
    public function __construct(
        private object $inner
    ) {}

    public function handle(object $command): mixed
    {
        return DB::transaction(fn () => $this->inner->handle($command));
    }
}
