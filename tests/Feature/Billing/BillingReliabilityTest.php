<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Handlers\ReportPaymentHandlerInterface;
use App\Application\Shared\Contracts\RateLimiterInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BillingReliabilityTest extends TestCase
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

        Storage::fake('s3');
        $this->app->instance(DocumentStorageServiceInterface::class, new StubDocumentStorageService);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_continues_processing_payments_when_the_rate_limiter_fails_open(): void
    {
        $this->app->instance(RateLimiterInterface::class, new ThrowingRateLimiter);

        $command = new ReportPaymentCommand(
            tenantId: '00000000-0000-0000-0000-000000000123',
            amountInCents: 1000,
            currency: 'USD',
            bank: 'Bank of America',
            paymentReference: 'REF-999',
            proofOfPaymentPath: 'temp/00000000-0000-0000-0000-000000000123/proof.jpg',
            idempotencyKey: '00000000-0000-0000-0000-000000000001'
        );

        /** @var ReportPaymentHandlerInterface $handler */
        $handler = $this->app->make(ReportPaymentHandlerInterface::class);
        $result = $handler->handle($command);

        $this->assertSame(1000, $result->amountInCents);
        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => '00000000-0000-0000-0000-000000000123',
            'amount' => 1000,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_duplicate_requests_idempotently(): void
    {
        /** @var ReportPaymentHandlerInterface $handler */
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

        $result1 = $handler->handle($command);
        $result2 = $handler->handle($command);

        $this->assertDatabaseCount('subscription_payments', 1);
        $this->assertSame($result1->id, $result2->id);
    }
}

final class StubDocumentStorageService implements DocumentStorageServiceInterface
{
    public function verifyPresence(string $path): void {}

    public function storeProofOfPayment(string $sourcePath, string $paymentReference): string
    {
        return 'permanent/proof.jpg';
    }

    public function promote(string $tempPath): string
    {
        return 'permanent/proof.jpg';
    }

    public function delete(string $path): void {}
}

final class ThrowingRateLimiter implements RateLimiterInterface
{
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        throw new Exception('Redis Connection Refused');
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        return 1;
    }

    public function availableIn(string $key): int
    {
        return 0;
    }

    public function clear(string $key): void {}
}
