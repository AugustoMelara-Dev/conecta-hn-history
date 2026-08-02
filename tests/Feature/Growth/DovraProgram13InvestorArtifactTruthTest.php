<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use Tests\TestCase;

final class DovraProgram13InvestorArtifactTruthTest extends TestCase
{
    public function test_program13_investor_artifacts_use_repository_truth_and_explicit_external_input_fields(): void
    {
        $documents = [
            'docs/dovra/investor/dovra-program13/executive-one-pager.md',
            'docs/dovra/investor/dovra-program13/pitch-outline.md',
            'docs/dovra/investor/dovra-program13/demo-script.md',
            'docs/dovra/investor/dovra-program13/financial-model-assumptions.md',
            'docs/dovra/investor/dovra-program13/data-room-index.md',
        ];

        foreach ($documents as $path) {
            self::assertFileExists(base_path($path), $path);

            $contents = (string) file_get_contents(base_path($path));

            self::assertStringContainsString('## Repository-proven facts', $contents, $path);
            self::assertStringContainsString('## External input fields', $contents, $path);
            self::assertStringContainsString('<external-input-required>', $contents, $path);
            self::assertStringNotContainsString('Conecta HN', $contents, $path);
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:ARR|MRR|run-rate|traction|PMF|product-market fit|investor-ready|investor readiness|SOC 2|ISO 27001|HIPAA certified|profitable)\b/i',
                $contents,
                $path,
            );
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:customer logos|signed investors|funding secured|certification received)\b/i',
                $contents,
                $path,
            );
        }
    }
}
