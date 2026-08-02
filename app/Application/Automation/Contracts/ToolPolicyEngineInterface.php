<?php

declare(strict_types=1);

namespace App\Application\Automation\Contracts;

use App\Domain\Automation\ToolAuthorizationDecision;
use App\Domain\Automation\ToolAuthorizationRequest;

interface ToolPolicyEngineInterface
{
    public function authorize(ToolAuthorizationRequest $request): ToolAuthorizationDecision;
}
