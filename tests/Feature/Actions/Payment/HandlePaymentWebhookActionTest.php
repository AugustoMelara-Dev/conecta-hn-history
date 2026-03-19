<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Payment;

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HandlePaymentWebhookActionTest extends TestCase
{
    #[Test]
    public function action_implements_handler_interface(): void
    {
        $action = new HandlePaymentWebhookAction(
            new RecordingEventBus,
            new RecordingLogger,
        );

        self::assertInstanceOf(PaymentWebhookHandlerInterface::class, $action);
    }

    #[Test]
    public function handle_method_dispatches_event_to_ledger(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = [
            'meta' => ['tenant_id' => 'tenant_123'],
            'id' => 'evt_123',
        ];

        $webhook = PaymentWebhook::fromArray('payment.success', $payload);
        $eventBus = new RecordingEventBus;
        $logger = new RecordingLogger;

        $action = new HandlePaymentWebhookAction($eventBus, $logger);
        $result = $action->handle($webhook);

        self::assertCount(1, $eventBus->dispatchedEvents);
        self::assertInstanceOf(PaymentWebhookReceived::class, $eventBus->dispatchedEvents[0]);
        self::assertSame($webhook, $eventBus->dispatchedEvents[0]->webhook);

        self::assertCount(1, $logger->infoLogs);
        self::assertSame('Processing Webhook via Handler', $logger->infoLogs[0]['message']);
        self::assertSame('tenant_123', $logger->infoLogs[0]['context']['tenant_id'] ?? null);
        self::assertSame('payment.success', $logger->infoLogs[0]['context']['event'] ?? null);

        self::assertSame('acknowledged', $result['status']);
        self::assertSame('tenant_123', $result['tenant_id']);
        self::assertTrue($result['processed']);
        self::assertIsString($result['timestamp']);
    }
}

final class RecordingEventBus implements EventBusInterface
{
    /** @var list<object> */
    public array $dispatchedEvents = [];

    public function dispatch(object $event): void
    {
        $this->dispatchedEvents[] = $event;
    }
}

final class RecordingLogger implements LoggerInterface
{
    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $infoLogs = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $warningLogs = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $errorLogs = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $debugLogs = [];

    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $criticalLogs = [];

    public function info(string $message, array $context = []): void
    {
        $this->infoLogs[] = ['message' => $message, 'context' => $context];
    }

    public function warning(string $message, array $context = []): void
    {
        $this->warningLogs[] = ['message' => $message, 'context' => $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->errorLogs[] = ['message' => $message, 'context' => $context];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->debugLogs[] = ['message' => $message, 'context' => $context];
    }

    public function critical(string $message, array $context = []): void
    {
        $this->criticalLogs[] = ['message' => $message, 'context' => $context];
    }
}
