<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

interface LockInterface
{
    /**
     * Attempt to acquire the lock immediately.
     *
     * @param  callable|null  $callback  Optional callback to execute while holding lock
     * @return bool True if acquired (and callback executed), false otherwise
     */
    public function get(?callable $callback = null): bool;

    /**
     * Block until lock is acquired or timeout reached.
     *
     * @param  int  $seconds  Maximum seconds to wait
     * @param  callable|null  $callback  Optional callback
     * @return mixed Return value of callback or true
     *
     * @throws \App\Application\Common\Exceptions\LockTimeoutException
     */
    public function block(int $seconds, ?callable $callback = null): mixed;

    /**
     * Release the lock if owned by this instance.
     */
    public function release(): void;
}
