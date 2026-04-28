<?php

declare(strict_types=1);

namespace App\Application\Operations;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final readonly class KitchenDisplayReadModel
{
    public function __construct(
        private CapabilityManagerInterface $capabilityManager,
    ) {}

    /**
     * @return array{
     *   enabled: bool,
     *   reason: string,
     *   columns: array<string, list<array<string, mixed>>>
     * }
     */
    public function forTenant(Tenant $tenant, ?string $locationId = null): array
    {
        if (! $this->capabilityManager->has($tenant, Capability::KITCHEN_DISPLAY)) {
            return [
                'enabled' => false,
                'reason' => 'Kitchen display is not enabled for this business type.',
                'columns' => [],
            ];
        }

        $orders = Order::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->when($locationId !== null && $locationId !== '', fn ($query) => $query->where('location_id', $locationId))
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_READY])
            ->with(['items'])
            ->orderBy('created_at')
            ->get();
        $locations = Location::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->pluck('name', 'id');

        return [
            'enabled' => true,
            'reason' => 'Kitchen display is backed by active orders and RubroManifest capability.',
            'columns' => $orders
                ->groupBy('status')
                ->map(fn (Collection $group): array => $group->map(fn (Order $order): array => [
                    'id' => (string) $order->getKey(),
                    'order_number' => (string) $order->order_number,
                    'status' => (string) $order->status,
                    'customer_name' => (string) $order->customer_name,
                    'location' => (string) ($locations[(string) $order->location_id] ?? 'Principal'),
                    'created_at' => $order->created_at?->toISOString(),
                    'items' => $order->items->map(fn ($item): array => [
                        'name' => (string) $item->product_name,
                        'quantity' => (int) $item->quantity,
                        'notes' => (string) ($item->notes ?? ''),
                    ])->values()->all(),
                ])->values()->all())
                ->all(),
        ];
    }
}
