<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use App\Domain\Api\Contracts\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * EloquentNotificationRepository - Infrastructure Implementation
 *
 * RESPONSIBILITY:
 * Encapsulate DatabaseNotification queries.
 */
final class EloquentNotificationRepository implements NotificationRepository
{
    /**
     * @inheritDoc
     */
    public function getUserNotifications(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', 'App\Models\User') // Constraint for safety
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function markAsRead(int $userId, string $notificationId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
    }

    /**
     * @inheritDoc
     */
    public function markAllAsRead(int $userId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
