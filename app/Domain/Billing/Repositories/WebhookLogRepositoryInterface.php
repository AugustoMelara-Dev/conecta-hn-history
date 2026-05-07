<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

/**
 * WebhookLogRepositoryInterface - Domain Port for Webhook Audit & Idempotency
 *
 * Provides idempotency guard and audit trail for external webhooks.
 */
interface WebhookLogRepositoryInterface
{
    /**
     * Check if a webhook event has already been processed.
     *
     * @param  string  $provider  External provider (stripe, paypal, etc.)
     * @param  string  $externalEventId  Provider's unique event ID
     */
    public function hasBeenProcessed(string $provider, string $externalEventId): bool;

    /**
     * Atomically reserve a provider event before side effects run.
     *
     * Returns false when another request already reserved the same event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordFirstSeen(string $provider, string $externalEventId, string $eventType, array $payload): bool;

    /**
     * Log a webhook event for audit trail.
     *
     * @param  string  $provider  External provider
     * @param  string  $externalEventId  Provider's unique event ID
     * @param  string  $eventType  Event type (payment.succeeded, etc.)
     * @param  array<string, mixed>  $payload  Full payload for forensic audit
     */
    public function log(string $provider, string $externalEventId, string $eventType, array $payload): void;
}
