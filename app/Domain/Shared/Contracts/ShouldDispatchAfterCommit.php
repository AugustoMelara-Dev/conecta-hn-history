<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Domain marker interface indicating an event should be dispatched
 * only after the current database transaction successfully commits.
 */
interface ShouldDispatchAfterCommit {}
