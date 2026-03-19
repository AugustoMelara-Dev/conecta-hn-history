<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateOrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_orders_and_items_through_the_real_handler(): void
    {
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

        $location = Location::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Principal',
            'slug' => 'principal',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->app->instance(IdempotencyServiceInterface::class, new IntegrationIdempotencyService());

        $command = new CreateOrderCommand(
            tenantId: (string) $tenant->id,
            locationId: (string) $location->id,
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

        $tenantContext = app(TenantContext::class);
        $tenantContext->reset();
        $tenantContext->setTenant((string) $tenant->id);

        /** @var CreateOrderHandlerInterface $handler */
        $handler = app(CreateOrderHandlerInterface::class);
        $result = $handler->handle($command);

        self::assertNotSame(0, $result->id);
        self::assertStringStartsWith('#', $result->orderNumber);
        self::assertSame(300.0, $result->totalAmount);

        $this->assertDatabaseHas('orders', [
            'id' => $result->id,
            'tenant_id' => (string) $tenant->id,
            'location_id' => (string) $location->id,
            'user_id' => (string) $user->id,
            'customer_name' => 'Integration Tester',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $result->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'product_name' => 'Integration Burger',
        ]);
    }
}

final class IntegrationIdempotencyService implements IdempotencyServiceInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, string> */
    private array $states = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function getState(string $key): ?string
    {
        return $this->states[$key] ?? null;
    }

    public function registerProcessing(string $key, int $ttlSeconds = 60): void
    {
        if (isset($this->states[$key])) {
            throw ConcurrentRequestException::forKey($key);
        }

        $this->states[$key] = 'PROCESSING';
    }

    public function store(string $key, mixed $value, int $ttlSeconds = 86400): void
    {
        $this->values[$key] = $value;
        $this->states[$key] = 'COMPLETED';
    }
}
