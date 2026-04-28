<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class DirectoryGuidePreflightCommandTest extends TestCase
{
    public function test_it_fails_in_strict_mode_when_required_variables_are_missing(): void
    {
        config([
            'app.key' => '',
            'services.openai.api_key' => '',
            'app.platform_app_url' => '',
            'app.platform_directory_url' => '',
        ]);

        $exitCode = Artisan::call('directory:guide:preflight', [
            '--strict' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"status": "failed"', $output);
        $this->assertStringContainsString('"APP_KEY": false', $output);
        $this->assertStringContainsString('"OPENAI_API_KEY": false', $output);
    }

    public function test_it_passes_when_required_runtime_configuration_exists(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'services.openai.api_key' => 'test-openai-key',
            'app.platform_app_url' => 'https://app.vitobusinessos.test',
            'app.platform_directory_url' => 'https://www.vitobusinessos.test',
            'directory.guide.pgvector.table' => 'directory_product_embeddings',
        ]);

        $exitCode = Artisan::call('directory:guide:preflight', [
            '--strict' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"status": "ok"', $output);
    }
}
