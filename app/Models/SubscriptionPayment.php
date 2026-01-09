<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * SubscriptionPayment - Payment Transaction Record
 *
 * State machine: pending → approved/rejected
 *
 * @property int $id
 * @property int $tenant_id
 * @property float $amount
 * @property string $bank
 * @property string $reference_number
 * @property string $proof_path
 * @property string $status
 * @property string|null $admin_notes
 * @property int|null $reviewed_by
 * @property \Carbon\Carbon|null $reviewed_at
 *                                            SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class SubscriptionPayment extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'amount',
        'bank',
        'reference_number',
        'proof_path',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
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

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get proof image URL (from private disk)
     */
    public function getProofUrlAttribute(): ?string
    {
        if (! $this->proof_path) {
            return null;
        }

        // Generate temporary signed URL for private file
        return Storage::disk('private')->temporaryUrl(
            $this->proof_path,
            now()->addMinutes(30)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STATE MACHINE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Approve payment and dispatch event for subscription extension.
     *
     * ARCHITECTURAL PATTERN: Event-Driven Architecture with Immutable DTO
     *
     * This method follows Single Responsibility Principle:
     * - Only updates the payment's own state
     * - Dispatches PaymentApproved immutable DTO for side-effects
     * - Uses primitive types (int, not models) for serialization safety
     * - The UpdateTenantSubscription listener handles subscription logic
     *   with pessimistic locking and idempotency guards
     *
     * @param  int  $reviewerId  The admin who approved the payment
     * @param  int  $daysToAdd  Number of subscription days to add (default: 30)
     */
    public function approve(int $reviewerId, int $daysToAdd = 30): void
    {
        // Only update payment's own state (SRP compliance)
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        // Dispatch IMMUTABLE DTO event for decoupled subscription extension
        // Uses factory method with primitives, not model objects
        \App\Events\PaymentApproved::fromPayment(
            tenantId: $this->tenant_id,
            paymentId: $this->id,
            reviewerId: $reviewerId,
            daysToAdd: $daysToAdd
        )->dispatch();
    }

    /**
     * Reject payment with reason
     */
    public function reject(int $reviewerId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'admin_notes' => $reason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
