<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Infrastructure\Persistence\Models\SocialIdentityModel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SocialIdentityPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_social_provider_tokens_are_encrypted_at_rest_and_hidden(): void
    {
        $user = User::factory()->create();

        $identity = SocialIdentityModel::query()->create([
            'user_id' => $user->id,
            'user_type' => User::class,
            'provider_name' => 'google',
            'provider_id' => 'google_123',
            'token' => 'plain-access-token',
            'refresh_token' => 'plain-refresh-token',
            'avatar' => null,
        ]);

        $raw = DB::table('social_identities')->where('id', $identity->id)->first();

        self::assertNotSame('plain-access-token', (string) $raw->token);
        self::assertNotSame('plain-refresh-token', (string) $raw->refresh_token);
        self::assertSame('plain-access-token', $identity->fresh()?->token);
        self::assertArrayNotHasKey('token', $identity->toArray());
        self::assertArrayNotHasKey('refresh_token', $identity->toArray());
    }
}
