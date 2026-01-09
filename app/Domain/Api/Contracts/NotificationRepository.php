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
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(int $userId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Mark a specific notification as read.
     *
     * @param int $userId
     * @param string $notificationId
     * @return void
     */
    public function markAsRead(int $userId, string $notificationId): void;

    /**
     * Mark all notifications as read for a user.
     *
     * @param int $userId
     * @return void
     */
    public function markAllAsRead(int $userId): void;
}
