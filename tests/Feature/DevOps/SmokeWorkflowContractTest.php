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

    public function test_launch_certification_aggregates_every_required_ci_gate(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('launch-certification:', $workflow);
        foreach ([
            'quality-guard',
            'frontend-integrity',
            'system-tests',
            'auth-security-mysql',
            'postgres-rls',
            'supply-chain',
            'critical-e2e',
            'ai-evaluations',
        ] as $gate) {
            $this->assertStringContainsString("- {$gate}", $workflow);
        }
        $this->assertStringContainsString('if: always()', $workflow);
        $this->assertStringContainsString('test "$result" = "success"', $workflow);
        $this->assertStringContainsString('tests/Feature/AI/GoldenDatasetEvaluationTest.php', $workflow);
        $this->assertStringContainsString('tests/Fixtures/ai/golden-launch-v1.json', $workflow);
    }

    public function test_portable_backend_gate_disables_composer_process_timeout(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Composer\\Config::disableProcessTimeout', $composer['scripts']['test:fast'][0] ?? null);
        $this->assertStringContainsString('phpunit.sqlite.xml', $composer['scripts']['test:fast'][1] ?? '');
    }

    public function test_smoke_workflow_checks_launch_critical_paths(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/smoke-post-deploy.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('HEALTH_PATH="${HEALTH_PATH:-/up}"', $workflow);
        $this->assertStringContainsString('check_not_5xx "/login"', $workflow);
        $this->assertStringContainsString('check_not_5xx "/verify-email"', $workflow);
        $this->assertStringContainsString('SMOKE_REQUIRE_PUBLISHED_FIXTURES: "true"', $workflow);
        $this->assertStringContainsString('secrets.SMOKE_OWNER_PASSWORD', $workflow);
        $this->assertStringContainsString('vars.SMOKE_PRODUCT_MICROSITE_URL', $workflow);
        $this->assertStringContainsString('Post-deploy verification cannot continue', $workflow);

        $fixtures = file_get_contents(base_path('tests/e2e/support/fixtures.ts'));
        $journey = file_get_contents(base_path('tests/e2e/vito-business-os-flow.spec.ts'));
        $this->assertIsString($fixtures);
        $this->assertIsString($journey);
        $this->assertStringContainsString('is required when SMOKE_REQUIRE_PUBLISHED_FIXTURES=true', $fixtures);
        $this->assertStringNotContainsString('http://localhost:8000/t/', $fixtures);
        $this->assertStringContainsString("process.env.E2E_MODE === 'smoke'", $journey);
        $this->assertStringNotContainsString('return 200;', $journey);
        $this->assertStringNotContainsString('|404|pagina no encontrada|Entre a su cuenta', $journey);
    }
}
