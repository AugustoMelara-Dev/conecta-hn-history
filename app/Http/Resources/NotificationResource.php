<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * NotificationResource - API Response Transformation
 *
 * RESPONSIBILITY:
 * Serialize notification data and hydrate signed URLs (JIT).
 *
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \Illuminate\Notifications\DatabaseNotification $notification */
        $notification = $this->resource;
        $data = $notification->data;

        // Hydration Logic moved from Controller to Resource
        $actionUrl = $data['action_url'] ?? null;

        if (($data['type'] ?? '') === 'export_ready' && ! empty($data['file_path'])) {
            try {
                $tenantId = $request->user()?->tenant_id;
                if ($tenantId) {
                    $actionUrl = URL::temporarySignedRoute(
                        'api.exports.download',
                        now()->addMinutes(30),
                        [
                            'path' => base64_encode($data['file_path']),
                            'tenant' => $tenantId,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                // Fail safe
                $actionUrl = null;
            }
        }

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'general',
            'title' => $data['title'] ?? 'Notificación',
            'body' => $data['body'] ?? '',
            'action_url' => $actionUrl,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
            'time_ago' => $notification->created_at->diffForHumans(),
        ];
    }
}
