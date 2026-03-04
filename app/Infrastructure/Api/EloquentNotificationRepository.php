<?php

declare(strict_types=1);

namespace App\Infrastructure\Api;

use App\Domain\Api\Contracts\NotificationRepository;
use App\Domain\Shared\Contracts\PaginatedResult;
use App\Infrastructure\Shared\EloquentPaginatedResult;
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
     * {@inheritDoc}
     */
    public function getUserNotifications(int $userId, int $perPage = 20): PaginatedResult
    {
        return new EloquentPaginatedResult(
            DatabaseNotification::query()
                ->where('notifiable_id', $userId)
                ->where('notifiable_type', 'App\Models\User')
                ->orderByRaw('read_at IS NULL DESC')
                ->orderByDesc('created_at')
                ->paginate($perPage)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function markAsRead(int $userId, string $notificationId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
    }

    /**
     * {@inheritDoc}
     */
    public function markAllAsRead(int $userId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
