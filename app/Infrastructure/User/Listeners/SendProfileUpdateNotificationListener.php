<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Listeners;

use App\Domain\User\Events\UserProfileUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendProfileUpdateNotificationListener implements ShouldQueue
{
    public function handle(UserProfileUpdated $event): void
    {
        // Placeholder for notification logic
    }
}
