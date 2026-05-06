<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class OnboardingE2EContractTest extends TestCase
{
    public function test_onboarding_first_run_host_script_exists(): void
    {
        $packageJson = file_get_contents(base_path('package.json'));

        $this->assertIsString($packageJson);
        $this->assertStringContainsString('"test:e2e:onboarding-first-run:host"', $packageJson);
        $this->assertStringContainsString('tests/e2e/vito-business-os-flow.spec.ts', $packageJson);
    }

    public function test_onboarding_e2e_spec_keeps_first_run_wizard_assertions(): void
    {
        $spec = file_get_contents(base_path('tests/e2e/vito-business-os-flow.spec.ts'));

        $this->assertIsString($spec);
        $this->assertStringContainsString("getByTestId('first-run-wizard')", $spec);
        $this->assertStringContainsString('[aria-current="step"]', $spec);
        $this->assertStringContainsString("getByTestId('first-run-open-step')", $spec);
        $this->assertStringContainsString("getByTestId('first-run-open-step-disabled')", $spec);
    }
}

