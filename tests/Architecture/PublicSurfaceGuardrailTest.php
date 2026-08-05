<?php

declare(strict_types=1);

it('keeps public blade layouts indexable and accessible on mobile', function (): void {
    $basePath = dirname(__DIR__, 2);
    $layoutPaths = [
        'resources/views/layouts/public.blade.php',
        'resources/views/layouts/shop.blade.php',
    ];

    foreach ($layoutPaths as $relativePath) {
        $contents = file_get_contents($basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        expect($contents)
            ->not->toBeFalse()
            ->and($contents)->toContain("@include('components.seo-meta')")
            ->and($contents)->not->toContain('user-scalable=no')
            ->and($contents)->not->toContain('maximum-scale=1');
    }
});

it('keeps the shared SEO component on the canonical Dovra AI public brand', function (): void {
    $basePath = dirname(__DIR__, 2);
    $contents = file_get_contents($basePath.DIRECTORY_SEPARATOR.'resources/views/components/seo-meta.blade.php');

    expect($contents)
        ->not->toBeFalse()
        ->and($contents)->toContain("config('dovra.identity.name')")
        ->and($contents)->toContain("config('dovra.identity.commercial_promise')")
        ->and($contents)->not->toContain('Conecta HN')
        ->and($contents)->not->toContain('Vito');
});
