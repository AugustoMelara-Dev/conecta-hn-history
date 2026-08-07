<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Reporting\Enums\ReportFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReportSchedule - Programación recurrente de un reporte.
 */
class ReportSchedule extends Model
{
    use HasUuids;

    protected $table = 'report_schedules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'report_key',
        'frequency',
        'recipients',
        'filters',
        'format',
        'last_run_at',
        'next_run_at',
        'is_active',
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFrequencyEnumAttribute(): ReportFrequency
    {
        return ReportFrequency::from($this->frequency);
    }

    public function getCronAttribute(): string
    {
        return $this->frequency_enum->cron();
    }

    /**
     * Calcula el next_run_at basado en la frecuencia.
     */
    public function computeNextRun(): \Carbon\CarbonImmutable
    {
        $now = \Carbon\CarbonImmutable::now();
        return match ($this->frequency_enum) {
            ReportFrequency::Daily => $now->addDay()->startOfDay()->addHours(8),
            ReportFrequency::Weekly => $now->addWeek()->startOfWeek()->addHours(8),
            ReportFrequency::Monthly => $now->addMonth()->startOfMonth()->addHours(8),
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForRun($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }
}
