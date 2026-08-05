<?php

declare(strict_types=1);

it('prevents financial readers and private agents from summing mutable order totals', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $allowedOperationalCalculators = [
        str_replace('\\', '/', $appRoot.'/Infrastructure/Sales/EloquentPublicOrderCreator.php'),
    ];
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));
    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        if (in_array($path, $allowedOperationalCalculators, true)) {
            continue;
        }

        $source = file_get_contents($path);
        if (is_string($source)
            && (str_contains($source, "->sum('total')") || str_contains($source, '->sum("total")'))) {
            $violations[] = $path;
        }
    }

    expect($violations)->toBe([]);
});
