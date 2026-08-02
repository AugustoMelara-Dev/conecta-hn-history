<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

final class DovraProgram11ThreatModelEvidenceTest extends TestCase
{
    public function test_dovra_security_docs_capture_required_sections_and_do_not_overclaim_compliance(): void
    {
        $threatModel = file_get_contents(base_path('docs/security/dovra-threat-model.md'));
        $classification = file_get_contents(base_path('docs/security/dovra-data-classification.md'));
        $matrix = file_get_contents(base_path('docs/security/dovra-privacy-control-matrix.md'));
        $runbooks = file_get_contents(base_path('docs/security/dovra-incident-runbooks.md'));

        foreach ([$threatModel, $classification, $matrix, $runbooks] as $document) {
            self::assertIsString($document);
            self::assertStringNotContainsString('HIPAA-compliant', $document);
            self::assertStringNotContainsString('GDPR-certified', $document);
            self::assertStringNotContainsString('SOC 2 certified', $document);
            self::assertStringNotContainsString('ISO 27001 certified', $document);
        }

        self::assertStringContainsString('## Assets', $threatModel);
        self::assertStringContainsString('## Trust boundaries', $threatModel);
        self::assertStringContainsString('## Threats and mitigations', $threatModel);
        self::assertStringContainsString('## Residual risk and external review', $threatModel);
        self::assertStringContainsString('Clinics remain scheduling and commercial only.', $classification);
        self::assertStringContainsString('Integration seams', $matrix);
        self::assertStringContainsString('Prohibited data intake', $runbooks);
        self::assertStringContainsString('Supply-chain advisory or secret exposure', $runbooks);
    }
}
