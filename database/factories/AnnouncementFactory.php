<?php

namespace Database\Factories;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AnnouncementFactory - Uses PHP 8.1 enum for type validation (ADR-002).
 *
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'business_id' => Tenant::factory(),
            // ADR-002: Type from PHP 8.1 enum
            'type' => $this->faker->randomElement(AnnouncementType::values()),
            'content' => '<p>'.$this->faker->sentence().'</p>',
            'is_active' => true,
            // ADR-001: Nullable for "always visible" announcements
            'starts_at_utc' => null,
            'expires_at_utc' => null,
        ];
    }

    /**
     * Indicate the announcement is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate the announcement is a promo.
     */
    public function promo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AnnouncementType::PROMO->value,
            'content' => '<p><strong>🎉 Promoción especial!</strong></p>',
        ]);
    }

    /**
     * Indicate the announcement is a warning.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AnnouncementType::WARNING->value,
            'content' => '<p>⚠️ Aviso importante</p>',
        ]);
    }

    /**
     * Indicate the announcement is urgent (ADR-002: new type example).
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AnnouncementType::URGENT->value,
            'content' => '<p>🚨 Urgente</p>',
        ]);
    }

    /**
     * Indicate the announcement is expired.
     * ADR-001: Uses UTC explicitly.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at_utc' => now()->utc()->subDay(),
        ]);
    }

    /**
     * Indicate the announcement is scheduled for future.
     * ADR-001: Uses UTC explicitly.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at_utc' => now()->utc()->addDay(),
        ]);
    }

    /**
     * Create an announcement visible right now.
     * ADR-001: Uses UTC explicitly.
     */
    public function activeNow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'starts_at_utc' => now()->utc()->subHour(),
            'expires_at_utc' => now()->utc()->addDay(),
        ]);
    }
}
