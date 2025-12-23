<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Payment - Subscription payment records
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class Payment extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'amount',
        'proof_image_path',
        'bank_reference',
        'status',
        'reviewed_at',
        'notes',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Configuracion de Activity Log - Registro Forense
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tenant_id', 'amount', 'status', 'bank_reference', 'reviewed_at', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => "Pago de L. {$this->amount} registrado para {$this->tenant?->name}",
                'updated' => "Pago #{$this->id} actualizado (estado: {$this->status})",
                'deleted' => "Pago #{$this->id} eliminado",
                default => "Pago #{$this->id} {$eventName}",
            })
            ->useLogName('pagos');
    }

    /**
     * Capturar IP del usuario que realiza la accion
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Scope to get pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved payments.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
