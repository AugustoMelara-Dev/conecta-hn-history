<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OpeningHour - Business Schedule Configuration (Production Hardened)
 *
 * Represents a single day's operating hours for a tenant.
 * Each tenant has 0-6 entries (one per OPEN day of week).
 *
 * TIME STANDARD: Uses Carbon's dayOfWeek convention:
 * - 0 = Sunday, 1 = Monday, ..., 6 = Saturday
 *
 * DATABASE DESIGN:
 * - Columns: id, tenant_id, day_of_week, opening_time, closing_time, timestamps
 * - opening_time and closing_time are NOT NULL
 * - CLOSED DAYS: No record exists for that day_of_week
 * - OPEN DAYS: Record exists with opening_time and closing_time
 *
 * PRODUCTION HARDENING:
 * 1. Timezone: All times forced to America/Tegucigalpa (Honduras UTC-6)
 * 2. Nightclub Logic: Supports overnight hours (20:00 to 02:00) using OR logic
 * 3. Date Sync: Time accessors sync to today's date for accurate comparisons
 */
class OpeningHour extends Model
{
    /**
     * Business timezone - Honduras (UTC-6).
     * CRITICAL: All time comparisons MUST use this timezone.
     */
    public const BUSINESS_TIMEZONE = 'America/Tegucigalpa';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'opening_time',
        'closing_time',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            // opening_time and closing_time handled by Accessors below
        ];
    }

    /**
     * Day of week labels in Spanish.
     */
    public const DAY_LABELS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // TIME ACCESSORS (Timezone-Aware, Date-Synchronized)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the opening time with today's date and business timezone.
     *
     * @param string|null $value Raw time string from database (e.g., "08:00:00")
     * @return Carbon|null Carbon instance with today's date + stored time
     */
    public function getOpeningTimeAttribute(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', $value, self::BUSINESS_TIMEZONE)
            ->setDateFrom(now(self::BUSINESS_TIMEZONE));
    }

    /**
     * Get the closing time with today's date and business timezone.
     *
     * NOTE: Does NOT add a day here - nightclub logic is handled in isOpenAt()
     * to keep accessor pure and predictable.
     *
     * @param string|null $value Raw time string from database (e.g., "17:00:00")
     * @return Carbon|null Carbon instance with today's date + stored time
     */
    public function getClosingTimeAttribute(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', $value, self::BUSINESS_TIMEZONE)
            ->setDateFrom(now(self::BUSINESS_TIMEZONE));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BUSINESS LOGIC
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Check if business crosses midnight (Nightclub Scenario).
     *
     * Example: Opening 20:00, Closing 02:00 = TRUE (crosses midnight)
     * Example: Opening 08:00, Closing 17:00 = FALSE (standard hours)
     *
     * @return bool
     */
    public function crossesMidnight(): bool
    {
        $openingRaw = $this->getOriginal('opening_time');
        $closingRaw = $this->getOriginal('closing_time');

        if ($openingRaw === null || $closingRaw === null) {
            return false;
        }

        // Compare raw time strings (HH:MM:SS format)
        // If closing < opening, it means closing is on the next day
        return $closingRaw < $openingRaw;
    }

    /**
     * Check if the business is currently open based on this schedule entry.
     *
     * TIMEZONE HANDLING:
     * - External $time is converted to business timezone before comparison
     * - All internal times use America/Tegucigalpa
     *
     * NIGHTCLUB LOGIC:
     * - Standard (9AM-5PM): time >= opening AND time <= closing
     * - Overnight (8PM-2AM): time >= opening OR time <= closing
     *
     * @param Carbon|null $time The time to check (any timezone, will be converted)
     * @return bool
     */
    public function isOpenAt(?Carbon $time = null): bool
    {
        // Default to current time in business timezone
        $time = $time 
            ? $time->copy()->setTimezone(self::BUSINESS_TIMEZONE)
            : now(self::BUSINESS_TIMEZONE);

        // Guard: No times defined = closed
        if ($this->opening_time === null || $this->closing_time === null) {
            return false;
        }

        // Extract time components for comparison (ignore date)
        $currentTimeString = $time->format('H:i:s');
        $openingTimeString = $this->getOriginal('opening_time');
        $closingTimeString = $this->getOriginal('closing_time');

        // ═══════════════════════════════════════════════════════════
        // NIGHTCLUB SCENARIO: Closing time < Opening time
        // Example: 20:00 to 02:00 (crosses midnight)
        // Logic: Open if NOW >= 20:00 OR NOW <= 02:00
        // ═══════════════════════════════════════════════════════════
        if ($this->crossesMidnight()) {
            // OR logic: Either after opening (same day) OR before closing (next day)
            return $currentTimeString >= $openingTimeString 
                || $currentTimeString <= $closingTimeString;
        }

        // ═══════════════════════════════════════════════════════════
        // STANDARD SCENARIO: Opening time < Closing time
        // Example: 08:00 to 17:00 (same day)
        // Logic: Open if NOW >= 08:00 AND NOW <= 17:00
        // ═══════════════════════════════════════════════════════════
        return $currentTimeString >= $openingTimeString 
            && $currentTimeString <= $closingTimeString;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED ATTRIBUTES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the tenant that owns this opening hour entry.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the localized day name (e.g., "Lunes").
     */
    public function getDayNameAttribute(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? 'Desconocido';
    }

    /**
     * Format hours for display (e.g., "8:00 AM - 5:00 PM").
     */
    public function getFormattedHoursAttribute(): string
    {
        if ($this->opening_time === null || $this->closing_time === null) {
            return 'Horario no definido';
        }

        $formatted = sprintf(
            '%s - %s',
            $this->opening_time->format('g:i A'),
            $this->closing_time->format('g:i A')
        );

        // Indicate overnight hours for clarity
        if ($this->crossesMidnight()) {
            $formatted .= ' (+1)';
        }

        return $formatted;
    }
}
