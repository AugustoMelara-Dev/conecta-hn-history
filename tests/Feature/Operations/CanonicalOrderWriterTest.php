<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\CreateOrderCommand;
use App\Application\Orders\Commands\TransitionOrderCommand;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalOrderWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_order_uses_server_snapshots_stock_and_idempotent_replay(): void
    {
        [$tenant, $owner] = $this->orderTenant();
        GlobalMarketFixture::assignCanada($tenant);
        $product = Product::factory()->forTenant($tenant)->withStock(5)->create([
            'name' => 'Maple kit',
            'price' => 12.50,
            'price_minor' => 1_250,
            'currency' => 'CAD',
        ]);
        $writer = app(CanonicalOrderWriter::class);
        $command = $this->createCommand(
            $tenant,
            $owner,
            'order:create:ca-1',
            [(int) $product->id => 2],
        );

        $first = $writer->create($command);
        $second = $writer->create($command);

        self::assertSame($first->order->getKey(), $second->order->getKey());
        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame('CAD', $first->order->currency);
        self::assertSame(25.0, (float) $first->order->getRawOriginal('subtotal'));
        self::assertSame(25.0, (float) $first->order->getRawOriginal('total'));
        $this->assertDatabaseHas('order_items', [
            'order_id' => $first->order->id,
            'product_id' => $product->id,
            'product_name' => 'Maple kit',
            'product_price' => 12.50,
            'unit_price' => 12.50,
            'quantity' => 2,
            'line_total' => 25,
        ]);
        self::assertSame(3, (int) $product->fresh()->stock_quantity);
        self::assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', BusinessOperationChanged::class)
            ->count());
    }

    public function test_create_rejects_drift_cross_tenant_products_and_oversell(): void
    {
        [$tenant, $owner] = $this->orderTenant();
        GlobalMarketFixture::assignCanada($tenant);
        [$other] = $this->orderTenant('other-order@example.test');
        GlobalMarketFixture::assignCanada($other);
        $product = Product::factory()->forTenant($tenant)->withStock(1)->create([
            'price' => 10,
            'price_minor' => 1_000,
            'currency' => 'CAD',
        ]);
        $foreign = Product::factory()->forTenant($other)->withStock(10)->create([
            'price' => 10,
            'price_minor' => 1_000,
            'currency' => 'CAD',
        ]);
        $writer = app(CanonicalOrderWriter::class);
        try {
            $writer->create($this->createCommand(
                $tenant,
                $owner,
                'order:create:currency-drift',
                [(int) $product->id => 1],
                requestedCurrency: 'USD',
            ));
            self::fail('A caller-provided currency cannot override the tenant market.');
        } catch (ValidationException) {
            self::assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
            self::assertSame(1, (int) $product->fresh()->stock_quantity);
        }
        $writer->create($this->createCommand(
            $tenant,
            $owner,
            'order:create:stock-1',
            [(int) $product->id => 1],
        ));

        try {
            $writer->create($this->createCommand(
                $tenant,
                $owner,
                'order:create:stock-1',
                [(int) $product->id => 2],
            ));
            self::fail('Changed order payload cannot reuse an idempotency key.');
        } catch (ValidationException|\LogicException) {
            self::assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        }

        foreach ([
            ['key' => 'order:create:foreign', 'product' => (int) $foreign->id],
            ['key' => 'order:create:oversell', 'product' => (int) $product->id],
        ] as $attempt) {
            try {
                $writer->create($this->createCommand(
                    $tenant,
                    $owner,
                    $attempt['key'],
                    [$attempt['product'] => 1],
                ));
                self::fail('Foreign or unavailable stock must fail closed.');
            } catch (ValidationException) {
                self::assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
            }
        }
        self::assertSame(0, (int) $product->fresh()->stock_quantity);
        self::assertSame(10, (int) $foreign->fresh()->stock_quantity);
    }

    public function test_fulfillment_and_minimum_order_are_tenant_market_rules(): void
    {
        [$tenant, $owner] = $this->orderTenant();
        GlobalMarketFixture::assignJapan($tenant);
        $tenant->forceFill([
            'delivery_enabled' => false,
            'pickup_enabled' => true,
            'minimum_order' => 2_000,
        ])->save();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create([
            'price' => 1_500,
            'price_minor' => 1_500,
            'currency' => 'JPY',
        ]);
        $writer = app(CanonicalOrderWriter::class);

        foreach ([
            ['key' => 'order:create:below-minimum', 'delivery' => 'pickup'],
            ['key' => 'order:create:disabled-delivery', 'delivery' => 'delivery'],
        ] as $attempt) {
            try {
                $writer->create($this->createCommand(
                    $tenant,
                    $owner,
                    $attempt['key'],
                    [(int) $product->id => 1],
                    deliveryType: $attempt['delivery'],
                    customerAddress: $attempt['delivery'] === 'delivery' ? '1 Marunouchi' : null,
                ));
                self::fail('Invalid fulfillment or minimum order must fail closed.');
            } catch (ValidationException) {
                self::assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
            }
        }
        self::assertSame(10, (int) $product->fresh()->stock_quantity);
    }

    public function test_global_coupon_limit_is_redeemed_once_without_partial_second_order(): void
    {
        [$tenant, $owner] = $this->orderTenant();
        GlobalMarketFixture::assignCanada($tenant);
        $product = Product::factory()->forTenant($tenant)->withStock(2)->create([
            'price' => 20,
            'price_minor' => 2_000,
            'currency' => 'CAD',
        ]);
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $coupon = Coupon::factory()->fixed(5)->create([
            'tenant_id' => $tenant->id,
            'code' => 'ONCE5',
            'usage_limit' => 1,
            'usage_count' => 0,
            'max_uses_per_user' => 0,
        ]);
        app(TenantContext::class)->reset();
        $writer = app(CanonicalOrderWriter::class);
        $first = $writer->create($this->createCommand(
            $tenant,
            $owner,
            'order:create:coupon-first',
            [(int) $product->id => 1],
            couponCode: 'ONCE5',
        ));

        self::assertSame(5.0, (float) $first->order->getRawOriginal('discount'));
        self::assertSame(15.0, (float) $first->order->getRawOriginal('total'));
        self::assertSame(1, (int) $coupon->fresh()->usage_count);
        self::assertSame(1, DB::table('coupon_user')
            ->where('coupon_id', $coupon->id)
            ->where('order_id', $first->order->id)
            ->count());

        try {
            $writer->create($this->createCommand(
                $tenant,
                $owner,
                'order:create:coupon-second',
                [(int) $product->id => 1],
                couponCode: 'ONCE5',
            ));
            self::fail('A globally depleted coupon cannot create another order.');
        } catch (InvalidCouponException) {
            self::assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
            self::assertSame(1, (int) $product->fresh()->stock_quantity);
            self::assertSame(1, (int) $coupon->fresh()->usage_count);
        }
    }

    public function test_state_transitions_are_tenant_scoped_and_invalid_moves_are_atomic(): void
    {
        [$tenant, $owner] = $this->orderTenant();
        GlobalMarketFixture::assignCanada($tenant);
        [$other, $otherOwner] = $this->orderTenant('state-hostile@example.test');
        GlobalMarketFixture::assignCanada($other);
        $product = Product::factory()->forTenant($tenant)->withStock(5)->create([
            'price' => 10,
            'price_minor' => 1_000,
            'currency' => 'CAD',
        ]);
        $writer = app(CanonicalOrderWriter::class);
        $order = $writer->create($this->createCommand(
            $tenant,
            $owner,
            'order:create:state',
            [(int) $product->id => 1],
        ))->order;

        try {
            $writer->transition(new TransitionOrderCommand(
                (string) $other->id,
                (string) $otherOwner->id,
                (int) $order->id,
                Order::STATUS_CONFIRMED,
            ));
            self::fail('Cross-tenant transitions must hide the order.');
        } catch (ModelNotFoundException) {
            self::assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        }

        $confirmed = $writer->transition(new TransitionOrderCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $order->id,
            Order::STATUS_CONFIRMED,
        ));
        self::assertSame(Order::STATUS_CONFIRMED, $confirmed->order->status);
        self::assertNotNull($confirmed->order->confirmed_at);
        $eventsBefore = DB::table('outbox_messages')->count();

        try {
            $writer->transition(new TransitionOrderCommand(
                (string) $tenant->id,
                (string) $owner->id,
                (int) $order->id,
                Order::STATUS_DELIVERED,
            ));
            self::fail('A confirmed order cannot skip directly to delivered.');
        } catch (ValidationException|\LogicException) {
            self::assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
            self::assertSame($eventsBefore, DB::table('outbox_messages')->count());
        }

        $cancelled = $writer->cancel(new TransitionOrderCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $order->id,
            Order::STATUS_CANCELLED,
            reason: 'Customer changed plans',
        ));
        self::assertSame(Order::STATUS_CANCELLED, $cancelled->order->status);
        self::assertNotNull($cancelled->order->cancelled_at);
        self::assertSame('Customer changed plans', $cancelled->order->cancellation_reason);
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function createCommand(
        Tenant $tenant,
        User $actor,
        string $idempotencyKey,
        array $quantities,
        string $deliveryType = 'pickup',
        ?string $customerAddress = null,
        ?string $requestedCurrency = null,
        ?string $couponCode = null,
    ): CreateOrderCommand {
        $items = [];
        foreach ($quantities as $productId => $quantity) {
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'options' => [],
            ];
        }

        return new CreateOrderCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $actor->id,
            idempotencyKey: $idempotencyKey,
            customerName: 'Order customer',
            customerPhone: '+1 416 555 0100',
            customerEmail: 'order.customer@example.test',
            customerAddress: $customerAddress,
            customerNotes: null,
            deliveryType: $deliveryType,
            paymentMethod: 'cash',
            items: $items,
            couponCode: $couponCode,
            source: 'authenticated',
            userId: (string) $actor->id,
            publiclySellableOnly: false,
            requestedCurrency: $requestedCurrency,
        );
    }

    /**
     * @return array{Tenant, User}
     */
    private function orderTenant(string $email = 'order-owner@example.test'): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'delivery_enabled' => true,
            'pickup_enabled' => true,
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->id])->save();

        return [$tenant, $owner];
    }
}
