<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class SmokeWorkflowContractTest extends TestCase
{
    public function test_smoke_workflow_fails_when_target_url_is_missing(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/smoke-post-deploy.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('No deployment URL detected', $workflow);
        $this->assertStringContainsString('exit 1', $workflow);
    }

    public function test_smoke_workflow_checks_launch_critical_paths(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/smoke-post-deploy.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('HEALTH_PATH="${HEALTH_PATH:-/up}"', $workflow);
        $this->assertStringContainsString('check_not_5xx "/login"', $workflow);
        $this->assertStringContainsString('check_not_5xx "/verify-email"', $workflow);
    }
}

