<?php

declare(strict_types=1);

it('forbids direct application reads of Dovra business graph tables outside the repository authority', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $allowed = [
        'app/Application/BusinessGraph/Contracts/BusinessGraphRepositoryInterface.php',
        'app/Infrastructure/BusinessGraph/DatabaseBusinessGraphRepository.php',
        'app/Console/Commands/ValidateDovraWorkforceCommand.php',
    ];
    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace(
            str_replace('\\', '/', dirname($appRoot)).'/',
            '',
            str_replace('\\', '/', $file->getPathname()),
        );

        if (in_array($relativePath, $allowed, true)) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (! is_string($source)) {
            continue;
        }

        if (str_contains($source, 'dovra_business_facts') || str_contains($source, 'dovra_business_edges')) {
            $violations[] = $relativePath;
        }
    }

    expect($violations)->toBe([]);
});
