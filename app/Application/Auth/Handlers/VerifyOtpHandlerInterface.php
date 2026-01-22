<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Results\VerifyOtpResult;

interface VerifyOtpHandlerInterface
{
    public function handle(VerifyOtpCommand $command): VerifyOtpResult;
}
