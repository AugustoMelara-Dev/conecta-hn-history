<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Results\ReportPaymentResult;

/**
 * ReportPaymentHandlerInterface - Decorator Pattern Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DECORATOR ENABLED: Allows validation layer to wrap concrete handler.   ║
 * ║  DI COMPATIBLE: Enables container binding resolution.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ReportPaymentHandlerInterface
{
    public function handle(ReportPaymentCommand $command): ReportPaymentResult;
}
