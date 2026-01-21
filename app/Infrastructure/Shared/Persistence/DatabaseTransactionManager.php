<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence;

use App\Application\Shared\Contracts\TransactionManagerInterface;
use Illuminate\Support\Facades\DB;

class DatabaseTransactionManager implements TransactionManagerInterface
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
