<?php

declare(strict_types=1);

namespace App\Application\Automation\Contracts;

use App\Domain\Automation\WorkflowResumeRequest;
use App\Domain\Automation\WorkflowRunResult;
use App\Domain\Automation\WorkflowStartRequest;

interface WorkflowRuntimeInterface
{
    public function start(WorkflowStartRequest $request): WorkflowRunResult;

    public function resume(string $runId, WorkflowResumeRequest $request): WorkflowRunResult;
}
