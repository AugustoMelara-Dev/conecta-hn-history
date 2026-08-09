<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Payment;

use App\Domain\Billing\DTOs\WebhookEventClaim;
use App\Domain\Billing\Repositories\WebhookLogRepositoryInterface as BillingWebhookLogRepositoryInterface;
use App\Domain\Payment\Repositories\WebhookLogRepositoryInterface;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * EloquentWebhookLogRepository - Infrastructure Adapter for Webhook Audit Trail
 *
 * Provides idempotency guard via unique compound index (provider, external_event_id).
 */
final class EloquentWebhookLogRepository implements BillingWebhookLogRepositoryInterface, WebhookLogRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function hasBeenProcessed(string $provider, string $externalEventId): bool
    {
        return WebhookLog::where('provider', $provider)
            ->where('external_event_id', $externalEventId)
            ->where('status', 'processed')
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function recordFirstSeen(string $provider, string $externalEventId, string $eventType, array $payload): bool
    {
        try {
            $claim = $this->claim($provider, $externalEventId, $eventType, $payload);

            return $claim !== null && $this->markProcessed($claim);
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
            'payload' => $this->auditPayload($payload),
            'status' => 'processed',
            'attempts' => 1,
            'processing_started_at' => now(),
            'processed_at' => now(),
            'payload_hash' => $this->payloadHash($payload),
        ]);
    }

    public function claim(string $provider, string $externalEventId, string $eventType, array $payload, int $leaseSeconds = 300): ?WebhookEventClaim
    {
        $payloadHash = $this->payloadHash($payload);
        $processingToken = (string) Str::uuid();

        try {
            return DB::transaction(function () use ($provider, $externalEventId, $eventType, $payload, $payloadHash, $processingToken): WebhookEventClaim {
                WebhookLog::create([
                    'provider' => $provider,
                    'external_event_id' => $externalEventId,
                    'event_type' => $eventType,
                    'payload' => $this->auditPayload($payload),
                    'status' => 'processing',
                    'attempts' => 1,
                    'processing_started_at' => now(),
                    'payload_hash' => $payloadHash,
                    'processing_token' => $processingToken,
                ]);

                return new WebhookEventClaim($provider, $externalEventId, $processingToken);
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }

        return DB::transaction(function () use ($provider, $externalEventId, $eventType, $payloadHash, $leaseSeconds, $processingToken): ?WebhookEventClaim {
            /** @var WebhookLog|null $webhookLog */
            $webhookLog = WebhookLog::query()
                ->where('provider', $provider)
                ->where('external_event_id', $externalEventId)
                ->lockForUpdate()
                ->first();

            if ($webhookLog === null) {
                return null;
            }

            if ($webhookLog->status === 'processed') {
                $this->assertEventIsCompatible($webhookLog, $eventType, $payloadHash);

                return null;
            }

            $this->assertEventIsCompatible($webhookLog, $eventType, $payloadHash);

            $reclaimBefore = now()->subSeconds($leaseSeconds);
            $claimable = $webhookLog->status === 'failed'
                || ($webhookLog->status === 'processing'
                    && ($webhookLog->processing_started_at === null || $webhookLog->processing_started_at->lte($reclaimBefore)));

            if (! $claimable) {
                return null;
            }

            $claimed = WebhookLog::query()
                ->whereKey($webhookLog->getKey())
                ->where(function (Builder $query) use ($reclaimBefore): void {
                    $query->where('status', 'failed')
                        ->orWhere(function (Builder $processing) use ($reclaimBefore): void {
                            $processing->where('status', 'processing')
                                ->where(function (Builder $lease) use ($reclaimBefore): void {
                                    $lease->whereNull('processing_started_at')
                                        ->orWhere('processing_started_at', '<=', $reclaimBefore);
                                });
                        });
                })
                ->update([
                    'status' => 'processing',
                    'attempts' => DB::raw('attempts + 1'),
                    'processing_started_at' => now(),
                    'failed_at' => null,
                    'last_error' => null,
                    'processing_token' => $processingToken,
                ]);

            return $claimed === 1
                ? new WebhookEventClaim($provider, $externalEventId, $processingToken)
                : null;
        }, 3);
    }

    public function markProcessed(WebhookEventClaim $claim): bool
    {
        return WebhookLog::query()
            ->where('provider', $claim->provider)
            ->where('external_event_id', $claim->externalEventId)
            ->where('status', 'processing')
            ->where('processing_token', $claim->processingToken)
            ->update([
                'status' => 'processed',
                'processed_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]) === 1;
    }

    public function markFailed(WebhookEventClaim $claim, \Throwable $error): bool
    {
        return WebhookLog::query()
            ->where('provider', $claim->provider)
            ->where('external_event_id', $claim->externalEventId)
            ->where('status', 'processing')
            ->where('processing_token', $claim->processingToken)
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => $this->sanitizeError($error),
            ]) === 1;
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $payload */
    private function auditPayload(array $payload): array
    {
        $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];
        $metadata = is_array($object['metadata'] ?? null)
            ? $object['metadata']
            : (is_array($payload['meta'] ?? null) ? $payload['meta'] : []);

        return [
            'id' => is_scalar($payload['id'] ?? null) ? (string) $payload['id'] : null,
            'type' => is_scalar($payload['type'] ?? $payload['event'] ?? null)
                ? (string) ($payload['type'] ?? $payload['event'])
                : null,
            'object_id' => is_scalar($object['id'] ?? null) ? (string) $object['id'] : null,
            'tenant_id' => is_scalar($metadata['tenant_id'] ?? null) ? (string) $metadata['tenant_id'] : null,
            'subscription_intent_id' => is_scalar($metadata['subscription_intent_id'] ?? null)
                ? (string) $metadata['subscription_intent_id']
                : null,
            'order_id' => is_scalar($metadata['order_id'] ?? null) ? (string) $metadata['order_id'] : null,
            'raw_payload_retained' => false,
        ];
    }

    private function assertEventIsCompatible(WebhookLog $webhookLog, string $eventType, string $payloadHash): void
    {
        if ($webhookLog->event_type !== $eventType) {
            throw new UnexpectedValueException('Webhook event ID was reused with an incompatible event type or payload.');
        }

        if ($webhookLog->payload_hash !== null && ! hash_equals($webhookLog->payload_hash, $payloadHash)) {
            throw new UnexpectedValueException('Webhook event ID was reused with an incompatible event type or payload.');
        }
    }

    private function sanitizeError(\Throwable $error): string
    {
        $message = preg_replace('/[\r\n\t]+/', ' ', $error->getMessage()) ?? 'Webhook processing failed.';
        $message = preg_replace('/(?i)(authorization)\s*:\s*bearer\s+[^\s,;]+/', '$1=[REDACTED]', $message) ?? $message;
        $message = preg_replace('/(?i)(secret|token|password|authorization|api[_-]?key)\s*["\']?\s*[:=]\s*[^\s,;]+/', '$1=[REDACTED]', $message) ?? $message;

        return substr(trim($message), 0, 1000);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '1555', '2067'], true);
    }
}
