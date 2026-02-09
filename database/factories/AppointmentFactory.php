<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $scheduledAt = fake()->dateTimeBetween('+1 day', '+1 month');
        $durationMinutes = fake()->randomElement([30, 45, 60]);

        return [
            'tenant_id' => Tenant::factory(),
            'service_id' => Service::factory(),
            'service_provider_id' => ServiceProvider::factory(),
            'user_id' => null,
            'scheduled_at' => $scheduledAt,
            'end_at' => (clone $scheduledAt)->modify("+{$durationMinutes} minutes"),
            'status' => Appointment::STATUS_PENDING,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_notes' => fake()->optional()->sentence(),
            'staff_notes' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => Appointment::STATUS_CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Appointment::STATUS_CANCELLED]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Appointment::STATUS_COMPLETED]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);
    }

    public function walkIn(): static
    {
        return $this->state(fn () => [
            'customer_email' => null,
        ]);
    }
}
