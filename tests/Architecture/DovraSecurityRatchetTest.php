<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DovraSecurityRatchetTest extends TestCase
{
    #[Test]
    public function dovra_security_slice_documents_the_required_shared_authority_seams(): void
    {
        $keys = array_column((new DovraIntegrationSeamRegistry)->all(), 'key');

        self::assertSame([
            'legacy_account_privacy',
            'sensitive_log_scrubber',
            'safe_catalog_image_normalizer',
            'ai_capability_policy',
            'postgres_rls_runtime',
            'sales_export_tenant_guard',
            'audit_context',
        ], $keys);
    }

    #[Test]
    public function application_slice_does_not_reference_forbidden_shared_entry_points_directly(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];
        $forbidden = [
            'SecurityHeaders',
            'CatalogImportController',
            'PlatformAiRegistryController',
            'routes/api.php',
            'routes/web.php',
        ];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Application/Privacy/Dovra')) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach ($forbidden as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)).": {$needle}";
                }
            }
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }
}
