<?php

namespace App\Models;

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
 */
class SubscriptionPayment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'amount',
        'bank',
        'reference_number',
        'proof_path',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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
     * Approve payment and extend tenant subscription
     */
    public function approve(int $reviewerId, int $daysToAdd = 30): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        // Extend tenant subscription
        $tenant = $this->tenant;
        $baseDate = $tenant->next_billing_date ?? now();

        $tenant->update([
            'is_pro' => true,
            'next_billing_date' => $baseDate->addDays($daysToAdd),
            'subscription_status' => 'active',
        ]);
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
