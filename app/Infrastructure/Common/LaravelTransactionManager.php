<?php

declare(strict_types=1);

namespace App\Infrastructure\Common;

use App\Application\Common\TransactionManagerInterface;
use Closure;
use Illuminate\Database\DatabaseManager;

final readonly class LaravelTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private DatabaseManager $database
    ) {}

    public function transactional(Closure $callback): mixed
    {
        return $this->database->transaction($callback);
    }
}
