<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\Shared\Contracts\PaginatedResult;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderLocationResolutionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_the_primary_active_location_when_checkout_omits_location_id(): void
    {
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
        $handler = new RecordingPublicCreateOrderHandler(new CreateOrderResult(
            id: 9001,
            orderNumber: '#9001',
            totalAmount: 100.0,
            status: 'pending',
            itemsCount: 1,
            currency: 'HNL',
            items: [],
        ));

        $this->app->instance(CreateOrderHandlerInterface::class, $handler);
        $this->app->instance(OrderReader::class, new StubPublicOrderReader());

        $response = $this->actingAs($user)->postJson('/api/orders', [
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
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order_id', 9001);

        self::assertCount(1, $handler->commands);
        self::assertSame((string) $tenant->id, $handler->commands[0]->tenantId);
        self::assertSame((string) $user->id, $handler->commands[0]->customerId);
        self::assertSame((string) $primaryLocation->id, $handler->commands[0]->locationId);
    }

    #[Test]
    public function it_rejects_invalid_or_inactive_locations_at_the_checkout_boundary(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();
        $foreignTenant = Tenant::factory()->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $product = Product::factory()->forTenant($tenant)->create();
        $handler = new RecordingPublicCreateOrderHandler(new CreateOrderResult(
            id: 42,
            orderNumber: '#42',
            totalAmount: 100.0,
            status: 'pending',
            itemsCount: 1,
            currency: 'HNL',
            items: [],
        ));

        $this->app->instance(CreateOrderHandlerInterface::class, $handler);
        $this->app->instance(OrderReader::class, new StubPublicOrderReader());

        $foreignLocation = Location::query()->create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'tenant_id' => (string) $tenant->id,
            'location_id' => (string) $foreignLocation->id,
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

        self::assertCount(0, $handler->commands);
    }

    #[Test]
    public function it_rejects_inactive_or_foreign_locations_inside_the_handler(): void
    {
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

        /** @var CreateOrderHandler $handler */
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

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Invalid or inactive location {$inactiveLocation->id}");

        try {
            $handler->handle($inactiveCommand);
        } catch (DomainException $exception) {
            self::assertSame("Invalid or inactive location {$inactiveLocation->id}", $exception->getMessage());

            $this->expectException(DomainException::class);
            $this->expectExceptionMessage("Invalid or inactive location {$foreignLocation->id}");
            $handler->handle($foreignCommand);
        }
    }
}

final class RecordingPublicCreateOrderHandler implements CreateOrderHandlerInterface
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

final class StubPublicOrderReader implements OrderReader
{
    public function __construct(private readonly ?object $order = null) {}

    public function search(int|string $tenantId, ?string $status = null, int $perPage = 20): PaginatedResult
    {
        return new EmptyOrderPaginatedResult();
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

final class EmptyOrderPaginatedResult implements PaginatedResult
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
