<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('the Laravel 13 migration chain remains compatible with sqlite', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('database.default'))->toBe('sqlite')
        ->and(config('telescope.storage.database.connection'))->toBe('sqlite');

    $this->artisan('migrate:fresh', [
        '--drop-views' => false,
        '--drop-types' => false,
        '--seed' => false,
    ])
        ->assertSuccessful();

    expect(Schema::hasTable('tenants'))->toBeTrue();
});
