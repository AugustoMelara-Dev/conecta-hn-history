<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\AI;

use App\Infrastructure\AI\CatalogImportPublicFileCompensation;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CatalogImportPublicFileCompensationTest extends TestCase
{
    public function test_stale_loser_cannot_delete_the_winner_public_file(): void
    {
        Storage::fake('public');
        $path = 'tenants/winner/products/42.jpg';
        Storage::disk('public')->put($path, 'winner-content');

        app(CatalogImportPublicFileCompensation::class)->deleteIfOwned(Storage::disk('public'), $path, false);

        Storage::disk('public')->assertExists($path);
        self::assertSame('winner-content', Storage::disk('public')->get($path));
    }

    public function test_owned_loser_cleanup_deletes_only_its_unique_attempt_path(): void
    {
        Storage::fake('public');
        $winnerPath = 'tenants/winner/products/42.winner.jpg';
        $loserPath = 'tenants/winner/products/42.loser.jpg';
        Storage::disk('public')->put($winnerPath, 'winner-content');
        Storage::disk('public')->put($loserPath, 'loser-content');

        app(CatalogImportPublicFileCompensation::class)->deleteIfOwned(Storage::disk('public'), $loserPath, true);

        Storage::disk('public')->assertMissing($loserPath);
        Storage::disk('public')->assertExists($winnerPath);
        self::assertSame('winner-content', Storage::disk('public')->get($winnerPath));
    }
}
