<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Automation\Contracts\ToolPolicyEngineInterface;
use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use App\Application\Automation\Contracts\WorkflowRuntimeInterface;
use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowRuntime;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use Illuminate\Support\ServiceProvider;

final class AutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            WorkflowRepositoryInterface::class,
            fn (): DatabaseWorkflowRepository => new DatabaseWorkflowRepository($this->app['db']->connection()),
        );
        $this->app->bind(ToolPolicyEngineInterface::class, ToolPolicyEngine::class);
        $this->app->bind(WorkflowRuntimeInterface::class, WorkflowRuntime::class);
    }
}
