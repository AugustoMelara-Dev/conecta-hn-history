<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use Closure;

interface TransactionManagerInterface
{
    /**
     * Execute a Closure within a database transaction.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed;
}
