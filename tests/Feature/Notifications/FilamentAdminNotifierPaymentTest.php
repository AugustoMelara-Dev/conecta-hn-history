<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Infrastructure\Notifications\FilamentAdminNotifier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FilamentAdminNotifierPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_update_notifies_only_the_exact_tenant_owner_with_safe_status_copy(): void
    {
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        Tenant::factory()->forUser($foreignOwner)->create();

        (new FilamentAdminNotifier)->notifyPaymentUpdate(
            (string) $tenant->id,
            '<script>unknown</script>',
            -100,
        );

        $notification = DB::table('notifications')->where('notifiable_id', $owner->id)->first();
        self::assertNotNull($notification);
        self::assertSame(0, DB::table('notifications')->where('notifiable_id', $foreignOwner->id)->count());
        $data = json_decode((string) $notification->data, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Estado de pago actualizado', $data['title']);
        self::assertStringContainsString('L. 0.00', $data['body']);
        self::assertStringNotContainsString('<script>', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
