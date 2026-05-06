<?php

declare(strict_types=1);

namespace App\Events\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a new appointment is created.
 *
 * Used to trigger notifications (email confirmations, etc.)
 * without blocking the HTTP request.
 */
class AppointmentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }
}
