<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_stream_and_download_its_order_receipt(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();
        $order = Order::factory()->forTenant($tenant)->create(['currency' => 'HNL']);

        $this->actingAs($owner)
            ->get(route('orders.print-ticket', ['order' => $order->getKey()], false))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($owner)
            ->get(route('receipt.download', ['order' => $order->getKey()], false))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_tenant_cannot_read_another_tenants_receipt(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();
        $foreignOrder = Order::factory()->forTenant(Tenant::factory()->create())->create();

        $this->actingAs($owner)
            ->get(route('receipt.download', ['order' => $foreignOrder->getKey()], false))
            ->assertNotFound();
    }
}
