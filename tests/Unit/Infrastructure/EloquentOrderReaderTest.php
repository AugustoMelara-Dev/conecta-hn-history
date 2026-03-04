<?php

declare(strict_types=1);

use App\Infrastructure\Sales\EloquentOrderReader;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

describe('EloquentOrderReader Tenant Isolation', function () {

    it('returns null when accessing order from different tenant (IDOR Protection)', function () {
        // Arrange: Create two tenants
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        
        // Create an order belonging to Tenant A
        $orderA = Order::factory()->for($tenantA)->create();
        
        // Act: Tenant B tries to access Tenant A's order
        $reader = new EloquentOrderReader();
        $result = $reader->find($tenantB->id, $orderA->id);
        
        // Assert: Must return null (IDOR protected)
        expect($result)->toBeNull();
    });

    it('returns order when accessing own tenant order', function () {
        // Arrange
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->for($tenant)->create();
        
        // Act
        $reader = new EloquentOrderReader();
        $result = $reader->find($tenant->id, $order->id);
        
        // Assert
        expect($result)->not->toBeNull();
        expect($result->id)->toBe($order->id);
    });

    it('returns null for non-existent order ID', function () {
        $tenant = Tenant::factory()->create();
        
        $reader = new EloquentOrderReader();
        $result = $reader->find($tenant->id, 999999);
        
        expect($result)->toBeNull();
    });

});
