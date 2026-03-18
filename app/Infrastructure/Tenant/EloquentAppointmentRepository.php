<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Models\Appointment;

class EloquentAppointmentRepository implements AppointmentRepositoryInterface
{
    public function findById(string|int $id): ?Appointment
    {
        return Appointment::find($id);
    }

    public function findWithRelations(string|int $id): ?Appointment
    {
        return Appointment::with(['service', 'provider'])->find($id);
    }
}
