<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'version' => 'integer',
        'id' => 'string', // Ensure cast
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (! $payment->getKey()) {
                $payment->id = (string) Str::uuid();
            }

            if (! isset($payment->idempotency_key) || trim((string) $payment->idempotency_key) === '') {
                $payment->idempotency_key = 'manual-'.$payment->id;
            }

            if ($payment->version === null) {
                $payment->version = 1;
            }
        });
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('tenant_id', $attributes) && ! static::isUnguarded()) {
            throw new MassAssignmentException('Add [tenant_id] to fillable property to allow mass assignment on ['.static::class.'].');
        }

        return parent::fill($attributes);
    }

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
