<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class BrandCleanCommandTest extends TestCase
{
    public function test_brand_clean_passes_for_vito_copy(): void
    {
        $directory = storage_path('framework/testing/brand-clean-pass');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/copy.txt', 'Vito Business OS opera el negocio con claridad.');

        $this->artisan('check:brand-clean', ['--path' => [$directory]])
            ->assertSuccessful();
    }

    public function test_brand_clean_fails_for_legacy_brand_terms(): void
    {
        $directory = storage_path('framework/testing/brand-clean-fail');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/copy.txt', ('Con'.'ecta').' legacy copy');

        $this->artisan('check:brand-clean', ['--path' => [$directory]])
            ->assertFailed();
    }
}
