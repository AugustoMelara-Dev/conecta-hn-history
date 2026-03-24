<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class NotificationApiUuidTest extends TestCase
{
    use DatabaseTransactions;

    public function test_uuid_users_can_receive_and_query_database_notifications(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        FilamentNotification::make()
            ->title('Pago aprobado')
            ->body('Tu negocio ya tiene acceso PRO.')
            ->success()
            ->sendToDatabase($user);

        $notification = DatabaseNotification::query()->firstOrFail();

        $this->assertSame((string) $user->getKey(), (string) $notification->notifiable_id);
        $this->assertSame($user->getMorphClass(), $notification->notifiable_type);

        $this->getJson('/api/notifications/count')
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.title', 'Pago aprobado');

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $notification->refresh();

        $this->assertNotNull($notification->read_at);
    }

    public function test_uuid_users_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        FilamentNotification::make()
            ->title('Nueva orden')
            ->body('Llegó un pedido al panel.')
            ->success()
            ->sendToDatabase($user);

        FilamentNotification::make()
            ->title('Plan por vencer')
            ->body('Tu suscripción vence pronto.')
            ->warning()
            ->sendToDatabase($user);

        $this->getJson('/api/notifications/count')
            ->assertOk()
            ->assertJson(['count' => 2]);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(
            0,
            DatabaseNotification::query()->whereNull('read_at')->count()
        );

        $this->getJson('/api/notifications/count')
            ->assertOk()
            ->assertJson(['count' => 0]);
    }
}
