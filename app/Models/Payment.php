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
        'tenant_id',
        'amount',
        'proof_image_path',
        'bank',
        'bank_reference',
        'reference_number',
        'status',
        'reviewed_at',
        'notes',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * Note: tenant_id is in $fillable for explicit mass assignment,
     * but BelongsToTenant trait auto-sets it on creation as well.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Configuracion de Activity Log - Registro Forense
     *
     * DIRECTIVE 3 COMPLIANCE: Full financial observability.
     * All monetary fields must generate audit trail entries.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // AUDIT TRAIL: All financial fields explicitly logged
            ->logOnly([
                'tenant_id',
                'amount',
                'status',
                'bank',              // Financial institution
                'bank_reference',
                'reference_number',  // Transaction reference
                'proof_image_path',  // FORENSIC: Payment proof evidence
                'reviewed_at',
                'notes',             // Administrative notes
            ])
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
