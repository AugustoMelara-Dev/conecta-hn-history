<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class ProductClaimsContractTest extends TestCase
{
    public function test_task7_metadata_and_truth_document_use_dovra_and_four_evidence_classes(): void
    {
        $readme = (string) file_get_contents(base_path('README.md'));
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $truth = (string) file_get_contents(base_path('docs/dovra/product/dovra-global-ai-workforce-os-truth.md'));

        self::assertStringStartsWith('# Dovra AI', $readme);
        self::assertStringContainsString('Conecta HN → Dovra AI', $readme);
        self::assertSame('dovra-ai/platform', $composer['name']);
        self::assertStringContainsString('Dovra AI', $composer['description']);
        self::assertSame('dovra-ai', $package['name']);

        foreach ([
            'verified_repository_capability',
            'implementation_target',
            'external_dependency',
            'unsupported_claim',
        ] as $evidenceClass) {
            self::assertStringContainsString($evidenceClass, $truth);
        }
    }

    public function test_task7_public_truth_documents_reject_unsupported_claim_patterns(): void
    {
        /** @var array{manifest: array{unsupported_claim_patterns: list<string>}} $launchManifest */
        $launchManifest = require base_path('config/dovra/launch.php');
        $patterns = $launchManifest['manifest']['unsupported_claim_patterns'];

        foreach ([
            'Dovra AI is publicly live and operational.',
            'Dovra AI is investment-ready and has generated real revenue.',
            'Dovra AI is fully compliant and certified.',
            'Dovra AI has active providers, DNS, and payments.',
            'Dovra AI está lista para producción pública.',
            'Dovra AI cumple con GDPR y está certificada.',
            'Dovra AI tiene tracción y está lista para inversión.',
        ] as $unsupportedClaim) {
            $matched = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $unsupportedClaim) === 1) {
                    $matched = true;
                    break;
                }
            }

            self::assertTrue($matched, $unsupportedClaim);
        }

        foreach ([
            'README.md',
            'docs/dovra/product/dovra-global-ai-workforce-os-truth.md',
            'docs/dovra/evidence/program-workforce-2026-08-02.md',
        ] as $path) {
            $contents = (string) file_get_contents(base_path($path));

            foreach ($patterns as $pattern) {
                self::assertDoesNotMatchRegularExpression($pattern, $contents, $path);
            }
        }
    }

    public function test_task7_certifier_source_exposes_exact_artifact_state_without_readiness_greenwash(): void
    {
        $certifier = (string) file_get_contents(base_path('scripts/certify-dovra-launch.php'));

        foreach ([
            "'head'",
            "'dirty_paths'",
            "'missing_evidence'",
            "'canonical_brand_matches'",
            "'legacy_brand_matches'",
            "'unsupported_claim_matches'",
            "'manifest_failures'",
            "'can_certify_exact_artifact'",
            "'dirty_state_is_not_production_readiness'",
        ] as $field) {
            self::assertStringContainsString($field, $certifier);
        }
    }

    public function test_readme_declares_honest_crm_scope(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertIsString($readme);
        $this->assertStringContainsString('## Alcance CRM actual (honesto)', $readme);
        $this->assertStringContainsString('No debe interpretarse como CRM enterprise completo.', $readme);
        $this->assertStringNotContainsString('CRM completo', $readme);
        $this->assertStringNotContainsString('production-ready', $readme);
        $this->assertStringNotContainsString('listo para producción', $readme);
    }

    public function test_crm_api_contract_declares_non_enterprise_scope(): void
    {
        $contract = file_get_contents(base_path('docs/api/crm-v1.md'));

        $this->assertIsString($contract);
        $this->assertStringContainsString('## Honest scope note', $contract);
        $this->assertStringContainsString('Still not included in this contract:', $contract);
    }
}
