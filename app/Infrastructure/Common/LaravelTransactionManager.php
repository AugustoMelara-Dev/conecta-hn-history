<?php

declare(strict_types=1);

namespace App\Infrastructure\Common;

use App\Application\Shared\Contracts\TransactionManagerInterface;
use Closure;
use Illuminate\Database\DatabaseManager;

/**
 * LaravelTransactionManager - Laravel Database Transaction Adapter
 *
 * FIXED: Method renamed from transactional() to transaction() to match interface.
 * FIXED: Changed from readonly class to final class for PHP 8.2+ compatibility.
 */
final class LaravelTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private readonly DatabaseManager $database
    ) {}

    public function transaction(Closure $operation): mixed
    {
        return $this->database->transaction($operation);
    }
}
