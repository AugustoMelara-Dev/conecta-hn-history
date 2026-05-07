<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Payment;

use App\Domain\Billing\Repositories\WebhookLogRepositoryInterface as BillingWebhookLogRepositoryInterface;
use App\Domain\Payment\Repositories\WebhookLogRepositoryInterface;
use App\Models\WebhookLog;
use Illuminate\Database\QueryException;

/**
 * EloquentWebhookLogRepository - Infrastructure Adapter for Webhook Audit Trail
 *
 * Provides idempotency guard via unique compound index (provider, external_event_id).
 */
final class EloquentWebhookLogRepository implements WebhookLogRepositoryInterface, BillingWebhookLogRepositoryInterface
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
    public function recordFirstSeen(string $provider, string $externalEventId, string $eventType, array $payload): bool
    {
        try {
            $this->log($provider, $externalEventId, $eventType, $payload);

            return true;
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return false;
            }

            throw $exception;
        }
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

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '1555', '2067'], true);
    }
}
