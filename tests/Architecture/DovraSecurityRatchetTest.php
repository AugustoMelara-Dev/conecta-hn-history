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
        $registry = new DovraIntegrationSeamRegistry;
        $keys = array_column($registry->all(), 'key');

        self::assertSame([
            'legacy_account_privacy',
            'sensitive_log_scrubber',
            'safe_catalog_image_normalizer',
            'ai_capability_policy',
            'postgres_rls_runtime',
            'sales_export_tenant_guard',
            'audit_context',
            'postgres_rls_inventory_registration',
        ], $keys);

        self::assertSame('incomplete', (new DovraIntegrationSeamRegistry)->status('postgres_rls_inventory_registration'));
        self::assertSame(
            ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            $registry->seamsForEntrypoint('directory_guide_chat'),
        );
        self::assertSame(
            ['ai_capability_policy', 'sensitive_log_scrubber', 'postgres_rls_runtime'],
            $registry->seamsForEntrypoint('tenant_agent_chat'),
        );
        self::assertFalse($registry->entrypointRequiresTenantContext('directory_guide_chat'));
        self::assertTrue($registry->entrypointRequiresTenantContext('tenant_agent_chat'));
    }

    #[Test]
    public function the_two_ai_http_owners_guard_before_runtime(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'app/Http/Controllers/Api/V1/DirectoryGuideChatController.php' => 'public',
            'app/Http/Controllers/Api/V1/TenantAgentChatController.php' => 'private',
        ] as $relativePath => $runtimeMethod) {
            $source = (string) file_get_contents($root.'/'.$relativePath);

            self::assertStringContainsString('DovraDataIngressGuard', $source);
            self::assertStringContainsString('DOVRA_SOURCE', $source);
            self::assertStringContainsString('DataEnvelope', $source);
            self::assertStringContainsString(
                $runtimeMethod === 'public'
                    ? "private const DOVRA_SOURCE = 'directory_guide_chat';"
                    : "private const DOVRA_SOURCE = 'tenant_agent_chat';",
                $source,
            );

            $guardPosition = strpos($source, '->inspect(new DataEnvelope');
            $runtimePosition = strpos($source, 'runtime->'.$runtimeMethod.'(');

            self::assertIsInt($guardPosition);
            self::assertIsInt($runtimePosition);
            self::assertLessThan($runtimePosition, $guardPosition === false ? PHP_INT_MAX : $guardPosition);
            // The inspected envelope intentionally contains the validated request
            // payload. Check only the controller code after the guard's decision
            // handling begins, so this ratchet does not flag that legitimate use.
            $postGuardPosition = strpos($source, '$failure = $this->ingressFailureResponse($decision);', $guardPosition);

            self::assertIsInt($postGuardPosition);
            self::assertStringNotContainsString('$validated', substr($source, $postGuardPosition));
        }
    }

    #[Test]
    public function profile_optimization_authorizes_then_guards_and_dispatches_only_redacted_input(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/app/Http/Controllers/Api/V1/AiOptimizationController.php');

        self::assertStringContainsString('DovraDataIngressGuard', $source);
        self::assertStringContainsString('DovraTenantBusinessContextResolver', $source);
        self::assertStringContainsString("private const DOVRA_SOURCE = 'private_ai_prompt';", $source);

        $authorization = strpos($source, 'privateAiAccess->allows');
        $inspection = strpos($source, '->inspect(new DataEnvelope');
        $dispatch = strpos($source, 'GenerateBusinessProfileJob::dispatch');

        self::assertIsInt($authorization);
        self::assertIsInt($inspection);
        self::assertIsInt($dispatch);
        self::assertLessThan($inspection, $authorization === false ? PHP_INT_MAX : $authorization);
        self::assertLessThan($dispatch, $inspection === false ? PHP_INT_MAX : $inspection);
        $postGuard = strpos($source, '$failure = $this->ingressFailureResponse($decision);', $inspection);

        self::assertIsInt($postGuard);
        self::assertStringNotContainsString('$validated', substr($source, $postGuard === false ? 0 : $postGuard));
    }

    #[Test]
    public function dovra_rls_contract_cannot_pass_by_missing_tables_or_unregistered_inventory(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root.'/database/migrations/2026_08_01_000006_expand_program11_privacy_tenancy.php');

        foreach ([
            'dovra_privacy_requests',
            'dovra_privacy_request_events',
            'dovra_legal_holds',
            'dovra_data_retention_records',
        ] as $table) {
            self::assertStringContainsString("'{$table}'", $migration);
        }

        self::assertStringContainsString('ALTER TABLE {$identifier} ENABLE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('ALTER TABLE {$identifier} FORCE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString("current_setting('app.tenant_id', true)", $migration);
        self::assertStringContainsString('assertTablesExist', $migration);
        self::assertSame('incomplete', (new DovraIntegrationSeamRegistry)->status('postgres_rls_inventory_registration'));
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
