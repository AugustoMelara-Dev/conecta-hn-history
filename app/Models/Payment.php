<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Payment - Subscription payment records
 * Matches table: subscription_payments
 */
class Payment extends Model
{
    use BelongsToTenant, \Illuminate\Database\Eloquent\Factories\HasFactory, LogsActivity;

    protected $table = 'subscription_payments';

    // UUID CONFIGURATION
    public $incrementing = false;     // Disable Auto-Increment

    protected $keyType = 'string';    // ID is String (UUID)

    protected $fillable = [
        'id',             // Explicitly fillable for UUIDs
        'tenant_id',
        'amount',
        'proof_path',
        'bank',
        'reference_number',
        'status',
        'reviewed_at',
        'admin_notes',
        'idempotency_key',
        'version',
    ];

    protected $guarded = ['created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'version' => 'integer',
        'id' => 'string', // Ensure cast
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tenant_id',
                'amount',
                'status',
                'bank',
                'reference_number',
                'proof_path',
                'reviewed_at',
                'admin_notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('pagos');
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->attributes['proof_path'] ?? null;
    }

    public function setProofUrlAttribute(?string $value): void
    {
        $this->attributes['proof_path'] = $value;
    }
}
