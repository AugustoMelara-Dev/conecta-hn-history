<?php

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Domain\Sales\Contracts\OrderReader;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the primary active location when checkout omits location_id', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->forUser($user)->create();
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $primaryLocation = Location::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Sucursal Centro',
        'slug' => 'sucursal-centro',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $product = Product::factory()->forTenant($tenant)->create();

    $handlerMock = Mockery::mock(CreateOrderHandlerInterface::class);
    $handlerMock->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(function (CreateOrderCommand $command) use ($tenant, $user, $primaryLocation): bool {
            return $command->tenantId === (string) $tenant->id
                && $command->customerId === (string) $user->id
                && $command->locationId === (string) $primaryLocation->id;
        }))
        ->andReturn(new CreateOrderResult(
            id: 9001,
            orderNumber: '#9001',
            totalAmount: 100.0,
            status: 'pending',
            itemsCount: 1,
            currency: 'HNL',
            items: [],
        ));
    $this->app->instance(CreateOrderHandlerInterface::class, $handlerMock);

    $orderReaderMock = Mockery::mock(OrderReader::class);
    $orderReaderMock->shouldReceive('find')->once()->andReturn(null);
    $this->app->instance(OrderReader::class, $orderReaderMock);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'tenant_id' => $tenant->id,
        'customer_name' => 'John Doe',
        'customer_phone' => '12345678',
        'customer_email' => $user->email,
        'delivery_type' => 'pickup',
        'payment_method' => 'pending',
        'items' => [
            [
                'product_id' => $product->id,
                'name' => 'Burger',
                'price' => 100,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order_id', 9001);
});

it('rejects invalid or inactive locations at the checkout boundary', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->forUser($user)->create();
    $foreignTenant = Tenant::factory()->create();
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    $product = Product::factory()->forTenant($tenant)->create();

    $handlerMock = Mockery::mock(CreateOrderHandlerInterface::class);
    $handlerMock->shouldNotReceive('handle');
    $this->app->instance(CreateOrderHandlerInterface::class, $handlerMock);

    $foreignLocation = Location::query()->create([
        'tenant_id' => $foreignTenant->id,
        'name' => 'Foreign',
        'slug' => 'foreign',
        'is_active' => true,
        'is_primary' => true,
    ]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'tenant_id' => $tenant->id,
        'location_id' => $foreignLocation->id,
        'customer_name' => 'John Doe',
        'customer_phone' => '12345678',
        'customer_email' => $user->email,
        'delivery_type' => 'pickup',
        'payment_method' => 'pending',
        'items' => [
            [
                'product_id' => $product->id,
                'name' => 'Burger',
                'price' => 100,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['location_id'])
        ->assertJsonPath('errors.location_id.0', 'Invalid or inactive location for this tenant.');
});

it('handler rejects inactive or foreign locations', function () {
    $tenant = Tenant::factory()->create();
    $foreignTenant = Tenant::factory()->create();

    $inactiveLocation = Location::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Inactive',
        'slug' => 'inactive',
        'is_active' => false,
        'is_primary' => true,
    ]);

    $foreignLocation = Location::query()->create([
        'tenant_id' => $foreignTenant->id,
        'name' => 'Foreign',
        'slug' => 'foreign',
        'is_active' => true,
        'is_primary' => true,
    ]);

    $handler = app(CreateOrderHandler::class);

    $inactiveCommand = new CreateOrderCommand(
        tenantId: (string) $tenant->id,
        locationId: (string) $inactiveLocation->id,
        customerId: 'customer-uuid',
        currency: 'HNL',
        deliveryType: 'pickup',
        items: [],
    );

    $foreignCommand = new CreateOrderCommand(
        tenantId: (string) $tenant->id,
        locationId: (string) $foreignLocation->id,
        customerId: 'customer-uuid',
        currency: 'HNL',
        deliveryType: 'pickup',
        items: [],
    );

    expect(fn () => $handler->handle($inactiveCommand))
        ->toThrow(DomainException::class, "Invalid or inactive location {$inactiveLocation->id}");

    expect(fn () => $handler->handle($foreignCommand))
        ->toThrow(DomainException::class, "Invalid or inactive location {$foreignLocation->id}");
});
