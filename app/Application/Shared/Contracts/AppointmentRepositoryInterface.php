<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Models\Appointment;

interface AppointmentRepositoryInterface
{
    public function findById(string|int $id): ?Appointment;

    public function findWithRelations(string|int $id): ?Appointment;
}
