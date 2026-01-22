<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Results\RequestUpgradeResult;

/**
 * RequestUpgradeHandlerInterface - Decorator Pattern Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DECORATOR ENABLED: Allows validation layer to wrap concrete handler.   ║
 * ║  DI COMPATIBLE: Enables container binding resolution.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface RequestUpgradeHandlerInterface
{
    public function handle(RequestUpgradeCommand $command): RequestUpgradeResult;
}
