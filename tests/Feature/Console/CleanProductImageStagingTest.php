<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CleanProductImageStagingTest extends TestCase
{
    public function test_it_recursively_deletes_stale_private_product_staging_objects(): void
    {
        Storage::fake('local');
        $path = 'manual-product-staging/550e8400-e29b-41d4-a716-446655440000/nested/source.png';
        Storage::disk('local')->put($path, 'private-bytes');

        $this->artisan('storage:cleanup-product-staging', ['--grace' => -1])
            ->expectsOutput('1 stale staging object(s) deleted.')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_dry_run_reports_but_preserves_staging_objects(): void
    {
        Storage::fake('local');
        $path = 'manual-product-staging/550e8400-e29b-41d4-a716-446655440000/source.png';
        Storage::disk('local')->put($path, 'private-bytes');

        $this->artisan('storage:cleanup-product-staging', ['--grace' => -1, '--dry-run' => true])
            ->expectsOutput($path)
            ->expectsOutput('1 stale staging object(s) found.')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($path);
    }
}
