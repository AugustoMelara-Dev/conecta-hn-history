<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * UpcasterInterface - Event Schema Evolution Port
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LAYER: Domain\Shared\Contracts (canonical location)                    ║
 * ║  PURPOSE: Transform old event payloads to newer schema versions.        ║
 * ║  PATTERN: Chain of Responsibility for sequential version upgrades.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Moved from App\Application\Shared\Contracts to enforce A5 (Domain Purity).
 * The Application layer retains a deprecated alias for backward compatibility.
 *
 * @see App\Application\Shared\Contracts\UpcasterInterface (deprecated alias)
 */
interface UpcasterInterface
{
    /**
     * Determine if this upcaster can transform the given event type and version.
     *
     * @param  string  $eventType  The canonical event type (e.g., 'auth.user_password_changed')
     * @param  int  $schemaVersion  The source schema version stored in the payload
     * @return bool True if this upcaster handles V{schemaVersion} → V{schemaVersion+1}
     */
    public function supports(string $eventType, int $schemaVersion): bool;

    /**
     * Transform the payload from source version to target version.
     *
     * @param  array<string, mixed>  $payload  The raw payload from storage (JSON decoded)
     * @return array<string, mixed> The transformed payload compatible with next version
     */
    public function upcast(array $payload): array;

    /**
     * Get the source schema version this upcaster transforms FROM.
     */
    public function fromVersion(): int;

    /**
     * Get the target schema version this upcaster transforms TO.
     */
    public function toVersion(): int;
}
