<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Domain\Shared\Services\TenantContext;
use App\Events\Order\OrderDelivered;
use App\Listeners\AwardLoyaltyPoints;
use App\Models\LoyaltyLedger;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

final class AwardLoyaltyPointsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function delivery_event_awards_points_once_inside_its_tenant(): void
    {
        [$tenant, $order] = $this->fixture();
        $event = OrderDelivered::create(
            (string) $tenant->getKey(),
            $order->getKey(),
            (string) $order->order_number,
        );

        self::assertTrue($this->runListener($event));
        self::assertFalse($this->runListener($event));
        app(TenantContext::class)->setAsSystemRoot();

        $entry = LoyaltyLedger::query()->where('tenant_id', $tenant->getKey())->sole();
        self::assertSame((string) $tenant->getKey(), (string) $entry->tenant_id);
        self::assertSame((string) $order->getKey(), (string) $entry->order_id);
        self::assertSame("order:{$order->getKey()}:earn", $entry->idempotency_key);
        self::assertSame(10, $entry->amount);
    }

    #[Test]
    public function foreign_tenant_event_cannot_award_points_for_another_tenants_order(): void
    {
        [, $order] = $this->fixture();
        $tenantB = Tenant::factory()->create();
        $event = OrderDelivered::create(
            (string) $tenantB->getKey(),
            $order->getKey(),
            (string) $order->order_number,
        );

        self::assertFalse($this->runListener($event));
        app(TenantContext::class)->setAsSystemRoot();

        self::assertSame(0, LoyaltyLedger::query()->count());
    }

    private function runListener(OrderDelivered $event): bool
    {
        $listener = new AwardLoyaltyPoints;
        $middleware = $listener->middleware($event);

        self::assertCount(1, $middleware);
        $result = $middleware[0]->handle(
            new stdClass,
            static fn (): bool => $listener->handle($event),
        );

        self::assertFalse(app(TenantContext::class)->isInitialized());

        return $result;
    }

    /**
     * @return array{Tenant, Order}
     */
    private function fixture(): array
    {
        app(TenantContext::class)->setAsSystemRoot();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $order = Order::factory()->delivered()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => (string) $user->getKey(),
            'payment_status' => 'paid',
            'subtotal' => 100,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 100,
        ]);
        LoyaltyProgram::forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Programa A',
            'earn_ratio' => 10,
            'redemption_ratio' => 100,
            'expiry_days' => 365,
            'is_active' => true,
        ]);

        return [$tenant, $order];
    }
}
