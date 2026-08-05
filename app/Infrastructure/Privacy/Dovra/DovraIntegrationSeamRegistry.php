<?php

declare(strict_types=1);

namespace App\Infrastructure\Privacy\Dovra;

final class DovraIntegrationSeamRegistry
{
    /**
     * @return list<array{key:string,path:string,contract:string,purpose:string,status:string}>
     */
    public function all(): array
    {
        return [
            [
                'key' => 'legacy_account_privacy',
                'path' => 'app/Application/Privacy/AccountPrivacyService.php',
                'contract' => 'export/delete',
                'purpose' => 'Reuse the current subject export/delete authority without editing the shared service.',
                'status' => 'complete',
            ],
            [
                'key' => 'sensitive_log_scrubber',
                'path' => 'app/Infrastructure/Logging/SensitiveDataScrubber.php',
                'contract' => 'scrub',
                'purpose' => 'Keep shared log redaction authority as the source of truth.',
                'status' => 'complete',
            ],
            [
                'key' => 'safe_catalog_image_normalizer',
                'path' => 'app/Infrastructure/Security/SafeCatalogImageNormalizer.php',
                'contract' => 'normalize',
                'purpose' => 'Document the upload normalization boundary used by the existing shared upload path.',
                'status' => 'complete',
            ],
            [
                'key' => 'ai_capability_policy',
                'path' => 'app/Application/Ai/Policies/AiCapabilityPolicy.php',
                'contract' => 'assertExecutionAllowed/assertRequestAllowed',
                'purpose' => 'Document the deny-by-default AI execution boundary that the Dovra policy must feed.',
                'status' => 'complete',
            ],
            [
                'key' => 'postgres_rls_runtime',
                'path' => 'tests/Feature/Tenancy/PostgresRlsRuntimeTest.php',
                'contract' => 'runtime tenant isolation proof',
                'purpose' => 'Reuse the canonical tenant-isolation evidence instead of building a second RLS authority.',
                'status' => 'complete',
            ],
            [
                'key' => 'sales_export_tenant_guard',
                'path' => 'tests/Feature/Api/ReportExportTenantIsolationTest.php',
                'contract' => 'tenant-safe export path guard',
                'purpose' => 'Anchor Dovra export evidence to the existing signed-download and tenant path protections.',
                'status' => 'complete',
            ],
            [
                'key' => 'audit_context',
                'path' => 'app/ValueObjects/AuditContext.php',
                'contract' => 'privacy-safe audit metadata',
                'purpose' => 'Reuse the existing audit metadata rules for actor, IP anonymization, and user agent capture.',
                'status' => 'complete',
            ],
            [
                'key' => 'postgres_rls_inventory_registration',
                'path' => 'app/Infrastructure/Tenancy/PostgresRlsPolicyInventory.php',
                'contract' => 'canonical inventory registration',
                'purpose' => 'The canonical shared RLS inventory cannot be edited in this slice; Dovra tables require explicit follow-up registration.',
                'status' => 'incomplete',
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

    /**
     * @return list<string>|null
     */
    public function seamsForEntrypoint(string $entrypoint): ?array
    {
        $map = [
            'export' => ['legacy_account_privacy', 'sales_export_tenant_guard', 'audit_context', 'postgres_rls_runtime'],
            'upload' => ['safe_catalog_image_normalizer', 'postgres_rls_runtime'],
            'website_import' => ['safe_catalog_image_normalizer', 'postgres_rls_runtime'],
            'private_ai_prompt' => ['ai_capability_policy', 'postgres_rls_runtime'],
            'knowledge_ingestion' => ['ai_capability_policy', 'postgres_rls_runtime'],
            'channel_message' => ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            'directory_guide_chat' => ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            'tenant_agent_chat' => ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            'audit' => ['sensitive_log_scrubber', 'audit_context', 'postgres_rls_runtime'],
            'log' => ['sensitive_log_scrubber', 'audit_context', 'postgres_rls_runtime'],
            'webhook' => ['sensitive_log_scrubber', 'audit_context', 'postgres_rls_runtime'],
            'privacy_request' => ['legacy_account_privacy', 'audit_context', 'postgres_rls_runtime'],
            'retention_job' => ['audit_context', 'postgres_rls_runtime'],
        ];

        if (! array_key_exists($entrypoint, $map)) {
            return null;
        }

        $required = $map[$entrypoint];

        return array_diff($required, $this->keys()) === [] ? $required : null;
    }

    public function entrypointRequiresTenantContext(string $entrypoint): bool
    {
        return in_array($entrypoint, [
            'upload',
            'website_import',
            'private_ai_prompt',
            'knowledge_ingestion',
            'channel_message',
            'tenant_agent_chat',
        ], true);
    }

    public function status(string $key): string
    {
        foreach ($this->all() as $seam) {
            if ($seam['key'] === $key) {
                return $seam['status'];
            }
        }

        return 'unregistered';
    }
}
