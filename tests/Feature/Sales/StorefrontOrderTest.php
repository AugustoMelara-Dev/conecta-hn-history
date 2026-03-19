<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\Shared\Contracts\PaginatedResult;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontOrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_expected_json_for_public_cart_checkout(): void
    {
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
        $handler = new StorefrontCreateOrderHandler(new CreateOrderResult(
            id: 101,
            orderNumber: '#1005',
            totalAmount: 200.00,
            status: 'pending',
            itemsCount: 1,
            currency: 'HNL',
            items: [],
        ));
        $orderReader = new StorefrontOrderReader(new class
        {
            public function getWhatsAppUrl(): string
            {
                return 'https://wa.me/50412345678?text=test';
            }
        });

        $this->app->instance(CreateOrderHandlerInterface::class, $handler);
        $this->app->instance(OrderReader::class, $orderReader);

        $payload = [
            'tenant_id' => (string) $tenant->id,
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

        self::assertCount(1, $handler->commands);
        self::assertSame((string) $tenant->id, $handler->commands[0]->tenantId);
    }

    #[Test]
    public function it_blocks_unsupported_delivery_types_for_the_tenant(): void
    {
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
        $handler = new StorefrontCreateOrderHandler(new CreateOrderResult(
            id: 202,
            orderNumber: '#202',
            totalAmount: 100.0,
            status: 'pending',
            itemsCount: 1,
            currency: 'HNL',
            items: [],
        ));

        $this->app->instance(CreateOrderHandlerInterface::class, $handler);
        $this->app->instance(OrderReader::class, new StorefrontOrderReader);

        $payload = [
            'tenant_id' => (string) $tenant->id,
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

        self::assertCount(0, $handler->commands);
    }
}

final class StorefrontCreateOrderHandler implements CreateOrderHandlerInterface
{
    /** @var list<CreateOrderCommand> */
    public array $commands = [];

    public function __construct(private readonly CreateOrderResult $result) {}

    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        $this->commands[] = $command;

        return $this->result;
    }
}

final class StorefrontOrderReader implements OrderReader
{
    public function __construct(private readonly ?object $order = null) {}

    public function search(int|string $tenantId, ?string $status = null, int $perPage = 20): PaginatedResult
    {
        return new StorefrontEmptyPaginatedResult;
    }

    public function find(int|string $tenantId, int $orderId): ?object
    {
        return $this->order;
    }

    public function findByStatus(int|string $tenantId, string $status, int $limit = 50): iterable
    {
        return [];
    }
}

final class StorefrontEmptyPaginatedResult implements PaginatedResult
{
    public function items(): array
    {
        return [];
    }

    public function through(callable $callback): PaginatedResult
    {
        return $this;
    }

    public function total(): int
    {
        return 0;
    }

    public function perPage(): int
    {
        return 0;
    }

    public function currentPage(): int
    {
        return 1;
    }

    public function lastPage(): int
    {
        return 1;
    }
}
