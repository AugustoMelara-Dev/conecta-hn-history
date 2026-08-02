<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DovraLaunchGateTest extends TestCase
{
    public function test_launch_gate_matrix_defines_exactly_eighteen_non_greenwashed_rows(): void
    {
        $gates = config('dovra.launch.gates');

        self::assertIsArray($gates);
        self::assertCount(18, $gates);

        foreach ($gates as $gate) {
            self::assertMatchesRegularExpression('/^LG\d{2}$/', $gate['id']);
            self::assertContains($gate['result'], ['proved', 'failed', 'incomplete', 'external']);
            self::assertNotEmpty($gate['title']);
            self::assertNotEmpty($gate['commands']);
            self::assertNotEmpty($gate['authority_paths']);
            self::assertNotEmpty($gate['artifacts']);
            self::assertNotEmpty($gate['limitation']);

            foreach (array_merge($gate['authority_paths'], $gate['artifacts']) as $path) {
                self::assertFileExists(base_path($path), $path);
            }
        }
    }

    public function test_launch_gate_markdown_preserves_every_result_without_pass_by_absence(): void
    {
        $markdown = (string) file_get_contents(base_path('docs/dovra/launch-gates-2026-08-01.md'));
        $gates = config('dovra.launch.gates');

        foreach ($gates as $gate) {
            self::assertStringContainsString('| '.$gate['id'].' |', $markdown);
            self::assertStringContainsString('| '.$gate['result'].' |', $markdown);
            self::assertStringContainsString($gate['title'], $markdown);
        }

        self::assertStringNotContainsString('| pass |', strtolower($markdown));
        self::assertStringContainsString('No launch gate passes by absence.', $markdown);
    }
}
