<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Upcasters;

use App\Domain\Shared\Contracts\UpcasterInterface;

/**
 * UserPasswordChangedV1ToV2Upcaster - Example Migration Upcaster
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SCENARIO: In V2, 'session_invalidated' was renamed to 'revoke_all_tokens' ║
 * ║  for semantic clarity. This upcaster transforms V1 payloads to V2.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Historical context:
 * - V1 (2025-01): Initial schema with 'session_invalidated' boolean
 * - V2 (2026-01): Renamed to 'revoke_all_tokens' for API clarity
 */
final readonly class UserPasswordChangedV1ToV2Upcaster implements UpcasterInterface
{
    private const EVENT_TYPE = 'auth.user_password_changed';
    private const FROM_VERSION = 1;
    private const TO_VERSION = 2;

    public function supports(string $eventType, int $schemaVersion): bool
    {
        return $eventType === self::EVENT_TYPE && $schemaVersion === self::FROM_VERSION;
    }

    public function upcast(array $payload): array
    {
        // 1. Extract the old field value
        $sessionInvalidated = $payload['payload']['session_invalidated'] ?? false;

        // 2. Remove old field
        unset($payload['payload']['session_invalidated']);

        // 3. Add renamed field
        $payload['payload']['revoke_all_tokens'] = $sessionInvalidated;

        // 4. Increment schema version to mark transformation complete
        $payload['schema_version'] = self::TO_VERSION;

        return $payload;
    }

    public function fromVersion(): int
    {
        return self::FROM_VERSION;
    }

    public function toVersion(): int
    {
        return self::TO_VERSION;
    }
}
