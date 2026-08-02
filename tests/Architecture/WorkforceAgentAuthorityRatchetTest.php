<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WorkforceAgentAuthorityRatchetTest extends TestCase
{
    public function test_every_workforce_role_has_complete_catalog_configuration(): void
    {
        foreach (AgentRole::cases() as $role) {
            $configuration = AgentRoleCatalog::configuration($role);

            self::assertNotSame('', trim($configuration->objective), "Missing objective for [{$role->value}].");
            self::assertNotSame([], $configuration->allowedJobs, "Missing allowed jobs for [{$role->value}].");
            self::assertNotSame('', trim($configuration->riskProfile), "Missing risk profile for [{$role->value}].");
            self::assertNotSame([], $configuration->metricKeys, "Missing metric keys for [{$role->value}].");
        }
    }

    public function test_application_and_http_layers_cannot_construct_ai_providers_directly(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];

        foreach ([$root.'/app/Application', $root.'/app/Http'] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if (! $this->constructsAiProvider($source)) {
                    continue;
                }

                $violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        self::assertSame([], $violations, 'Application and HTTP layers must use the AI gateway/registry boundaries, not construct providers.');
    }

    private function constructsAiProvider(string $source): bool
    {
        if (preg_match('/new\\s+\\\\?App\\\\(?:Infrastructure|Services)\\\\AI\\\\[A-Za-z_][A-Za-z0-9_\\\\]*/', $source) === 1) {
            return true;
        }

        preg_match_all('/^use\\s+(App\\\\(?:Infrastructure|Services)\\\\AI\\\\[^;]+);/mi', $source, $imports);
        foreach ($imports[1] as $import) {
            [$class, $alias] = array_pad(preg_split('/\\s+as\\s+/i', $import, 2) ?: [], 2, null);
            $name = $alias ?? substr($class, (int) strrpos($class, '\\') + 1);
            if (preg_match('/new\\s+'.preg_quote($name, '/').'\\b/', $source) === 1) {
                return true;
            }
        }

        return false;
    }
}
