<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class LaunchProductTruthContractTest extends TestCase
{
    public function test_user_facing_application_sources_use_conecta_hn_brand(): void
    {
        $violations = [];

        foreach ([base_path('app'), resource_path('js'), resource_path('views')] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()
                    || ! in_array($file->getExtension(), ['php', 'js', 'jsx', 'ts', 'tsx'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if (! is_string($contents)) {
                    continue;
                }

                $contents = preg_replace(
                    "/'vito'\s*,\s*\/\/ Legacy brand protection/i",
                    '',
                    $contents,
                ) ?? $contents;

                if (preg_match('/vito/ui', $contents) === 1) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $viteConfig = (string) file_get_contents(base_path('vite.config.js'));
        if (preg_match('/vito/ui', $viteConfig) === 1) {
            $violations[] = base_path('vite.config.js');
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_hybrid_launch_has_no_pilot_or_allowlist_runtime_gate(): void
    {
        $sources = implode(PHP_EOL, array_map(
            static fn (string $path): string => (string) file_get_contents(base_path($path)),
            [
                'app/Http/Controllers/Auth/RegisterTenantController.php',
                'app/Http/Requests/Auth/RegisterTenantRequest.php',
                'app/Support/Product/TenantAccessMatrix.php',
                'app/Infrastructure/TenantGovernance/Capabilities/ConfigCapabilityManager.php',
                'config/conecta.php',
                'scripts/qa/commercial-mode-release-gate.ps1',
            ],
        ));

        self::assertStringNotContainsString('Piloto controlado', $sources);
        self::assertStringNotContainsString('piloto controlado', $sources);
        self::assertStringNotContainsString('hybrid_commercial_mode_tenants', $sources);
        self::assertStringNotContainsString('HybridAllowlist', $sources);
    }

    public function test_active_plan_surfaces_do_not_embed_conflicting_pro_prices(): void
    {
        $sources = implode(PHP_EOL, array_map(
            static fn (string $path): string => (string) file_get_contents(base_path($path)),
            [
                'resources/js/Pages/Welcome.jsx',
                'resources/js/Components/Auth/RegisterTenantWizard.jsx',
                'resources/views/filament/app/pages/manage-marketing.blade.php',
                'app/Filament/App/Pages/ManageBilling.php',
                'app/Filament/App/Pages/ManageMarketing.php',
            ],
        ));

        self::assertDoesNotMatchRegularExpression('/L\.?\s*(?:99|499)(?:\.00)?(?:\/mes)?/i', $sources);
        self::assertStringContainsString('buildPlans(proPlanPrice)', $sources);
        self::assertStringContainsString('$formattedProPrice', $sources);
    }

    public function test_missing_canonical_pro_price_fails_closed(): void
    {
        config()->set('subscriptions.plans.pro.price', null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('minor currency units');

        \App\Domain\TenantGovernance\Services\SubscriptionPricingService::canonicalProPriceInMajorUnits();
    }
}
