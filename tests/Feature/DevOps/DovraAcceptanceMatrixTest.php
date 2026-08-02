<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DovraAcceptanceMatrixTest extends TestCase
{
    public function test_acceptance_matrix_defines_exactly_sixteen_repository_rows(): void
    {
        $matrix = config('dovra.launch.acceptance');

        self::assertIsArray($matrix);
        self::assertCount(16, $matrix);

        foreach ($matrix as $row) {
            self::assertMatchesRegularExpression('/^AC\d{2}$/', $row['id']);
            self::assertContains($row['result'], ['proved', 'failed', 'incomplete', 'external']);
            self::assertNotEmpty($row['title']);
            self::assertNotEmpty($row['commands']);
            self::assertNotEmpty($row['authority_paths']);
            self::assertNotEmpty($row['artifacts']);
            self::assertNotEmpty($row['limitation']);

            foreach (array_merge($row['authority_paths'], $row['artifacts']) as $path) {
                self::assertFileExists(base_path($path), $path);
            }
        }
    }

    public function test_acceptance_markdown_keeps_every_id_result_and_limitation_explicit(): void
    {
        $markdown = (string) file_get_contents(base_path('docs/dovra/acceptance-matrix-2026-08-01.md'));
        $matrix = config('dovra.launch.acceptance');

        foreach ($matrix as $row) {
            self::assertStringContainsString('| '.$row['id'].' |', $markdown);
            self::assertStringContainsString('| '.$row['result'].' |', $markdown);
            self::assertStringContainsString($row['title'], $markdown);
        }

        self::assertStringNotContainsString('| pass |', strtolower($markdown));
        self::assertStringContainsString('No criterion passes by absence.', $markdown);
    }
}
