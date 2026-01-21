<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * TransactionManagerInterface - Application Gateway for Atomic Operations
 *
 * This contract abstracts the underlying database transaction mechanism (e.g., DB::transaction),
 * allowing the Application Layer to enforce atomicity without coupling to Eloquent/SQL.
 * It also enables easy mocking of transaction boundaries in unit tests.
 */
interface TransactionManagerInterface
{
    /**
     * Execute the given operation within a database transaction.
     *
     * @template T
     *
     * @param  \Closure(): T  $operation
     * @return T
     *
     * @throws \Throwable If the operation fails, the transaction is rolled back.
     */
    public function transaction(\Closure $operation): mixed;
}
