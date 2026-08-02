<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DovraArtifactManifestTest extends TestCase
{
    public function test_manifest_reports_head_dirty_state_locks_artifacts_and_scans(): void
    {
        $head = $this->repositoryHeadOrSkip();
        [$exitCode, $output] = $this->runManifest('--expected-head='.$head);

        self::assertSame(0, $exitCode, $output);

        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('dovra-launch-certification', $payload['manifest']);
        self::assertSame($head, $payload['head']);
        self::assertIsBool($payload['dirty']);
        self::assertIsInt($payload['dirty_path_count']);
        self::assertNotEmpty($payload['lock_files']);
        self::assertNotEmpty($payload['artifacts']);
        self::assertArrayHasKey('brand', $payload['scans']);
        self::assertArrayHasKey('secrets', $payload['scans']);
        self::assertArrayHasKey('unsupported_claims', $payload['scans']);
        self::assertArrayHasKey('strict_failures', $payload['summary']);
        self::assertTrue($payload['summary']['can_certify_exact_artifact']);
    }

    public function test_manifest_fails_when_expected_head_does_not_match(): void
    {
        $this->repositoryHeadOrSkip();
        [$exitCode, $output] = $this->runManifest('--expected-head=0000000000000000000000000000000000000000');

        self::assertSame(2, $exitCode, $output);
        self::assertStringContainsString('"code": "head_mismatch"', $output);
    }

    public function test_manifest_fails_when_claim_scan_finds_unsupported_marketing_language(): void
    {
        $head = $this->repositoryHeadOrSkip();
        $tempPath = tempnam(sys_get_temp_dir(), 'dovra-claims-');
        self::assertNotFalse($tempPath);
        file_put_contents($tempPath, 'This artifact is production-ready and investment ready.');

        try {
            [$exitCode, $output] = $this->runManifest(
                '--expected-head='.$head,
                '--claims-scan-path='.$tempPath,
            );

            self::assertSame(4, $exitCode, $output);
            self::assertStringContainsString('"code": "unsupported_claims"', $output);
            self::assertStringContainsString('production-ready', $output);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_manifest_fails_when_config_declares_an_absent_path(): void
    {
        $tempConfig = tempnam(sys_get_temp_dir(), 'dovra-config-');
        self::assertNotFalse($tempConfig);
        file_put_contents($tempConfig, <<<'PHP'
<?php

return [
    'programs' => [],
    'acceptance' => [],
    'gates' => [],
    'manifest' => [
        'lock_files' => ['missing/lock.json'],
        'artifact_files' => [],
        'scan_roots' => [],
        'claims_scan_paths' => [],
        'unsupported_claim_patterns' => [],
        'secret_patterns' => [],
        'brand_patterns' => [],
    ],
];
PHP
        );

        try {
            [$exitCode, $output] = $this->runManifest(
                '--expected-head='.trim((string) shell_exec('git rev-parse HEAD')),
                '--config='.$tempConfig,
            );

            self::assertSame(8, $exitCode, $output);
            self::assertStringContainsString('"code": "missing_declared_paths"', $output);
            self::assertStringContainsString('missing/lock.json', $output);
        } finally {
            @unlink($tempConfig);
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runManifest(string ...$arguments): array
    {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('scripts/certify-dovra-launch.php'));

        foreach ($arguments as $argument) {
            $command .= ' '.escapeshellarg($argument);
        }

        $command .= ' 2>&1';
        $outputLines = [];
        $exitCode = 0;
        exec($command, $outputLines, $exitCode);

        return [$exitCode, implode(PHP_EOL, $outputLines)];
    }

    private function repositoryHeadOrSkip(): string
    {
        $head = trim((string) shell_exec('git rev-parse HEAD'));

        if ($head === '') {
            self::markTestSkipped('The exact-artifact manifest requires git; the certified PHP image does not include the git executable.');
        }

        return $head;
    }
}
