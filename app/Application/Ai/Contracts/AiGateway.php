<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiExecutionResult;

interface AiGateway
{
    public function execute(AiExecutionRequest $request): AiExecutionResult;
}
