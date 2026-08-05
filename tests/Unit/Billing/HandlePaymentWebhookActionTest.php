<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Billing\DTOs\WebhookEventClaim;
use App\Domain\Billing\Repositories\WebhookLogRepositoryInterface;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HandlePaymentWebhookActionTest extends TestCase
{
    #[Test]
    public function payment_webhook_requires_an_explicit_currency_instead_of_assuming_honduras(): void
    {
        $webhook = PaymentWebhook::fromArray('payment.success', [
            'id' => 'evt_missing_currency',
            'meta' => ['tenant_id' => 'tenant-1', 'provider' => 'manual_gateway'],
            'amount' => 1000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing currency');

        $webhook->currency();
    }

    #[Test]
    public function it_marks_a_claimed_event_failed_and_rethrows_when_dispatch_fails(): void
    {
        $repository = new class implements WebhookLogRepositoryInterface
        {
            public bool $failed = false;

            public bool $claimUnavailable = false;

            public function hasBeenProcessed(string $provider, string $externalEventId): bool
            {
                return false;
            }

            public function recordFirstSeen(string $provider, string $externalEventId, string $eventType, array $payload): bool
            {
                return true;
            }

            public function claim(string $provider, string $externalEventId, string $eventType, array $payload, int $leaseSeconds = 300): ?WebhookEventClaim
            {
                return $this->claimUnavailable ? null : new WebhookEventClaim($provider, $externalEventId, 'test-token');
            }

            public function markProcessed(WebhookEventClaim $claim): bool
            {
                return true;
            }

            public function markFailed(WebhookEventClaim $claim, \Throwable $error): bool
            {
                $this->failed = true;

                return true;
            }

            public function log(string $provider, string $externalEventId, string $eventType, array $payload): void {}
        };

        $action = new HandlePaymentWebhookAction(
            new class implements EventBusInterface
            {
                public function dispatch(object $event): void
                {
                    throw new RuntimeException('settlement failed');
                }
            },
            new class implements LoggerInterface
            {
                public function info(string $message, array $context = []): void {}

                public function warning(string $message, array $context = []): void {}

                public function error(string $message, array $context = []): void {}

                public function debug(string $message, array $context = []): void {}

                public function critical(string $message, array $context = []): void {}
            },
            $repository,
        );

        $this->expectException(RuntimeException::class);

        try {
            $action->handle(PaymentWebhook::fromArray('payment.success', [
                'id' => 'evt_action_failure',
                'meta' => ['tenant_id' => 'tenant-1', 'provider' => 'manual_gateway'],
            ]));
        } finally {
            self::assertTrue($repository->failed);
        }
    }

    #[Test]
    public function it_preserves_the_dispatch_exception_when_marking_failed_also_errors(): void
    {
        $repository = new class implements WebhookLogRepositoryInterface
        {
            public bool $claimUnavailable = false;

            public function hasBeenProcessed(string $provider, string $externalEventId): bool
            {
                return false;
            }

            public function recordFirstSeen(string $provider, string $externalEventId, string $eventType, array $payload): bool
            {
                return true;
            }

            public function claim(string $provider, string $externalEventId, string $eventType, array $payload, int $leaseSeconds = 300): ?WebhookEventClaim
            {
                return $this->claimUnavailable ? null : new WebhookEventClaim($provider, $externalEventId, 'test-token');
            }

            public function markProcessed(WebhookEventClaim $claim): bool
            {
                return true;
            }

            public function markFailed(WebhookEventClaim $claim, \Throwable $error): bool
            {
                throw new RuntimeException('failure marker unavailable');
            }

            public function log(string $provider, string $externalEventId, string $eventType, array $payload): void {}
        };

        $logger = new class implements LoggerInterface
        {
            public int $errors = 0;

            public function info(string $message, array $context = []): void {}

            public function warning(string $message, array $context = []): void {}

            public function error(string $message, array $context = []): void
            {
                $this->errors++;

                throw new RuntimeException('secondary logger failure');
            }

            public function debug(string $message, array $context = []): void {}

            public function critical(string $message, array $context = []): void {}
        };

        $action = new HandlePaymentWebhookAction(
            new class implements EventBusInterface
            {
                public function dispatch(object $event): void
                {
                    throw new RuntimeException('original dispatch failure');
                }
            },
            $logger,
            $repository,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('original dispatch failure');

        try {
            $action->handle(PaymentWebhook::fromArray('payment.success', [
                'id' => 'evt_mark_failed_failure',
                'meta' => ['tenant_id' => 'tenant-1', 'provider' => 'manual_gateway'],
            ]));
        } finally {
            self::assertSame(1, $logger->errors);
        }
    }
}
