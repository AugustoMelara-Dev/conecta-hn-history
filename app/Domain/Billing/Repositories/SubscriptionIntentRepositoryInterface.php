<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

/**
 * SubscriptionIntentRepositoryInterface - Persistence Port for Subscription Intents
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Abstracts subscription_intents table access from the        ║
 * ║           Application Layer. Supports the Insert-or-Recover pattern   ║
 * ║           for deterministic identity resolution and bulk              ║
 * ║           reconciliation of pending intents.                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface SubscriptionIntentRepositoryInterface
{
    /**
     * Insert a new subscription intent.
     *
     * @param  array<string, mixed>  $attributes  Intent data (id, tenant_id, client_idempotency_key, etc.)
     * @return bool  True if inserted, false if duplicate detected.
     */
    public function insertOrIgnore(array $attributes): bool;

    /**
     * Find an intent by tenant and client idempotency key.
     *
     * @return object|null  The intent row as a stdClass, or null if not found.
     */
    public function findByTenantAndIdempotencyKey(string $tenantId, string $clientIdempotencyKey): ?object;

    /**
     * Update the status and metadata of a subscription intent.
     *
     * @param  string               $intentId    The intent UUID
     * @param  array<string, mixed> $attributes  Columns to update
     */
    public function updateStatus(string $intentId, array $attributes): void;

    /**
     * Retrieve all pending intents older than the given threshold.
     *
     * Used by the reconciliation job to find "zombie" intents.
     *
     * @param  string  $tenantId   Tenant context for isolation
     * @param  string  $olderThan  ISO 8601 datetime string
     * @return iterable<object>  Collection of intent rows as stdClass objects
     */
    public function findPendingOlderThan(string $tenantId, string $olderThan): iterable;
}
