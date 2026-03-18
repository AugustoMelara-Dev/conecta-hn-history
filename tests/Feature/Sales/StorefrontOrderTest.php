<?php

use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public order controller returns correct json format for cart store', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->forUser($user)->create();
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Location',
        'slug' => 'main-location',
        'address' => 'Tegucigalpa',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $product = Product::factory()->forTenant($tenant)->create();

    $mockResult = new CreateOrderResult(
        id: 101,
        orderNumber: '#1005',
        totalAmount: 200.00,
        status: 'pending',
        itemsCount: 1,
        currency: 'HNL',
        items: [],
    );

    $handlerMock = Mockery::mock(CreateOrderHandlerInterface::class);
    $handlerMock->shouldReceive('handle')->once()->andReturn($mockResult);
    $this->app->instance(CreateOrderHandlerInterface::class, $handlerMock);

    $orderReaderMock = Mockery::mock(OrderReader::class);
    $orderReaderMock->shouldReceive('find')
        ->once()
        ->with($tenant->id, 101)
        ->andReturn(new class
        {
            public function getWhatsAppUrl(): string
            {
                return 'https://wa.me/50412345678?text=test';
            }
        });
    $this->app->instance(OrderReader::class, $orderReaderMock);

    $payload = [
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
                'quantity' => 2,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/orders', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'order_number' => '#1005',
                'order_id' => 101,
                'whatsapp_url' => 'https://wa.me/50412345678?text=test',
            ],
        ]);
});

test('public order controller blocks unsupported delivery types for the tenant', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->forUser($user)->create([
        'business_type' => BusinessType::RETAIL,
    ]);
    $user->forceFill(['tenant_id' => $tenant->id])->save();

    Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Location',
        'slug' => 'main-location',
        'address' => 'Tegucigalpa',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $product = Product::factory()->forTenant($tenant)->create();

    $handlerMock = Mockery::mock(CreateOrderHandlerInterface::class);
    $handlerMock->shouldNotReceive('handle');
    $this->app->instance(CreateOrderHandlerInterface::class, $handlerMock);

    $payload = [
        'tenant_id' => $tenant->id,
        'customer_name' => 'John Doe',
        'customer_phone' => '12345678',
        'customer_email' => $user->email,
        'delivery_type' => 'delivery',
        'payment_method' => 'pending',
        'items' => [
            [
                'product_id' => $product->id,
                'name' => 'Burger',
                'price' => 100,
                'quantity' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/orders', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['delivery_type'])
        ->assertJsonPath('errors.delivery_type.0', 'Este comercio no ofrece entrega a domicilio.');
});
