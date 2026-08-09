<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class EntrypointProductionGuardTest extends TestCase
{
    public function test_entrypoint_keeps_production_guards_fail_closed_by_default(): void
    {
        $entrypointPath = base_path('docker/entrypoint.sh');

        $this->assertFileExists($entrypointPath);

        $contents = file_get_contents($entrypointPath);
        $this->assertIsString($contents);

        $this->assertStringContainsString('${ENFORCE_PRODUCTION_GUARD:-true}', $contents);
        $this->assertStringContainsString('exit 1', $contents);
        $this->assertStringContainsString('config:production-guard', $contents);
        $this->assertStringContainsString('storage:production-guard', $contents);
    }
}
