<?php

declare(strict_types=1);

namespace App\Application\Common;

use Closure;

interface TransactionManagerInterface
{
    /**
     * Execute a callback within a database transaction.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    public function transactional(Closure $callback): mixed;
}
