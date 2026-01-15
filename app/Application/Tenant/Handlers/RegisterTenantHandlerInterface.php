<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Results\RegisterTenantResult;

interface RegisterTenantHandlerInterface
{
    public function handle(RegisterTenantCommand $command): RegisterTenantResult;
}
