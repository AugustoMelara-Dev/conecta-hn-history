<?php

declare(strict_types=1);

use Illuminate\Support\Str;

it('keeps canonical registry mutations behind the exact lifecycle and backfill writers', function (): void {
    $roots = [
        app_path('Application'),
        app_path('Http/Controllers'),
        app_path('Infrastructure/Jobs'),
        app_path('Filament'),
    ];
    $registryTables = [
        'ai_providers',
        'ai_provider_versions',
        'ai_models',
        'ai_model_versions',
        'ai_agent_definitions',
        'ai_agent_versions',
        'ai_prompt_definitions',
        'ai_prompt_versions',
        'ai_release_policies',
        'ai_agent_activations',
        'ai_release_decisions',
    ];
    $writers = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! is_string($source)
                || ! Str::contains($source, $registryTables)
                || preg_match_all('/->(?:insert|insertGetId|update|upsert|delete|increment|decrement)\\s*\\(/', $source, $matches) < 1) {
                continue;
            }

            $relative = str_replace('\\', '/', Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR));
            $writers[$relative] = count($matches[0]);
        }
    }

    expect($writers)->toBe([
        'app/Application/Ai/Registry/AiRegistryBackfill.php' => 11,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php' => 10,
    ]);
});
