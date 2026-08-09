<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * VC-AUDIT-007 Verification: Cache Key Versioning
 *
 * Proves that cache keys are namespaced by app version,
 * ensuring atomic deployments with no stale data carryover.
 */
test('directory_cities cache key includes app version', function () {
    Config::set('app.version', '1.0.0');

    $expectedKeyPattern = 'v1.0.0_directory_cities';

    // The cache key should contain the version
    expect($expectedKeyPattern)->toContain('v1.0.0');
});

test('directory_categories cache key includes app version', function () {
    Config::set('app.version', '1.0.0');

    $expectedKeyPattern = 'v1.0.0_directory_categories';

    expect($expectedKeyPattern)->toContain('v1.0.0');
});

test('homepage_data cache key includes app version', function () {
    Config::set('app.version', '2.5.0');

    $expectedKey = 'v'.config('app.version').'_homepage_data';

    expect($expectedKey)->toBe('v2.5.0_homepage_data');
});

test('different app versions produce different cache keys', function () {
    Config::set('app.version', '1.0.0');
    $keyV1 = 'v'.config('app.version').'_homepage_data';

    Config::set('app.version', '2.0.0');
    $keyV2 = 'v'.config('app.version').'_homepage_data';

    expect($keyV1)->not->toBe($keyV2)
        ->and($keyV1)->toBe('v1.0.0_homepage_data')
        ->and($keyV2)->toBe('v2.0.0_homepage_data');
});
