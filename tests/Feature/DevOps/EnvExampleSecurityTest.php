<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class EnvExampleSecurityTest extends TestCase
{
    public function test_env_example_keeps_debug_disabled_by_default(): void
    {
        $envExamplePath = base_path('.env.example');

        $this->assertFileExists($envExamplePath);

        $contents = file_get_contents($envExamplePath);
        $this->assertIsString($contents);
        $this->assertStringContainsString('APP_NAME="Conecta HN"', $contents);
        $this->assertStringNotContainsString('APP_NAME="Vito Business OS"', $contents);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        $this->assertStringNotContainsString('APP_DEBUG=true', $contents);
        $this->assertStringContainsString('TENANT_PRIVATE_RAG_LLM_ENABLED=false', $contents);
        $this->assertStringNotContainsString('TENANT_PRIVATE_RAG_LLM_ENABLED=true', $contents);
    }
}
