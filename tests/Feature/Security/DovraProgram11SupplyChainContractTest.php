<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

final class DovraProgram11SupplyChainContractTest extends TestCase
{
    public function test_ci_retains_secret_advisory_and_sbom_gates(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

        self::assertIsString($workflow);
        self::assertStringContainsString('supply-chain:', $workflow);
        self::assertStringContainsString('gitleaks_8.30.1_linux_x64.tar.gz', $workflow);
        self::assertStringContainsString('composer audit --locked --no-interaction', $workflow);
        self::assertStringContainsString('npm audit --omit=dev --audit-level=high', $workflow);
        self::assertStringContainsString('composer CycloneDX:make-sbom', $workflow);
        self::assertStringContainsString('npm sbom --omit=dev --sbom-format=cyclonedx', $workflow);
    }
}
