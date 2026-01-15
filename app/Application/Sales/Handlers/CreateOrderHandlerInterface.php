<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Results\CreateOrderResult;

interface CreateOrderHandlerInterface
{
    public function handle(CreateOrderCommand $command): CreateOrderResult;
}
