<?php

declare(strict_types=1);

function billingSourceViolations(string $relativePath, array $forbidden): array
{
    $projectRoot = getcwd();
    $base = $projectRoot.DIRECTORY_SEPARATOR.str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);

    if (! is_dir($base)) {
        return [];
    }

    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        foreach ($forbidden as $needle) {
            if (str_contains($source, $needle)) {
                $violations[] = str_replace($projectRoot.DIRECTORY_SEPARATOR, '', $file->getPathname())." uses {$needle}";
            }
        }
    }

    return $violations;
}

test('Application Layer should NOT depend on Eloquent Models', function (): void {
    expect(billingSourceViolations('app/Application/Billing', ['App\\Models\\', '\\App\\Models\\']))->toBeEmpty();
});

test('Application Layer should NOT depend on HTTP Layer', function (): void {
    expect(billingSourceViolations('app/Application/Billing', ['Illuminate\\Http']))->toBeEmpty();
});

test('Domain Layer should NOT depend on Laravel Infrastructure', function (): void {
    expect(billingSourceViolations('app/Domain/Billing', [
        'Illuminate\\Database',
        'Illuminate\\Support\\Facades',
        'App\\Infrastructure',
    ]))->toBeEmpty();
});

test('Domain Layer should NOT depend on Application Layer', function (): void {
    expect(billingSourceViolations('app/Domain/Billing', ['App\\Application\\Billing']))->toBeEmpty();
});

test('Repositories must be interfaces')
    ->expect('App\Domain\Billing\Repositories')
    ->toBeInterfaces();

test('Domain Exceptions must extend a domain exception base', function (): void {
    $base = getcwd().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR.'Billing'.DIRECTORY_SEPARATOR.'Exceptions';
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (! str_contains($source, 'extends DomainException')) {
            $violations[] = str_replace(getcwd().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});

test('Value Objects must be final and immutable')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

test('Enums must be backed enums')
    ->expect('App\Domain\Billing\Enums')
    ->toBeEnums();
