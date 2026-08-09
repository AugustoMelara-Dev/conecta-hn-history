<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('database-backed unit tests run migrations in the testing environment', function () {
    expect(app()->environment())->toBe('testing')
        ->and(Schema::hasTable('tenants'))->toBeTrue();
});
