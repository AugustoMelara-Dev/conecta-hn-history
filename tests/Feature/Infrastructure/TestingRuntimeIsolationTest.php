<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

final class TestingRuntimeIsolationTest extends TestCase
{
    public function test_testing_runtime_uses_an_isolated_compiled_view_path(): void
    {
        $compiledViewPath = str_replace('\\', '/', (string) config('view.compiled'));
        $storagePath = str_replace('\\', '/', storage_path());

        self::assertNotSame('', $compiledViewPath);
        self::assertDirectoryExists($compiledViewPath);
        self::assertStringContainsString('/storage/testing/', $storagePath);
        self::assertStringContainsString('/storage/testing/', $compiledViewPath);
        self::assertStringContainsString('/framework/views', $compiledViewPath);
    }
}
