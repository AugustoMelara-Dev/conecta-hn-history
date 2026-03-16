<?php

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('create order handler persists order and items securely to the database', function () {
    $user = User::factory()->create([
        'name' => 'Integration Tester',
        'email' => 'integration@example.test',
    ]);

    $tenant = Tenant::factory()->forUser($user)->create([
        'name' => 'Integration Tenant',
    ]);

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $product = Product::factory()
        ->forTenant($tenant)
        ->withStock(20)
        ->create([
            'name' => 'Integration Burger',
            'price' => 150.00,
        ]);

    $command = new CreateOrderCommand(
        tenantId: (string) $tenant->id,
        customerId: (string) $user->id,
        currency: 'HNL',
        customerName: 'Integration Tester',
        customerPhone: '98765432',
        customerEmail: 'integration@example.test',
        customerAddress: null,
        customerNotes: 'Test order',
        deliveryType: 'pickup',
        paymentMethod: 'cash',
        items: [
            [
                'product_id' => $product->id,
                'name' => 'Integration Burger',
                'price' => 150.00,
                'quantity' => 2,
            ],
        ],
        couponCode: null,
        requestorId: (string) $user->id,
    );

    // Dynamic mock injection keeps the integration path real while bypassing the
    // missing phpredis extension behind the idempotency decorator in tests.
    $reflection = new \ReflectionClass(\App\Application\Sales\Decorators\IdempotentCreateOrderHandler::class);
    if ($constructor = $reflection->getConstructor()) {
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType) {
                continue;
            }

            if (! str_ends_with($type->getName(), 'IdempotencyServiceInterface')) {
                continue;
            }

            $lock = \Mockery::mock();
            $lock->shouldReceive('get')->andReturnTrue();
            $lock->shouldReceive('release')->andReturnNull();

            $mock = \Mockery::mock($type->getName())->shouldIgnoreMissing();
            $mock->shouldReceive('get')->andReturnNull();
            $mock->shouldReceive('lock')->andReturn($lock);
            $mock->shouldReceive('store')->andReturnNull();

            app()->instance($type->getName(), $mock);
        }
    }

    $tenantContext = app(\App\Domain\Shared\Services\TenantContext::class);
    $tenantContext->reset();
    $tenantContext->setTenant((string) $tenant->id);

    /** @var CreateOrderHandlerInterface $handler */
    $handler = app(CreateOrderHandlerInterface::class);
    $result = $handler->handle($command);

    expect($result->id)->not->toBeNull()
        ->and($result->orderNumber)->toStartWith('#')
        ->and($result->totalAmount)->toBe(300.0);

    $this->assertDatabaseHas('orders', [
        'id' => $result->id,
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'customer_name' => 'Integration Tester',
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $result->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'product_name' => 'Integration Burger',
    ]);
});
