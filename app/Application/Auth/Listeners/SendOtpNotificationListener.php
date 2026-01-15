<?php

declare(strict_types=1);

namespace App\Application\Auth\Listeners;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * SendOtpNotificationListener - Async Delivery Worker
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ASYNC: Implements ShouldQueue to offload SMS/Email to background workers.║
 * ║  RESILIENT: Depends on OtpNotifierInterface, which is wrapped by        ║
 * ║             CircuitBreakerDecorator for Fail-Fast protection.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class SendOtpNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the queued job may be attempted.
     * Logic: If CircuitBreaker trips, we fail fast. 
     * If transient error (network blip), we retry a few times.
     */
    public $tries = 3;

    public function __construct(
        private readonly OtpNotifierInterface $notifier
    ) {}

    public function handle(OtpGenerated $event): void
    {
        $this->notifier->send(
            otpCode: $event->code,
            recipientIdentifier: $event->identifier,
            recipientName: $event->recipientName,
            expiryMinutes: $event->expiryMinutes
        );
    }
}
