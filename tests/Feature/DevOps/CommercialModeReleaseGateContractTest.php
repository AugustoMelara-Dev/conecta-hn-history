<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class CommercialModeReleaseGateContractTest extends TestCase
{
    public function test_release_gate_checks_launch_critical_paths(): void
    {
        $script = file_get_contents(base_path('scripts/qa/commercial-mode-release-gate.ps1'));

        $this->assertIsString($script);
        $this->assertStringContainsString('Test-ExpectedStatus -Route "/up"', $script);
        $this->assertStringContainsString('Test-ExpectedStatus -Route "/login"', $script);
        $this->assertStringContainsString('Test-ExpectedStatus -Route "/verify-email"', $script);
    }

    public function test_release_gate_enforces_evidence_for_every_launch_mode_without_hybrid_allowlist(): void
    {
        $script = file_get_contents(base_path('scripts/qa/commercial-mode-release-gate.ps1'));

        $this->assertIsString($script);
        $this->assertStringContainsString('"appointments"', $script);
        $this->assertStringContainsString('"products"', $script);
        $this->assertStringContainsString('"consulting_leads"', $script);
        $this->assertStringContainsString('"hybrid"', $script);
        $this->assertStringContainsString('[string]$Modes = "appointments,products,consulting_leads,hybrid"', $script);
        $this->assertStringNotContainsString('HybridAllowlist', $script);
        $this->assertStringNotContainsString('tenant-allowlisted', $script);
    }
}
