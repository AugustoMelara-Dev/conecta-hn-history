<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_uuid_user_can_list_and_count_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->notification($user, read: false);
        $this->notification($user, read: true);
        $this->notification(User::factory()->create(), read: false);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('unread_count', 1);

        $this->getJson('/api/v1/notifications/count')
            ->assertOk()
            ->assertExactJson(['unread_count' => 1]);
    }

    private function notification(User $user, bool $read): DatabaseNotification
    {
        $notification = new DatabaseNotification;
        $notification->forceFill([
            'id' => (string) Str::uuid(),
            'type' => self::class,
            'notifiable_type' => User::class,
            'notifiable_id' => (string) $user->getKey(),
            'data' => ['message' => 'Runtime smoke'],
            'read_at' => $read ? now() : null,
        ]);
        $notification->save();

        return $notification;
    }
}
