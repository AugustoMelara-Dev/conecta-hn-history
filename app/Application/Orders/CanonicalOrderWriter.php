<?php

declare(strict_types=1);

namespace App\Application\Orders;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: every bypass is constrained by canonical tenant ownership.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Markets\MarketContext;
use App\Application\Markets\MissingMarketContextException;
use App\Application\Markets\TenantMarketResolver;
use App\Application\Orders\Commands\CreateOrderCommand;
use App\Application\Orders\Commands\TransitionOrderCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CouponValidatorService;
use App\Services\InventoryManager;
use App\Services\PriceCalculatorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative transaction owner for order creation and lifecycle changes.
 */
final readonly class CanonicalOrderWriter
{
    public function __construct(
        private InventoryManager $inventory,
        private PriceCalculatorService $prices,
        private CouponValidatorService $coupons,
        private TenantMarketResolver $marketResolver,
        private TenantPermissionGuardInterface $permissionGuard,
        private OutboxRepositoryInterface $outbox,
    ) {}

    public function create(CreateOrderCommand $command): OrderWriteResult
    {
        $this->assertCreateCommand($command);

        return DB::transaction(function () use ($command): OrderWriteResult {
            $tenant = Tenant::query()
                ->withoutGlobalScopes()
                ->whereKey($command->tenantId)
                ->where('is_active', true)
                ->when(
                    $command->publiclySellableOnly,
                    fn ($query) => $query->whereNull('publication_paused_at'),
                )
                ->lockForUpdate()
                ->firstOrFail();
            $market = $this->market($tenant);
            if ($command->requestedCurrency !== null
                && strtoupper($command->requestedCurrency) !== strtoupper($market->currencyCode)) {
                throw ValidationException::withMessages([
                    'currency' => ['Order currency does not match the tenant market.'],
                ]);
            }
            $actor = $command->actorId === null
                ? null
                : User::query()->withoutGlobalScopes()->findOrFail($command->actorId);
            $user = $command->userId === null
                ? null
                : User::query()->withoutGlobalScopes()->findOrFail($command->userId);
            $payloadHash = $this->hash($command);
            $existing = Order::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Order) {
                if (! hash_equals((string) $existing->idempotency_hash, $payloadHash)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Order idempotency key was reused with another payload.'],
                    ]);
                }

                return new OrderWriteResult($existing, true);
            }

            $lockedProducts = $this->inventory->validateAndLock(
                $command->items,
                $command->tenantId,
                'canonical-order:'.$command->idempotencyKey,
                $command->publiclySellableOnly,
            );
            foreach ($lockedProducts as $product) {
                if ($product instanceof Product
                    && filled($product->currency)
                    && strtoupper((string) $product->currency) !== strtoupper($market->currencyCode)) {
                    throw ValidationException::withMessages([
                        'items' => ['Product currency does not match the tenant market.'],
                    ]);
                }
            }
            $calculatedItems = $this->prices->calculate($command->items, $lockedProducts);
            $subtotal = round((float) collect($calculatedItems)->sum('line_total'), 2);
            $attributes = $tenant->getAttributes();
            $this->assertFulfillment($command, $attributes);
            $minimumOrder = round((float) ($attributes['minimum_order'] ?? 0), 2);
            if ($subtotal < $minimumOrder) {
                throw ValidationException::withMessages([
                    'items' => ["Minimum order is {$minimumOrder} {$market->currencyCode}."],
                ]);
            }

            $coupon = null;
            $discount = 0.0;
            if ($command->couponCode !== null) {
                $coupon = $this->coupons->validate(
                    $command->couponCode,
                    $user,
                    $subtotal,
                    $tenant,
                );
                $discount = round((float) $coupon->calculateDiscountUnsafe($subtotal), 2);
            }

            $order = new Order;
            $order->forceFill([
                'tenant_id' => $command->tenantId,
                'user_id' => $user?->getKey(),
                'order_number' => $this->nextOrderNumber($command->tenantId),
                'tracking_uuid' => (string) Str::uuid(),
                'idempotency_key' => $command->idempotencyKey,
                'idempotency_hash' => $payloadHash,
                'customer_name' => trim($command->customerName),
                'customer_phone' => trim($command->customerPhone),
                'customer_email' => $command->customerEmail,
                'delivery_address' => $command->customerAddress,
                'delivery_type' => $command->deliveryType,
                'customer_notes' => $command->customerNotes,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => 0,
                'total' => max(0, $subtotal - $discount),
                'currency' => strtoupper($market->currencyCode),
                'payment_method' => $command->paymentMethod,
                'payment_status' => 'pending',
                'source' => $command->source,
                'status' => Order::STATUS_PENDING,
            ])->save();

            foreach ($calculatedItems as $item) {
                /** @var Product $product */
                $product = $lockedProducts->get($item['product_id']);
                $unitPrice = (float) $item['unit_price'];
                $basePrice = (float) $product->price;
                $lineTotal = (float) $item['line_total'];
                $options = $item['validated_options'] ?? null;
                $order->items()->create([
                    'product_id' => $product->getKey(),
                    'product_name' => (string) $product->name,
                    'product_sku' => $product->getAttributes()['sku'] ?? null,
                    'product_price' => $basePrice,
                    'unit_price' => $unitPrice,
                    'quantity' => (int) $item['quantity'],
                    'total_price' => $lineTotal,
                    'line_total' => $lineTotal,
                    'selected_options' => $options,
                    'options' => $options,
                    'options_total' => max(0, $unitPrice - $basePrice),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            if ($coupon !== null) {
                $this->coupons->redeem($coupon, $user, (int) $order->getKey());
            }
            $this->audit($actor, $order, 'order.created', [
                'tenant_id' => $command->tenantId,
                'currency' => strtoupper($market->currencyCode),
                'status' => Order::STATUS_PENDING,
            ]);

            return new OrderWriteResult($order->load('items'));
        }, 3);
    }

    public function transition(TransitionOrderCommand $command): OrderWriteResult
    {
        $this->assertTransitionCommand($command);

        return DB::transaction(function () use ($command): OrderWriteResult {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::ORDERS_MANAGE,
                $command->systemInitiated,
            );
            $order = $this->order($command->tenantId, $command->orderId);
            if ($command->targetStatus === Order::STATUS_CANCELLED) {
                return $this->cancelWithinTransaction(
                    $tenant,
                    $actor,
                    $order,
                    $command->reason,
                    $command->systemInitiated,
                );
            }
            $this->assertAllowedTransition((string) $order->status, $command->targetStatus);
            $attributes = ['status' => $command->targetStatus];
            match ($command->targetStatus) {
                Order::STATUS_CONFIRMED => $attributes['confirmed_at'] = now(),
                Order::STATUS_PROCESSING, Order::STATUS_READY => $attributes['prepared_at'] = now(),
                Order::STATUS_DELIVERED => $attributes['delivered_at'] = now(),
                default => null,
            };
            $order->forceFill($attributes)->save();
            $this->audit($actor, $order, 'order.transitioned', [
                'tenant_id' => $command->tenantId,
                'status' => $command->targetStatus,
                'actor_type' => $command->systemInitiated ? 'system' : 'user',
            ]);

            return new OrderWriteResult($order);
        }, 3);
    }

    public function cancel(TransitionOrderCommand $command): OrderWriteResult
    {
        if ($command->targetStatus !== Order::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Order cancellation requires the cancelled target status.');
        }
        $this->assertTransitionCommand($command);

        return DB::transaction(function () use ($command): OrderWriteResult {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::ORDERS_CANCEL,
                $command->systemInitiated,
            );
            $order = $this->order($command->tenantId, $command->orderId);

            return $this->cancelWithinTransaction(
                $tenant,
                $actor,
                $order,
                $command->reason,
                $command->systemInitiated,
            );
        }, 3);
    }

    private function cancelWithinTransaction(
        Tenant $tenant,
        ?User $actor,
        Order $order,
        ?string $reason,
        bool $systemInitiated = false,
    ): OrderWriteResult {
        if ($order->status === Order::STATUS_CANCELLED) {
            return new OrderWriteResult($order, true);
        }
        if ($order->status === Order::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'status' => ['Delivered orders cannot be cancelled.'],
            ]);
        }
        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Cancellation reason is required.'],
            ]);
        }
        $order->forceFill([
            'status' => Order::STATUS_CANCELLED,
            'cancellation_reason' => trim((string) $reason),
            'cancelled_at' => now(),
        ])->save();
        $this->inventory->restock($order);
        $this->audit($actor, $order, 'order.cancelled', [
            'tenant_id' => (string) $tenant->getKey(),
            'status' => Order::STATUS_CANCELLED,
            'actor_type' => $systemInitiated ? 'system' : 'user',
        ]);

        return new OrderWriteResult($order);
    }

    private function assertCreateCommand(CreateOrderCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ($command->actorId !== null && ! Str::isUuid($command->actorId))
            || trim($command->idempotencyKey) === ''
            || mb_strlen($command->idempotencyKey) > 64
            || trim($command->customerName) === ''
            || trim($command->customerPhone) === ''
            || ($command->requestedCurrency !== null
                && preg_match('/^[A-Z]{3}$/', strtoupper($command->requestedCurrency)) !== 1)
            || $command->items === []) {
            throw new \InvalidArgumentException(
                'Order creation requires tenant, idempotency, customer, and item data.',
            );
        }
        foreach ($command->items as $item) {
            if (! isset($item['product_id'], $item['quantity'])
                || (int) $item['product_id'] < 1
                || (int) $item['quantity'] < 1) {
                throw ValidationException::withMessages([
                    'items' => ['Each order item requires product and positive quantity.'],
                ]);
            }
        }
    }

    private function assertTransitionCommand(TransitionOrderCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ($command->systemInitiated && $command->actorId !== null)
            || (! $command->systemInitiated && ! Str::isUuid((string) $command->actorId))
            || $command->orderId < 1
            || ! in_array($command->targetStatus, Order::STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Order transition requires tenant, actor, order, and valid target status.',
            );
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertFulfillment(CreateOrderCommand $command, array $attributes): void
    {
        if (! in_array($command->deliveryType, ['pickup', 'delivery'], true)) {
            throw ValidationException::withMessages([
                'delivery_type' => ['Unsupported fulfillment method.'],
            ]);
        }
        $deliveryEnabled = (bool) ($attributes['delivery_enabled'] ?? true);
        $pickupEnabled = (bool) ($attributes['pickup_enabled'] ?? true);
        if (($command->deliveryType === 'delivery' && ! $deliveryEnabled)
            || ($command->deliveryType === 'pickup' && ! $pickupEnabled)) {
            throw ValidationException::withMessages([
                'delivery_type' => ['Selected fulfillment method is unavailable.'],
            ]);
        }
        if ($command->deliveryType === 'delivery' && trim((string) $command->customerAddress) === '') {
            throw ValidationException::withMessages([
                'customer_address' => ['Delivery address is required.'],
            ]);
        }
    }

    private function assertAllowedTransition(string $from, string $to): void
    {
        $allowed = [
            Order::STATUS_PENDING => [Order::STATUS_CONFIRMED],
            Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING],
            Order::STATUS_PROCESSING => [Order::STATUS_READY],
            Order::STATUS_READY => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELLED => [],
        ];
        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Order cannot transition from {$from} to {$to}."],
            ]);
        }
    }

    /**
     * @return array{Tenant, User|null}
     */
    private function authority(
        string $tenantId,
        ?string $actorId,
        Permission $permission,
        bool $systemInitiated = false,
    ): array {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
        if ($systemInitiated) {
            return [$tenant, null];
        }
        $actor = User::query()->withoutGlobalScopes()->findOrFail($actorId);
        if (! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::CHECKOUT_BASIC,
            $permission,
        )) {
            throw new AuthorizationException('Actor cannot transition orders for this tenant.');
        }

        return [$tenant, $actor];
    }

    private function order(string $tenantId, int $orderId): Order
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->findOrFail($orderId);
    }

    private function nextOrderNumber(string $tenantId): string
    {
        $last = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        return '#'.($last instanceof Order
            ? ((int) str_replace('#', '', (string) $last->order_number)) + 1
            : 1001);
    }

    private function market(Tenant $tenant): MarketContext
    {
        try {
            return $this->marketResolver->resolve($tenant);
        } catch (MissingMarketContextException) {
            throw ValidationException::withMessages([
                'market' => ['Tenant market context is required for order money.'],
            ]);
        }
    }

    private function hash(CreateOrderCommand $command): string
    {
        return hash('sha256', json_encode([
            'tenant_id' => $command->tenantId,
            'customer_name' => trim($command->customerName),
            'customer_phone' => trim($command->customerPhone),
            'customer_email' => $command->customerEmail,
            'customer_address' => $command->customerAddress,
            'customer_notes' => $command->customerNotes,
            'delivery_type' => $command->deliveryType,
            'payment_method' => $command->paymentMethod,
            'items' => $command->items,
            'coupon_code' => $command->couponCode,
            'user_id' => $command->userId,
            'source' => $command->source,
            'publicly_sellable_only' => $command->publiclySellableOnly,
            'requested_currency' => $command->requestedCurrency === null
                ? null
                : strtoupper($command->requestedCurrency),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, scalar|null>  $properties
     */
    private function audit(?User $actor, Order $order, string $event, array $properties): void
    {
        $activity = activity('business-operations')
            ->performedOn($order)
            ->withProperties($properties)
            ->event($event);
        if ($actor instanceof User) {
            $activity->causedBy($actor);
        }
        $activity->log($event);

        $this->outbox->append(new BusinessOperationChanged(
            aggregateId: 'order:'.$order->getKey(),
            aggregateType: 'order',
            tenantId: (string) ($properties['tenant_id'] ?? ''),
            actorId: $actor instanceof User
                ? (string) $actor->getKey()
                : (($properties['actor_type'] ?? null) === 'system' ? 'system' : 'guest'),
            eventName: $event,
            attributes: $properties,
            occurredAt: now()->toImmutable(),
        ));
    }
}
