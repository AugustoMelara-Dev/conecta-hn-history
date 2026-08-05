<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QuarantineUnsafeSvgMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
    }

    public function test_dry_run_fails_closed_without_mutating_public_media(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('tenants/demo/logo.svg', '<svg><script>alert(1)</script></svg>');

        $exitCode = Artisan::call('media:quarantine-unsafe-svg', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertSame('unsafe_media_detected', $payload['status']);
        self::assertSame(1, $payload['unsafe_objects']);
        Storage::disk('public')->assertExists('tenants/demo/logo.svg');
        Storage::disk('local')->assertDirectoryEmpty('quarantine/unsafe-svg');
    }

    public function test_execute_moves_extension_and_content_detected_svg_to_private_quarantine(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('tenants/demo/logo.svg', '<svg><script>alert(1)</script></svg>');
        Storage::disk('public')->put('products/disguised.png', '<?xml version="1.0"?><svg onload="alert(1)"></svg>');
        Storage::disk('public')->put('products/safe.png', "\x89PNG\r\n\x1a\nSAFE");

        $exitCode = Artisan::call('media:quarantine-unsafe-svg', [
            '--execute' => true,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertSame('quarantined', $payload['status']);
        self::assertSame(2, $payload['unsafe_objects']);
        Storage::disk('public')->assertMissing('tenants/demo/logo.svg');
        Storage::disk('public')->assertMissing('products/disguised.png');
        Storage::disk('public')->assertExists('products/safe.png');

        $quarantined = collect(Storage::disk('local')->allFiles('quarantine/unsafe-svg'))
            ->reject(fn (string $file): bool => str_contains($file, '/manifests/'));
        self::assertCount(2, $quarantined);
        self::assertCount(1, Storage::disk('local')->allFiles('quarantine/unsafe-svg/manifests'));
    }
}
