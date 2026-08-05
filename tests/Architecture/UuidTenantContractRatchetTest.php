<?php

declare(strict_types=1);

it('forbids integer tenant identity in active repository and reader contracts', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $roots = [
        $appRoot.'/Domain',
        $appRoot.'/Infrastructure',
    ];
    $violations = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if (! preg_match('/(Contract|Interface|Repository|Reader)\.php$/', $path)) {
                continue;
            }

            $source = file_get_contents($path);
            if (! is_string($source)) {
                continue;
            }

            if (preg_match('/\b(?:int|int\|string|string\|int)\s+\$tenantId\b/', $source) === 1) {
                $violations[] = str_replace(str_replace('\\', '/', dirname($appRoot)).'/', '', $path);
            }
        }
    }

    expect($violations)->toBe([]);
});

it('forbids integer tenant identity in active commands entities and jobs', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $roots = [
        $appRoot.'/Application',
        $appRoot.'/Domain/Sales/Entities',
        $appRoot.'/Domain/Sales/Factories',
        $appRoot.'/Infrastructure/Jobs',
    ];
    $violations = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (is_string($source)
                && preg_match('/\b(?:\??int|int\|string|string\|int)\s+\$tenantId\b/', $source) === 1) {
                $violations[] = str_replace(
                    str_replace('\\', '/', dirname($appRoot)).'/',
                    '',
                    str_replace('\\', '/', $file->getPathname()),
                );
            }
        }
    }

    expect($violations)->toBe([]);
});

it('forbids integer tenant identity in active models policies services and listeners', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $roots = [
        $appRoot.'/Models',
        $appRoot.'/Policies',
        $appRoot.'/Services',
        $appRoot.'/Observers',
        $appRoot.'/Filament/Widgets',
        $appRoot.'/Infrastructure/Listeners',
    ];
    $violations = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (is_string($source)
                && preg_match('/\b(?:\??int|int\|string|string\|int)\s+\$tenantId\b/', $source) === 1) {
                $violations[] = str_replace(
                    str_replace('\\', '/', dirname($appRoot)).'/',
                    '',
                    str_replace('\\', '/', $file->getPathname()),
                );
            }
        }
    }

    expect($violations)->toBe([]);
});

it('forbids integer tenant identity across every active application boundary', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (is_string($source)
            && preg_match('/\b(?:\??int|int\|string|string\|int)(?:\|null)?\s+\$tenant(?:Id|Key)\b/', $source) === 1) {
            $violations[] = str_replace(
                str_replace('\\', '/', dirname($appRoot)).'/',
                '',
                str_replace('\\', '/', $file->getPathname()),
            );
        }
    }

    expect($violations)->toBe([]);
});

it('covers tenant identity returns validators and legacy compatibility escape hatches', function (): void {
    $appRoot = realpath(__DIR__.'/../../app');
    expect($appRoot)->toBeString();

    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $source = file_get_contents($file->getPathname());
        if (! is_string($source)) {
            continue;
        }

        if (preg_match('/\b(?:\??int|int\|string|string\|int)(?:\|null)?\s+\$tenant_id\b/', $source) === 1) {
            $violations[] = "integer snake-case tenant identity: {$path}";
        }

        if (preg_match('/function\s+(?:get|resolve|extract|normalize)\w*TenantId\([^)]*\)\s*:\s*(?:\??int|int\|string|string\|int)(?:\|null)?/', $source) === 1) {
            $violations[] = "integer tenant identity return: {$path}";
        }

        if (preg_match('/[\'\"]tenantId[\'\"]\s*=>[^\r\n]*[\'\"]integer[\'\"]/', $source) === 1) {
            $violations[] = "integer tenant validator: {$path}";
        }

        if (str_contains($source, 'TenantId::fromInt(') || str_contains($source, '->toInt()')) {
            $violations[] = "public numeric TenantId compatibility API: {$path}";
        }

        $offset = 0;
        while (($offset = strpos($source, 'TenantId::fromSerialized(', $offset)) !== false) {
            $prefix = substr($source, 0, $offset);
            $lastMethod = strrpos($prefix, 'function ');
            $lastHydrator = strrpos($prefix, 'function fromPrimitives');

            if ($lastHydrator === false || $lastMethod !== $lastHydrator) {
                $violations[] = "legacy tenant hydration outside fromPrimitives: {$path}";
            }

            $offset++;
        }
    }

    expect($violations)->toBe([]);
    expect(file_exists($appRoot.'/ValueObjects/TenantId.php'))->toBeFalse();
});
