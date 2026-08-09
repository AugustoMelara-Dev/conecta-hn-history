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

    public function test_adaptive_onboarding_smoke_covers_the_complete_authoritative_journey(): void
    {
        $packageJson = file_get_contents(base_path('package.json'));
        $script = file_get_contents(base_path('scripts/qa/onboarding-adaptive-smoke.mjs'));
        $helper = file_get_contents(base_path('scripts/qa/onboarding-adaptive-smoke-helper.php'));

        $this->assertIsString($packageJson);
        $this->assertIsString($script);
        $this->assertIsString($helper);
        $this->assertStringContainsString('node scripts/qa/onboarding-adaptive-smoke.mjs', $packageJson);
        $this->assertStringContainsString('recommended_step_opens', $script);
        $this->assertStringContainsString('authoritative_progress_increases_after_return', $script);
        $this->assertStringContainsString('current_step_advances_after_real_completion', $script);
        $this->assertStringContainsString('first-run-dismiss', $script);
        $this->assertStringContainsString('readiness_persists_after_dismiss', $script);
        $this->assertStringContainsString('dismissal_persists_server_side', $script);
        $this->assertStringContainsString('readiness_reflows_at_360px', $script);
        $this->assertStringContainsString('readiness_supports_200_percent_text_zoom', $script);
        $this->assertStringContainsString("if (\$mode === 'advance')", $helper);
        $this->assertStringContainsString("if (\$mode === 'inspect')", $helper);
    }

    public function test_onboarding_e2e_spec_keeps_first_run_wizard_assertions(): void
    {
        $spec = file_get_contents(base_path('tests/e2e/vito-business-os-flow.spec.ts'));

        $this->assertIsString($spec);
        $this->assertStringContainsString("getByTestId('first-run-wizard')", $spec);
        $this->assertStringContainsString('[aria-current="step"]', $spec);
        $this->assertStringContainsString("getByTestId('first-run-open-step')", $spec);
        $this->assertStringContainsString("getByTestId('first-run-open-step-disabled')", $spec);
        $this->assertStringContainsString("getByTestId('launch-readiness-card')", $spec);
        $this->assertStringContainsString('await expect(firstRunWizard).toBeVisible', $spec);
        $this->assertStringNotContainsString('if (wizardVisible)', $spec);
    }

    public function test_accessibility_gates_fail_on_every_detected_wcag_a_aa_violation(): void
    {
        $sources = [
            (string) file_get_contents(base_path('tests/e2e/accessibility-critical.spec.ts')),
            (string) file_get_contents(base_path('scripts/qa/onboarding-adaptive-smoke.mjs')),
            (string) file_get_contents(base_path('scripts/qa/billing-entitlements-smoke.mjs')),
            (string) file_get_contents(base_path('scripts/qa/uiux-sixth-auth-smoke.mjs')),
        ];

        $this->assertStringContainsString('expect(results.violations', $sources[0]);

        foreach (array_slice($sources, 1) as $source) {
            $this->assertStringContainsString('violations.length === 0', $source);
        }

        foreach ($sources as $source) {
            $this->assertStringNotContainsString("impact === 'serious'", $source);
            $this->assertStringNotContainsString('impact === "serious"', $source);
            $this->assertStringNotContainsString('["serious", "critical"]', $source);
        }

        $criticalSpec = $sources[0];
        $this->assertStringContainsString("document.documentElement.style.fontSize = '200%'", $criticalSpec);
        $this->assertStringContainsString('textZoomOverflow', $criticalSpec);
        $this->assertStringContainsString('relevantInternalOverflows: []', $criticalSpec);
        $this->assertStringContainsString('allInternalOverflows', $criticalSpec);
        $this->assertStringContainsString('const relevantInternalOverflows = allInternalOverflows', $criticalSpec);
    }
}
