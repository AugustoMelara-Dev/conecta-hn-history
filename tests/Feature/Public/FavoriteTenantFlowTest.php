<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Favorite;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FavoriteTenantFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_toggle_tenant_favorite_with_uuid_identifier(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();

        $favorited = $user->toggleFavorite($tenant);

        self::assertTrue($favorited);

        /** @var Favorite|null $favorite */
        $favorite = Favorite::query()->where('user_id', $user->id)->first();

        self::assertNotNull($favorite);
        self::assertSame((string) $tenant->id, (string) $favorite->favoritable_id);
        self::assertSame(Tenant::class, $favorite->favoritable_type);
    }

    public function test_toggle_favorite_twice_removes_existing_record(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();

        self::assertTrue($user->toggleFavorite($tenant));
        self::assertFalse($user->toggleFavorite($tenant));

        $count = Favorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_id', (string) $tenant->id)
            ->where('favoritable_type', Tenant::class)
            ->count();

        self::assertSame(0, $count);
    }
}
