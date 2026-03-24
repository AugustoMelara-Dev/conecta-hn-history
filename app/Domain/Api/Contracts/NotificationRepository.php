<?php

declare(strict_types=1);

namespace App\Domain\Api\Contracts;

use App\Domain\Shared\Contracts\PaginatedResult;

/**
 * NotificationRepository - Domain Contract for User Notifications
 *
 * ARCHITECTURE:
 * Abstraction for notification retrieval and management.
 */
interface NotificationRepository
{
    /**
     * Get paginated notifications for a user.
     */
    public function getUserNotifications(int|string $userId, int $perPage = 20): PaginatedResult;

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(int|string $userId, string $notificationId): void;

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int|string $userId): void;
}
