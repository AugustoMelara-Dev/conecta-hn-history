<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $morphClass = $user->getMorphClass();

        $notifications = DatabaseNotification::query()
            ->where('notifiable_id', (string) $user->getAuthIdentifier())
            ->where('notifiable_type', $morphClass)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate(10);

        return NotificationResource::collection($notifications);
    }

    public function count(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $morphClass = $user->getMorphClass();

        $unreadCount = DatabaseNotification::query()
            ->where('notifiable_id', (string) $user->getAuthIdentifier())
            ->where('notifiable_type', $morphClass)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'count' => $unreadCount,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $morphClass = $user->getMorphClass();

        DatabaseNotification::query()
            ->where('notifiable_id', (string) $user->getAuthIdentifier())
            ->where('notifiable_type', $morphClass)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success']);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $morphClass = $user->getMorphClass();

        DatabaseNotification::query()
            ->where('notifiable_id', (string) $user->getAuthIdentifier())
            ->where('notifiable_type', $morphClass)
            ->where('id', $id)
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success']);
    }
}
