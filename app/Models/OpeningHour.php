<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * OpeningHour - Business Schedule Configuration (Deterministic & Pure)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DETERMINISM GUARANTEE                                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANT: Reading an attribute ALWAYS returns the same value.         ║
 * ║                                                                          ║
 * ║  $openingHour->opening_time === $openingHour->opening_time  // ALWAYS   ║
 * ║                                                                          ║
 * ║  Accessors are PURE FUNCTIONS: They transform database values without   ║
 * ║  injecting volatile state like now(). Time comparisons are delegated    ║
 * ║  to isOpenAt() where context is passed explicitly.                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * TIME STANDARD: Uses Carbon's dayOfWeek convention:
 * - 0 = Sunday, 1 = Monday, ..., 6 = Saturday
 *
 * DATABASE DESIGN:
 * - Columns: id, tenant_id, day_of_week, opening_time, closing_time, timestamps
 * - opening_time and closing_time store H:i:s format
 * - CLOSED DAYS: No record exists for that day_of_week
 * - OPEN DAYS: Record exists with opening_time and closing_time
 */
class OpeningHour extends Model
{
    use BelongsToTenant;

    /**
     * Business timezone - Honduras (UTC-6).
     * CRITICAL: All time comparisons MUST use this timezone.
     */
    public const BUSINESS_TIMEZONE = 'America/Tegucigalpa';

    /**
     * Epoch date for time-only Carbon instances (deterministic).
     * Using Unix epoch ensures consistent behavior across all reads.
     */
    private const EPOCH_DATE = '1970-01-01';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'day_of_week',
        'opening_time',
        'closing_time',
    ];

    protected $guarded = ['tenant_id'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
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
    // PURE TIME ACCESSORS (Deterministic - No Volatile State)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the opening time as a Carbon instance.
     *
     * DETERMINISM: Uses epoch date (1970-01-01) to ensure the same value
     * is returned on every read, regardless of the current date.
     *
     * @param  string|null  $value  Raw time string from database (e.g., "08:00:00")
     * @return Carbon|null Carbon instance with epoch date + stored time
     */
    public function getOpeningTimeAttribute(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            self::EPOCH_DATE.' '.$value,
            self::BUSINESS_TIMEZONE
        );
    }

    /**
     * Get the closing time as a Carbon instance.
     *
     * DETERMINISM: Uses epoch date (1970-01-01) to ensure the same value
     * is returned on every read, regardless of the current date.
     *
     * @param  string|null  $value  Raw time string from database (e.g., "17:00:00")
     * @return Carbon|null Carbon instance with epoch date + stored time
     */
    public function getClosingTimeAttribute(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            self::EPOCH_DATE.' '.$value,
            self::BUSINESS_TIMEZONE
        );
    }

    /**
     * Get raw opening time string (H:i:s format).
     * Useful when you need the original database value.
     */
    public function getRawOpeningTime(): ?string
    {
        $value = $this->getRawOriginal('opening_time');

        return $value instanceof Carbon ? $value->format('H:i:s') : $value;
    }

    /**
     * Get raw closing time string (H:i:s format).
     * Useful when you need the original database value.
     */
    public function getRawClosingTime(): ?string
    {
        $value = $this->getRawOriginal('closing_time');

        return $value instanceof Carbon ? $value->format('H:i:s') : $value;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BUSINESS LOGIC (Context Passed Explicitly)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Check if business crosses midnight (Nightclub Scenario).
     *
     * Example: Opening 20:00, Closing 02:00 = TRUE (crosses midnight)
     * Example: Opening 08:00, Closing 17:00 = FALSE (standard hours)
     */
    public function crossesMidnight(): bool
    {
        $openingRaw = $this->getRawOpeningTime();
        $closingRaw = $this->getRawClosingTime();

        if ($openingRaw === null || $closingRaw === null) {
            return false;
        }

        // Compare raw time strings (HH:MM:SS format)
        // If closing < opening, it means closing is on the next day
        return $closingRaw < $openingRaw;
    }

    /**
     * Check if the business is open at a specific time.
     *
     * EXPLICIT CONTEXT: The $time parameter MUST be provided for deterministic
     * behavior. If null, defaults to current time (explicitly stated).
     *
     * TIMEZONE HANDLING:
     * - External $time is converted to business timezone before comparison
     * - All internal times use America/Tegucigalpa
     *
     * NIGHTCLUB LOGIC:
     * - Standard (9AM-5PM): time >= opening AND time <= closing
     * - Overnight (8PM-2AM): time >= opening OR time <= closing
     *
     * @param  Carbon|null  $time  The time to check. If null, uses current time.
     */
    public function isOpenAt(?Carbon $time = null): bool
    {
        // Explicit: Use provided time or current time (documented behavior)
        $checkTime = $time !== null
            ? $time->copy()->setTimezone(self::BUSINESS_TIMEZONE)
            : Carbon::now(self::BUSINESS_TIMEZONE);

        // Guard: No times defined = closed
        $openingRaw = $this->getRawOpeningTime();
        $closingRaw = $this->getRawClosingTime();

        if ($openingRaw === null || $closingRaw === null) {
            return false;
        }

        // Extract time component for comparison (ignore date)
        $currentTimeString = $checkTime->format('H:i:s');

        // ═══════════════════════════════════════════════════════════
        // NIGHTCLUB SCENARIO: Closing time < Opening time
        // Example: 20:00 to 02:00 (crosses midnight)
        // Logic: Open if NOW >= 20:00 OR NOW <= 02:00
        // ═══════════════════════════════════════════════════════════
        if ($this->crossesMidnight()) {
            return $currentTimeString >= $openingRaw
                || $currentTimeString <= $closingRaw;
        }

        // ═══════════════════════════════════════════════════════════
        // STANDARD SCENARIO: Opening time < Closing time
        // Example: 08:00 to 17:00 (same day)
        // Logic: Open if NOW >= 08:00 AND NOW <= 17:00
        // ═══════════════════════════════════════════════════════════
        return $currentTimeString >= $openingRaw
            && $currentTimeString <= $closingRaw;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED ATTRIBUTES
    // ═══════════════════════════════════════════════════════════════════════

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
        $opening = $this->opening_time;
        $closing = $this->closing_time;

        if ($opening === null || $closing === null) {
            return 'Horario no definido';
        }

        $formatted = sprintf(
            '%s - %s',
            $opening->format('g:i A'),
            $closing->format('g:i A')
        );

        // Indicate overnight hours for clarity
        if ($this->crossesMidnight()) {
            $formatted .= ' (+1)';
        }

        return $formatted;
    }
}
