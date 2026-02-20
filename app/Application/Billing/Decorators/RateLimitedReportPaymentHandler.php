<?php

declare(strict_types=1);

namespace App\Application\Billing\Decorators;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Handlers\ReportPaymentHandlerInterface;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Shared\Contracts\RateLimiterInterface;
use Illuminate\Validation\ValidationException;

/**
 * RateLimitedReportPaymentHandler - DoS Protection (Level 28)
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  DEFENSE: TENANT-AWARE RATE LIMITING                                     â•‘
 * â•‘  1. Limits: 10 requests per minute per tenant.                           â•‘
 * â•‘  2. Protects: S3 Upload Bandwidth & Database Connections.                â•‘
 * â•‘  3. Storage: Redis (Atomic).                                             â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class RateLimitedReportPaymentHandler implements ReportPaymentHandlerInterface
{
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private ReportPaymentHandlerInterface $inner,
        private RateLimiterInterface $rateLimiter
    ) {}

    public function handle(ReportPaymentCommand $command): ReportPaymentResult
    {
        $key = 'billing:report_payment:'.$command->tenantId;

        try {
            if ($this->rateLimiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $seconds = $this->rateLimiter->availableIn($key);

                throw ValidationException::withMessages([
                    'tenant_id' => ["Rate limit exceeded. Please try again in {$seconds} seconds."],
                ]);
            }

            $this->rateLimiter->hit($key, self::DECAY_SECONDS);
        } catch (ValidationException $e) {
            throw $e; // Respect validation errors (429)
        } catch (\Throwable $e) {
            // CIRCUIT BREAKER: Fail Open (Level 33)
            // If Redis is down, we MUST NOT block payment reporting (Revenue).
            // We log the failure as critical but allow the request to pass.
            \Illuminate\Support\Facades->logger->critical('Rate Limiter FAILURE in ReportPayment (Fail-Open active): '.$e->getMessage());
        }

        return $this->inner->handle($command);
    }
}
