<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AiProviderAuthorityRatchetTest extends TestCase
{
    public function test_runtime_selection_cannot_choose_providers_models_or_prompts_from_raw_config_outside_bootstrap(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = [
            $root.'/app/Application',
            $root.'/app/Http',
            $root.'/app/Infrastructure',
        ];
        $allowed = [
            'app/Application/Ai/AiExecutionContextFactory.php',
            'app/Application/Ai/Conversation/ConversationRuntime.php',
            'app/Application/Ai/Knowledge/SemanticKnowledgeRetriever.php',
            'app/Application/Ai/Observability/AiFoundationHealth.php',
            'app/Application/Ai/Policies/AiCapabilityPolicy.php',
            'app/Application/Ai/PublicGroundedDiscovery.php',
            'app/Application/Ai/Registry/AiRegistryBackfill.php',
            'app/Application/Ai/Registry/AiRegistryLifecycle.php',
            'app/Console/Commands/ProductionConfigGuardCommand.php',
            'app/Http/Controllers/Api/V1/PlatformAiEvaluationController.php',
            'app/Http/Controllers/Api/V1/PlatformAiRegistryController.php',
            'app/Http/Middleware/EnsurePlatformAiRegistryConfigured.php',
            'app/Http/Middleware/InitializePublicAiPlatformTenantContext.php',
            'app/Infrastructure/AI/Conversation/EloquentConversationStore.php',
        ];
        $violations = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if (in_array($relative, $allowed, true)) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if ($this->selectsProviderFromConfig($source)) {
                    $violations[] = $relative;
                }
            }
        }

        self::assertSame([], $violations, 'Runtime provider/model/prompt authority must stay behind the registry/bootstrap boundary.');
    }

    private function selectsProviderFromConfig(string $source): bool
    {
        return preg_match('/config\s*\(\s*[\'"]ai\.(?:providers|capabilities\.[^\'"]+\.(?:provider|models)|public\.billing_tenant_id)[\'"]/i', $source) === 1
            || preg_match('/env\s*\(\s*[\'"]AI_(?:PUBLIC|PRIVATE|CATALOG)_[A-Z_]*(?:PROVIDER|MODEL)[\'"]/i', $source) === 1;
    }
}
