<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface as ApplicationLogger;
use App\Domain\Billing\Repositories\WebhookLogRepositoryInterface;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class PaymentWebhookControllerSecurityTest extends TestCase
{
    #[Test]
    public function it_fails_closed_when_the_webhook_secret_is_missing(): void
    {
        $controller = new PaymentWebhookController(
            $this->webhookAction(),
            new NullLogger,
            '',
        );

        $request = Request::create('/api/webhooks/payment', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', '{}', ''),
        ], '{}');

        $response = $controller->handle($request);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Webhook verification is not configured'],
            json_decode((string) $response->getContent(), true),
        );
    }

    private function webhookAction(): HandlePaymentWebhookAction
    {
        $logger = new class implements ApplicationLogger
        {
            public function info(string $message, array $context = []): void {}

            public function warning(string $message, array $context = []): void {}

            public function error(string $message, array $context = []): void {}

            public function debug(string $message, array $context = []): void {}

            public function critical(string $message, array $context = []): void {}
        };

        return new HandlePaymentWebhookAction(
            new class implements EventBusInterface
            {
                public function dispatch(object $event): void {}
            },
            $logger,
            new class implements WebhookLogRepositoryInterface
            {
                public function hasBeenProcessed(string $provider, string $externalEventId): bool
                {
                    return false;
                }

                public function recordFirstSeen(string $provider, string $externalEventId, string $eventType, array $payload): bool
                {
                    return true;
                }

                public function log(string $provider, string $externalEventId, string $eventType, array $payload): void {}
            },
        );
    }
}
