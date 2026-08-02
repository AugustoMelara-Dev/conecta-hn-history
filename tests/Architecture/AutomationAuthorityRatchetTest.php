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

    public function test_agent_actions_compose_the_existing_policy_confirmation_and_workflow_authorities_without_constructing_providers(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/app/Application/Automation/AgentAction/AgentActionExecutor.php');

        self::assertStringContainsString('ToolPolicyEngine', $source);
        self::assertStringContainsString('AiToolConfirmationService', $source);
        self::assertStringContainsString('WorkflowRuntime', $source);
        self::assertStringContainsString('->evaluate(', $source);
        self::assertStringContainsString('->authorizeAgentAction(', $source);
        self::assertStringContainsString('->claimAgentActionIdempotency(', $source);
        self::assertStringContainsString('->startAgentAction(', $source);
        self::assertDoesNotMatchRegularExpression('/new\\s+[^;]*(Provider|Client)\\s*\\(/', $source);
    }
}
