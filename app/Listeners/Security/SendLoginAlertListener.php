<?php

namespace App\Listeners\Security;

use App\Events\Security\NewLoginDetected;
use App\Mail\Security\LoginAlertMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendLoginAlertListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(NewLoginDetected $event): void
    {
        Mail::to($event->user->email)->send(
            new LoginAlertMail($event->user, $event->ipAddress, $event->time)
        );
    }
}
