<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class DovraProductTruthContractTest extends TestCase
{
    public function test_canonical_product_identity_is_configured_without_unsupported_clearance_claims(): void
    {
        self::assertSame('Dovra AI', config('dovra.identity.name'));
        self::assertSame('Dovra', config('dovra.identity.short_name'));
        self::assertSame('Global Conversational Business OS', config('dovra.identity.category'));
        self::assertSame(
            'Dovra deploys AI employees that know a business, work through its channels, and turn conversations into measurable results.',
            config('dovra.identity.commercial_promise'),
        );
        self::assertSame('HN', config('dovra.identity.origin_market'));
        self::assertNull(config('dovra.legal.entity_name'));
        self::assertFalse(config('dovra.legal.name_clearance_confirmed'));
    }

    public function test_application_defaults_and_environment_example_use_dovra_product_identity(): void
    {
        $appConfig = (string) file_get_contents(config_path('app.php'));
        $environmentExample = (string) file_get_contents(base_path('.env.example'));

        self::assertStringContainsString("env('APP_NAME', 'Dovra AI')", $appConfig);
        self::assertStringContainsString('APP_NAME="Dovra AI"', $environmentExample);
        self::assertStringContainsString('DOVRA_LEGAL_ENTITY_NAME=', $environmentExample);
        self::assertStringContainsString('DOVRA_NAME_CLEARANCE_CONFIRMED=false', $environmentExample);
        self::assertStringNotContainsString('DOVRA_NAME_CLEARANCE_CONFIRMED=true', $environmentExample);
    }

    public function test_legacy_brand_registry_contains_only_exact_non_public_exceptions(): void
    {
        $entries = config('dovra_legacy.allowed_references');

        self::assertIsArray($entries);
        self::assertNotSame([], $entries);

        $allowedClassifications = [
            'historical_evidence',
            'legal_dependency',
            'operational_compatibility',
        ];
        $registeredPaths = [];

        foreach ($entries as $entry) {
            self::assertIsArray($entry);
            self::assertSame(
                ['path', 'classification', 'reason', 'removal_program'],
                array_keys($entry),
            );
            self::assertIsString($entry['path']);
            self::assertDoesNotMatchRegularExpression('/[*?\[\]]/', $entry['path']);
            self::assertFileExists(base_path($entry['path']));
            self::assertContains($entry['classification'], $allowedClassifications);
            self::assertNotSame('', trim((string) $entry['reason']));
            self::assertMatchesRegularExpression('/^Program (?:0[1-9]|1[0-4])$/', $entry['removal_program']);
            self::assertDoesNotMatchRegularExpression(
                '#^(?:resources/(?:js|views)|app/(?:Mail|Notifications|Http/Controllers|Filament))/#',
                $entry['path'],
            );

            $registeredPaths[] = $entry['path'];
        }

        self::assertSame($registeredPaths, array_values(array_unique($registeredPaths)));
        self::assertContains('config/conecta.php', $registeredPaths);
        self::assertContains('.env.example', $registeredPaths);
        self::assertContains('config/observability.php', $registeredPaths);
        self::assertContains('app/ValueObjects/AuditContext.php', $registeredPaths);
    }

    public function test_server_product_surfaces_have_no_unregistered_legacy_brand_references(): void
    {
        $registeredPaths = array_column(
            (array) config('dovra_legacy.allowed_references', []),
            'path',
        );
        $violations = [];

        foreach ([app_path(), config_path(), resource_path('views')] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace(
                    '\\',
                    '/',
                    substr($file->getPathname(), strlen(base_path()) + 1),
                );

                if (in_array($relativePath, $registeredPaths, true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if (is_string($contents) && preg_match('/Conecta HN|ConectaHN|conecta-hn/ui', $contents) === 1) {
                    $violations[] = $relativePath;
                }
            }
        }

        sort($violations);

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_server_brand_migration_does_not_invent_legal_identity_or_email_privilege(): void
    {
        $billingPage = (string) file_get_contents(app_path('Filament/Pages/Billing.php'));
        $directoryView = (string) file_get_contents(resource_path('views/livewire/pages/directory.blade.php'));

        self::assertStringContainsString('ProductIdentity::legalEntityName()', $billingPage);
        self::assertStringNotContainsString('CONECTA HN S.A. de C.V.', $billingPage);
        self::assertStringContainsString("hasRole('super_admin')", $directoryView);
        self::assertDoesNotMatchRegularExpression(
            '/email\s*===?\s*[\'"][^\'"]+[\'"]/',
            $directoryView,
        );
    }

    public function test_global_server_metadata_has_no_honduras_only_or_unvalidated_domain_fallback(): void
    {
        $seo = (string) file_get_contents(resource_path('views/components/seo-meta.blade.php'));
        $guestLayout = (string) file_get_contents(resource_path('views/layouts/guest.blade.php'));
        $legacyHome = (string) file_get_contents(resource_path('views/livewire/pages/home.blade.php'));

        self::assertStringContainsString("config('dovra.identity.category')", $seo);
        self::assertStringContainsString("config('dovra.identity.commercial_promise')", $seo);
        self::assertStringContainsString('$presentationLocale', $seo);
        self::assertStringContainsString('og:locale:alternate', $seo);
        self::assertStringNotContainsString("config('app.url', 'https://conecta.hn')", $seo);
        self::assertStringNotContainsString("'addressCountry' => 'HN'", $seo);
        self::assertStringNotContainsString('Directorio de negocios en Honduras', $seo);
        self::assertStringNotContainsString('directorio más completo de Honduras', $guestLayout);
        self::assertStringNotContainsString('directorio de negocios más completo de Honduras', $legacyHome);
    }
}
