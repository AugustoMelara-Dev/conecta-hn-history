<?php

namespace Tests\Feature\Billing;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Handlers\ReportPaymentHandlerInterface;
use App\Application\Shared\Common\Contracts\RateLimiterInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BillingReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(\App\Domain\Shared\Services\TenantContext::class)->setAsSystemRoot(isConsoleOrTesting: true);

        DB::table('tenants')->insert([
            'id' => '00000000-0000-0000-0000-000000000123',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-slug',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Use standard Storage Fake
        Storage::fake('s3');

        // Mock the Document Storage Service Interface
        $mockStorage = Mockery::mock(DocumentStorageServiceInterface::class);
        $mockStorage->shouldReceive('verifyPresence')->andReturn(null);
        $mockStorage->shouldReceive('storeProofOfPayment')->andReturn('permanent/proof.jpg');

        $this->app->instance(DocumentStorageServiceInterface::class, $mockStorage);

        // Let the ServiceProvider resolve the Handler via container.
        // This ensures the Decorator Pipeline (RateLimiter -> Handler) is active.
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_continues_payment_processing_even_if_rate_limiter_fails_fail_open()
    {
        // ARRANGE: Force RateLimiter to throw (simulating Redis failure)
        $mockLimiter = $this->mock(RateLimiterInterface::class);
        $mockLimiter->shouldReceive('tooManyAttempts')->andThrow(new Exception('Redis Connection Refused'));

        $command = new ReportPaymentCommand(
            tenantId: '00000000-0000-0000-0000-000000000123',
            amountInCents: 1000,
            currency: 'USD',
            bank: 'Bank of America',
            paymentReference: 'REF-999',
            proofOfPaymentPath: 'temp/00000000-0000-0000-0000-000000000123/proof.jpg',
            idempotencyKey: '00000000-0000-0000-0000-000000000001'
        );

        // ACT: Resolve handler from container (tests decorator pipeline)
        $handler = $this->app->make(ReportPaymentHandlerInterface::class);
        $result = $handler->handle($command);

        // ASSERT: Payment processed despite rate limiter failure (fail-open)
        $this->assertNotNull($result);
        $this->assertEquals(1000, $result->amountInCents); // Flat DTO property
        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => '00000000-0000-0000-0000-000000000123',
            'amount' => 1000,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_duplicate_requests_idempotently()
    {
        // ARRANGE
        $handler = $this->app->make(ReportPaymentHandlerInterface::class);

        $command = new ReportPaymentCommand(
            tenantId: '00000000-0000-0000-0000-000000000123',
            amountInCents: 5000,
            currency: 'HNL',
            bank: 'Banco Atlantida',
            paymentReference: 'REF-IDEMP-1',
            proofOfPaymentPath: 'temp/00000000-0000-0000-0000-000000000123/proof2.jpg',
            idempotencyKey: '00000000-0000-0000-0000-000000000002'
        );

        // ACT: Submit same command twice
        $result1 = $handler->handle($command);
        $result2 = $handler->handle($command);

        // ASSERT: Only one record, same ID returned
        $this->assertDatabaseCount('subscription_payments', 1);
        $this->assertEquals($result1->id, $result2->id); // Flat DTO property
    }
}
