<?php

declare(strict_types=1);

namespace App\Infrastructure\EventVersioning\Upcasters;

use App\Infrastructure\EventVersioning\UpcasterInterface;

/**
 * PaymentReportedV1ToV2Upcaster - Schema Evolution Example
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Helper to migrate legacy events to modern schema.              ║
 * ║  TRANSFORMATION: Adds 'currency' field (default HNL) if missing.         ║
 * ║  TARGET: V1 -> V2                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class PaymentReportedV1ToV2Upcaster implements UpcasterInterface
{
    public function supports(string $eventType, int $sourceVersion): bool
    {
        // Target: PaymentReported event, transitioning from Version 1
        return $eventType === 'App\\Domain\\Billing\\Events\\PaymentReported' && $sourceVersion === 1;
    }

    public function upcast(array $payload): array
    {
        // V1 had validation issues and sometimes missed 'currency'.
        // V2 requires 'currency'.
        // Default to 'HNL' if missing.

        $newPayload = $payload;

        if (! isset($newPayload['currency'])) {
            $newPayload['currency'] = 'HNL';
        }

        // Rename legacy field if exists (Example)
        if (isset($newPayload['amount_legacy'])) {
            $newPayload['amount'] = $newPayload['amount_legacy'];
            unset($newPayload['amount_legacy']);
        }

        return $newPayload;
    }
}
