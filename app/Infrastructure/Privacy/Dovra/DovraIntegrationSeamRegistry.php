<?php

declare(strict_types=1);

namespace App\Infrastructure\Privacy\Dovra;

final class DovraIntegrationSeamRegistry
{
    /**
     * @return list<array{key:string,path:string,contract:string,purpose:string}>
     */
    public function all(): array
    {
        return [
            [
                'key' => 'legacy_account_privacy',
                'path' => 'app/Application/Privacy/AccountPrivacyService.php',
                'contract' => 'export/delete',
                'purpose' => 'Reuse the current subject export/delete authority without editing the shared service.',
            ],
            [
                'key' => 'sensitive_log_scrubber',
                'path' => 'app/Infrastructure/Logging/SensitiveDataScrubber.php',
                'contract' => 'scrub',
                'purpose' => 'Keep shared log redaction authority as the source of truth.',
            ],
            [
                'key' => 'safe_catalog_image_normalizer',
                'path' => 'app/Infrastructure/Security/SafeCatalogImageNormalizer.php',
                'contract' => 'normalize',
                'purpose' => 'Document the upload normalization boundary used by the existing shared upload path.',
            ],
            [
                'key' => 'ai_capability_policy',
                'path' => 'app/Application/Ai/Policies/AiCapabilityPolicy.php',
                'contract' => 'assertExecutionAllowed/assertRequestAllowed',
                'purpose' => 'Document the deny-by-default AI execution boundary that the Dovra policy must feed.',
            ],
            [
                'key' => 'postgres_rls_runtime',
                'path' => 'tests/Feature/Tenancy/PostgresRlsRuntimeTest.php',
                'contract' => 'runtime tenant isolation proof',
                'purpose' => 'Reuse the canonical tenant-isolation evidence instead of building a second RLS authority.',
            ],
            [
                'key' => 'sales_export_tenant_guard',
                'path' => 'tests/Feature/Api/ReportExportTenantIsolationTest.php',
                'contract' => 'tenant-safe export path guard',
                'purpose' => 'Anchor Dovra export evidence to the existing signed-download and tenant path protections.',
            ],
            [
                'key' => 'audit_context',
                'path' => 'app/ValueObjects/AuditContext.php',
                'contract' => 'privacy-safe audit metadata',
                'purpose' => 'Reuse the existing audit metadata rules for actor, IP anonymization, and user agent capture.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_column($this->all(), 'key');
    }
}
