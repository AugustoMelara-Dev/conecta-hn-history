<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AutomationAuthorityRatchetTest extends TestCase
{
    /** @var list<string> */
    private const AUTHORIZATION_PORT_FILES = [
        'app/Application/Automation/Contracts/ToolPolicyEngineInterface.php',
        'app/Application/Automation/ToolPolicyEngine.php',
    ];

    public function test_only_the_program06_slice_declares_the_mutating_tool_authorization_port(): void
    {
        $root = dirname(__DIR__, 2);
        $matches = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/authorize\s*\(\s*ToolAuthorizationRequest\s+\$request\s*\)\s*:\s*ToolAuthorizationDecision/', $source) !== 1) {
                continue;
            }

            $matches[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }

        sort($matches);
        $expected = self::AUTHORIZATION_PORT_FILES;
        sort($expected);

        self::assertSame($expected, $matches);
    }

    public function test_the_automation_slice_uses_the_policy_port_without_binding_to_the_legacy_program05_confirmation_service(): void
    {
        $root = dirname(__DIR__, 2).'/app/Application/Automation';
        $violations = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1));

            if (str_contains($source, 'AiToolConfirmationService')) {
                $violations[] = $relativePath;
            }
        }

        self::assertSame([], $violations, 'Program 06 must use a local automation seam instead of rebinding the Program 05 confirmation service.');
    }
}
