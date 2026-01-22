<?php

declare(strict_types=1);

namespace App\Domain\Api\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * NotificationRepository - Domain Contract for User Notifications
 *
 * ARCHITECTURE:
 * Abstraction for notification retrival and management.
 */
interface NotificationRepository
{
    /**
     * Get paginated notifications for a user.
     */
    public function getUserNotifications(int $userId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(int $userId, string $notificationId): void;

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId): void;
}
