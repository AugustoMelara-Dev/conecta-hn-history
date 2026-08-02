<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Application\Growth\RepositoryMarketingTruthCatalog;
use App\Domain\Growth\Enums\EvidenceLabel;
use App\Infrastructure\Growth\StaticGrowthFactSource;
use Tests\TestCase;

final class DovraProgram13MarketingTruthTest extends TestCase
{
    public function test_task7_workforce_truth_evidence_is_explicitly_bounded(): void
    {
        $truth = (string) file_get_contents(base_path('docs/dovra/product/dovra-global-ai-workforce-os-truth.md'));
        $evidence = (string) file_get_contents(base_path('docs/dovra/evidence/program-workforce-2026-08-02.md'));

        foreach ([
            '`verified_repository_capability`',
            '`implementation_target`',
            '`external_dependency`',
            '`unsupported_claim`',
        ] as $class) {
            self::assertStringContainsString($class, $truth);
            self::assertStringContainsString($class, $evidence);
        }

        self::assertStringContainsString('Dovra AI', $truth);
        self::assertStringContainsString('Conecta HN → Dovra AI', $truth);
        self::assertStringContainsString('can_certify_exact_artifact', $truth);
        self::assertStringContainsString('vendor blocker', $truth);
        self::assertStringNotContainsString('public production availability is established', $truth);
    }

    public function test_growth_truth_catalog_labels_claims_and_links_existing_evidence(): void
    {
        $catalog = new RepositoryMarketingTruthCatalog(new StaticGrowthFactSource);

        $claims = $catalog->marketingClaims();

        self::assertCount(5, $claims);

        foreach ($claims as $claim) {
            self::assertContains($claim->label, EvidenceLabel::cases());
            self::assertNotSame('', trim($claim->title));
            self::assertNotSame('', trim($claim->statement));
            self::assertNotSame([], $claim->evidencePaths);

            foreach ($claim->evidencePaths as $path) {
                self::assertFileExists(base_path($path), $path);
            }
        }

        self::assertSame('Dovra AI', $claims[0]->title);
        self::assertSame(EvidenceLabel::RepositoryProven, $claims[0]->label);
    }

    public function test_program13_marketing_documents_keep_dovra_brand_and_reject_unsupported_public_claims(): void
    {
        $documents = [
            'docs/dovra/marketing/dovra-program13/positioning.md',
            'docs/dovra/marketing/dovra-program13/content-map.md',
            'docs/dovra/evidence/program-13-2026-08-01.md',
        ];

        foreach ($documents as $path) {
            self::assertFileExists(base_path($path), $path);

            $contents = (string) file_get_contents(base_path($path));

            self::assertStringContainsString('Dovra', $contents, $path);
            self::assertStringNotContainsString('Conecta HN', $contents, $path);
            self::assertStringContainsString('[repository-proven]', $contents, $path);
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:SOC 2|ISO 27001|HIPAA|GDPR certified|certified|ARR|MRR|run-rate|traction|PMF|product-market fit|investor-ready|investor readiness)\b/i',
                $contents,
                $path,
            );
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:all countries|every country|available everywhere|works everywhere|worldwide coverage)\b/i',
                $contents,
                $path,
            );
        }
    }
}
