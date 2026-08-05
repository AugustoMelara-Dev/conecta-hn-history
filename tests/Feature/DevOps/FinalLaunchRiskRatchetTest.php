<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class FinalLaunchRiskRatchetTest extends TestCase
{
    public function test_every_container_role_has_a_real_production_dependency_and_health_probe(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
        $healthcheck = (string) file_get_contents(base_path('docker/healthcheck.sh'));

        self::assertArrayHasKey('laravel/horizon', $composer['require']);
        self::assertArrayHasKey('league/flysystem-aws-s3-v3', $composer['require']);
        self::assertStringContainsString('CMD ["/app/docker/healthcheck.sh"]', $dockerfile);
        self::assertStringContainsString('worker)', $healthcheck);
        self::assertStringContainsString('artisan horizon', $healthcheck);
        self::assertStringContainsString('scheduler)', $healthcheck);
        self::assertStringContainsString('artisan schedule:work', $healthcheck);
    }

    public function test_post_deploy_credentials_can_only_reach_an_allowlisted_https_origin(): void
    {
        $workflowPath = base_path('.github/workflows/smoke-post-deploy.yml');
        $workflow = (string) file_get_contents($workflowPath);
        $parsed = Yaml::parseFile($workflowPath);

        self::assertSame('production', $parsed['jobs']['smoke-post-deploy']['environment'] ?? null);
        self::assertStringContainsString("github.ref == 'refs/heads/main'", $workflow);
        self::assertStringContainsString('vars.SMOKE_ALLOWED_HOSTS', $workflow);
        self::assertStringContainsString('url.protocol !== "https:"', $workflow);
        self::assertStringContainsString('allowed.includes(url.host.toLowerCase())', $workflow);
        self::assertStringNotContainsString('secrets.SMOKE_CUSTOMER_PASSWORD', $workflow);
    }

    public function test_deployed_smoke_is_business_read_only_except_for_login(): void
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $script = (string) ($package['scripts']['test:e2e:smoke'] ?? '');
        $smoke = (string) file_get_contents(base_path('tests/e2e/smoke-post-deploy.spec.ts'));

        self::assertStringContainsString('tests/e2e/smoke-post-deploy.spec.ts', $script);
        self::assertStringNotContainsString('vito-business-os-flow.spec.ts', $script);
        self::assertStringContainsString("path !== '/login'", $smoke);
        self::assertStringContainsString('post-login smoke must not mutate business or AI state', $smoke);
        self::assertStringNotContainsString('/registrar-negocio', $smoke);
        self::assertStringNotContainsString('/api/v1/directory/guide/chat', $smoke);
    }

    public function test_postgres_rls_setup_targets_the_github_service_host(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

        self::assertStringContainsString(
            'psql -h 127.0.0.1 -p 5432 -U postgres -d conecta_hn_rls_verification',
            $workflow,
        );
    }
}
