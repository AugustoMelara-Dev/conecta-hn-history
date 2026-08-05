<?php

declare(strict_types=1);

it('keeps quote aggregate persistence behind the canonical writer', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $allowed = str_replace('\\', '/', realpath(
        $root.'/Application/Quotes/CanonicalQuoteWriter.php',
    ) ?: '');
    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $file->getRealPath());
        $contents = (string) file_get_contents($path);
        $ownsAggregate = str_contains($contents, 'new Quote')
            || str_contains($contents, 'new QuoteItem')
            || preg_match(
                "/DB::table\\(['\"](?:quotes|quote_items)['\"]\\).*?->(?:insert|update|delete)\\(/s",
                $contents,
            ) === 1;
        if ($ownsAggregate && $path !== $allowed) {
            $violations[] = str_replace(str_replace('\\', '/', dirname($root)).'/', '', $path);
        }
    }

    expect($violations)->toBe([]);
});

it('keeps the quote API as a typed canonical adapter', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $controller = (string) file_get_contents($root.'/Http/Controllers/Api/V1/QuoteController.php');
    $writer = (string) file_get_contents($root.'/Application/Quotes/CanonicalQuoteWriter.php');

    expect($controller)
        ->toContain('CanonicalQuoteWriter')
        ->toContain('CreateQuoteCommand')
        ->toContain('TransitionQuoteCommand')
        ->not->toContain('Quote::create')
        ->not->toContain('DB::table')
        ->and($writer)
        ->toContain('lockForUpdate()')
        ->toContain('CanonicalOrderWriter')
        ->toContain('CanonicalReservationWriter')
        ->toContain('quote_changed');
});
