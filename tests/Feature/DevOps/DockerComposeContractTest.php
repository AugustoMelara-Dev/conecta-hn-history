<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DockerComposeContractTest extends TestCase
{
    public function test_docker_compose_is_versioned_with_safe_defaults(): void
    {
        $composePath = base_path('docker-compose.yml');
        $this->assertFileExists($composePath);

        $compose = file_get_contents($composePath);
        $this->assertIsString($compose);

        $this->assertStringContainsString('dockerfile: Dockerfile', $compose);
        $this->assertStringContainsString('APP_DEBUG: "${APP_DEBUG:-false}"', $compose);
        $this->assertStringContainsString('CACHE_STORE: "${CACHE_STORE:-redis}"', $compose);
        $this->assertStringContainsString('QUEUE_CONNECTION: "${QUEUE_CONNECTION:-redis}"', $compose);
        $this->assertStringContainsString('condition: service_healthy', $compose);
    }
}
