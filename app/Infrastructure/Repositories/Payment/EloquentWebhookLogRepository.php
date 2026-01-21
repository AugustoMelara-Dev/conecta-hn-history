<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Payment;

use App\Domain\Payment\Repositories\WebhookLogRepositoryInterface;
use App\Models\WebhookLog;

/**
 * EloquentWebhookLogRepository - Infrastructure Adapter for Webhook Audit Trail
 *
 * Provides idempotency guard via unique compound index (provider, external_event_id).
 */
final class EloquentWebhookLogRepository implements WebhookLogRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function hasBeenProcessed(string $provider, string $externalEventId): bool
    {
        return WebhookLog::where('provider', $provider)
            ->where('external_event_id', $externalEventId)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function log(string $provider, string $externalEventId, string $eventType, array $payload): void
    {
        WebhookLog::create([
            'provider' => $provider,
            'external_event_id' => $externalEventId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }
}
