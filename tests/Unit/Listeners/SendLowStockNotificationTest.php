<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Domain\Shared\Services\TenantContext;
use App\Events\Inventory\LowStockThresholdReached;
use App\Listeners\SendLowStockNotification;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SendLowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->setAsSystemRoot();
    }

    #[Test]
    public function it_processes_a_product_only_with_its_exact_tenant_uuid(): void
    {
        $fixture = $this->fixture();
        $event = new LowStockThresholdReached($fixture['productA'], 5, 10);

        $fixture['listener']->handle($event);

        self::assertTrue(cache()->has(
            'low_stock_alert_sent_'.$fixture['tenantA']->getKey().'_'.$fixture['productA']->getKey().'_'.now()->format('Y-m-d')
        ));
    }

    #[Test]
    public function it_blocks_a_product_from_another_tenant_and_logs_the_probe(): void
    {
        $fixture = $this->fixture();
        $spoofedProduct = clone $fixture['productA'];
        $spoofedProduct->tenant_id = (string) $fixture['tenantB']->id;
        $event = new LowStockThresholdReached($spoofedProduct, 5, 10);

        Log::shouldReceive('channel')
            ->with('security')
            ->once()
            ->andReturnSelf();
        Log::shouldReceive('critical')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'Cross-Tenant Access Attempt BLOCKED')
                && ($context['severity'] ?? null) === 'CRITICAL');

        $fixture['listener']->handle($event);

        self::assertFalse(cache()->has(
            'low_stock_alert_sent_'.$fixture['tenantB']->getKey().'_'.$fixture['productA']->getKey().'_'.now()->format('Y-m-d')
        ));
    }

    #[Test]
    public function it_returns_safely_when_the_product_no_longer_exists(): void
    {
        $fixture = $this->fixture();
        $missingProduct = clone $fixture['productA'];
        $missingProduct->id = 99999999;
        $event = new LowStockThresholdReached($missingProduct, 5, 10);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(static fn (string $message): bool => str_contains($message, 'not found'));

        $fixture['listener']->handle($event);

        self::assertFalse(cache()->has(
            'low_stock_alert_sent_'.$fixture['tenantA']->getKey().'_99999999_'.now()->format('Y-m-d')
        ));
    }

    /**
     * @return array{tenantA: Tenant, tenantB: Tenant, productA: Product, listener: SendLowStockNotification}
     */
    private function fixture(): array
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        $productA = Product::factory()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Product A',
            'stock_quantity' => 5,
        ]);

        return [
            'tenantA' => $tenantA,
            'tenantB' => $tenantB,
            'productA' => $productA,
            'listener' => new SendLowStockNotification,
        ];
    }
}
