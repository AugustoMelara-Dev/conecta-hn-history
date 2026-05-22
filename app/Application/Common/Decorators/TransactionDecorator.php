<?php

declare(strict_types=1);

namespace App\Application\Common\Decorators;

use App\Application\Shared\Contracts\TransactionManagerInterface;

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
        private object $inner,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(object $command): mixed
    {
        return $this->transactionManager->transaction(fn () => $this->inner->handle($command));
    }
}
