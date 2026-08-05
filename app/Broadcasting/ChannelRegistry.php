<?php

declare(strict_types=1);

namespace App\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ChannelRegistry - Single Source of Truth for WebSocket Channel Topology
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE SOURCE OF TRUTH:                                                ║
 * ║    - Channel names defined here ONLY                                    ║
 * ║    - routes/channels.php MUST use these constants                       ║
 * ║    - Broadcast Events MUST use factory methods                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE IN routes/channels.php:
 * ```php
 * use App\Broadcasting\ChannelRegistry;
 *
 * Broadcast::channel(ChannelRegistry::TENANT_CHANNEL_PATTERN, function ($user, $id) {
 *     return (string) $user->tenant_id === (string) $id;
 * });
 * ```
 */
final class ChannelRegistry
{
    // ═══════════════════════════════════════════════════════════════════════
    // CHANNEL PATTERNS (for routes/channels.php authorization)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Pattern for tenant-scoped channels.
     * Use in routes/channels.php for authorization.
     */
    public const TENANT_CHANNEL_PATTERN = 'tenant.{id}';

    /**
     * Pattern for tenant admin-only channels (Security).
     */
    public const TENANT_ADMIN_CHANNEL_PATTERN = 'tenant.{id}.admins';

    /**
     * Pattern for user-specific channels.
     */
    public const USER_CHANNEL_PATTERN = 'user.{id}';

    /**
     * Pattern for order-tracking channels.
     */
    public const ORDER_CHANNEL_PATTERN = 'order.{id}';

    // ═══════════════════════════════════════════════════════════════════════
    // CHANNEL PREFIXES (internal use)
    // ═══════════════════════════════════════════════════════════════════════

    private const TENANT_PREFIX = 'tenant.';

    private const USER_PREFIX = 'user.';

    private const ORDER_PREFIX = 'order.';

    // ═══════════════════════════════════════════════════════════════════════
    // FACTORY METHODS (for Broadcast Events)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a private channel for tenant-scoped events.
     *
     * @param  string  $tenantId  The tenant UUID
     * @return PrivateChannel Channel instance for WebSocket routing
     *
     * @throws InvalidArgumentException If tenantId is not positive
     */
    public static function tenant(string $tenantId): PrivateChannel
    {
        $tenantId = self::normalizeTenantIdentifier($tenantId);

        return new PrivateChannel(self::TENANT_PREFIX.$tenantId);
    }

    /**
     * Get the raw channel name for tenant.
     *
     * @param  string  $tenantId  The tenant UUID
     * @return string Raw channel name (e.g., 'tenant.42')
     */
    public static function tenantName(string $tenantId): string
    {
        $tenantId = self::normalizeTenantIdentifier($tenantId);

        return self::TENANT_PREFIX.$tenantId;
    }

    /**
     * Create a private channel for tenant administrators (Sensitive).
     *
     * @param  string  $tenantId  The tenant UUID
     * @return PrivateChannel Channel instance for WebSocket routing
     */
    public static function tenantAdmins(string $tenantId): PrivateChannel
    {
        $tenantId = self::normalizeTenantIdentifier($tenantId);

        return new PrivateChannel(self::TENANT_PREFIX.$tenantId.'.admins');
    }

    /**
     * Create a private channel for user-specific events.
     *
     * @param  int  $userId  The user identifier (must be > 0)
     * @return PrivateChannel Channel instance for WebSocket routing
     */
    public static function user(int|string $userId): PrivateChannel
    {
        $userId = self::normalizeIdentifier($userId, 'userId');

        return new PrivateChannel(self::USER_PREFIX.$userId);
    }

    /**
     * Get the raw channel name for user.
     *
     * @param  int  $userId  The user identifier (must be > 0)
     * @return string Raw channel name (e.g., 'user.123')
     */
    public static function userName(int|string $userId): string
    {
        $userId = self::normalizeIdentifier($userId, 'userId');

        return self::USER_PREFIX.$userId;
    }

    /**
     * Create a private channel for order-specific tracking.
     *
     * @param  int  $orderId  The order identifier (must be > 0)
     * @return PrivateChannel Channel instance for WebSocket routing
     */
    public static function order(int|string $orderId): PrivateChannel
    {
        $orderId = self::normalizeIdentifier($orderId, 'orderId');

        return new PrivateChannel(self::ORDER_PREFIX.$orderId);
    }

    /**
     * Get the raw channel name for order.
     *
     * @param  int  $orderId  The order identifier (must be > 0)
     * @return string Raw channel name (e.g., 'order.456')
     */
    public static function orderName(int|string $orderId): string
    {
        $orderId = self::normalizeIdentifier($orderId, 'orderId');

        return self::ORDER_PREFIX.$orderId;
    }

    /**
     * Validate that an ID is a positive integer.
     *
     * @throws InvalidArgumentException If ID is not positive
     */
    private static function normalizeIdentifier(int|string $id, string $paramName): string
    {
        $value = (string) $id;

        if ((ctype_digit($value) && (int) $value > 0) || Str::isUuid($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            "{$paramName} must be a positive integer or UUID, got {$value}"
        );
    }

    private static function normalizeTenantIdentifier(string $tenantId): string
    {
        $normalized = strtolower($tenantId);

        if (Str::isUuid($normalized)) {
            return $normalized;
        }

        throw new InvalidArgumentException(
            "tenantId must be a UUID, got {$tenantId}"
        );
    }
}
