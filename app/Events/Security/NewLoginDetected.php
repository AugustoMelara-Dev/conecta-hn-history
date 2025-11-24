<?php

namespace App\Events\Security;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewLoginDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $ipAddress;

    public $time;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $ipAddress)
    {
        $this->user = $user;
        $this->ipAddress = $ipAddress;
        $this->time = now();
    }
}
