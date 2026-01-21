<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * UpcasterInterface - Event Evolution Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Transform old event payloads to newer schema versions.        ║
 * ║  PATTERN: Chain of Responsibility for sequential version upgrades.      ║
 * ║  IMMUTABLE: Upcasters MUST NOT mutate global state.                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * When an event's schema evolves (e.g., field renamed, added, or restructured),
 * old serialized payloads in the database become incompatible with the new PHP
 * class definition. Upcasters bridge this gap by transforming V(n) payloads
 * to V(n+1) format BEFORE instantiation.
 *
 * Example chain: V1 payload → UpcasterV1ToV2 → V2 payload → UpcasterV2ToV3 → V3 payload → Instantiate
 */
interface UpcasterInterface
{
    /**
     * Determine if this upcaster can transform the given event type and version.
     *
     * @param string $eventType The canonical event type (e.g., 'auth.user_password_changed')
     * @param int $schemaVersion The source schema version stored in the payload
     * @return bool True if this upcaster handles V{schemaVersion} → V{schemaVersion+1}
     */
    public function supports(string $eventType, int $schemaVersion): bool;

    /**
     * Transform the payload from source version to target version.
     *
     * @param array<string, mixed> $payload The raw payload from storage (JSON decoded)
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
