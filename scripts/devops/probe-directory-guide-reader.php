<?php

declare(strict_types=1);

use App\Domain\Directory\Contracts\DirectoryGuideReader;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var DirectoryGuideReader $reader */
$reader = app(DirectoryGuideReader::class);

$search = $reader->searchPublishedTenants(
    search: null,
    citySlug: 'tegucigalpa',
    categorySlug: 'restaurantes',
    limit: 20
);

$guide = $reader->recommendByBudget(
    budget: 1000,
    citySlug: 'tegucigalpa',
    categorySlug: 'restaurantes',
    search: 'combo',
    limit: 8
);

$out = [
    'search_count' => count($search),
    'search_first_slug' => $search[0]['slug'] ?? null,
    'guide_count' => count($guide),
    'guide_first_slug' => $guide[0]['tenant']['slug'] ?? null,
];

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
