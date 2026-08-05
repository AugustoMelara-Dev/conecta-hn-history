<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Quote extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const ACTION_CONVERT_TO_ORDER = 'convert_to_order';

    public const ACTION_CONVERT_TO_RESERVATION = 'convert_to_reservation';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['tenant_id'];

    protected $fillable = [
        'public_id',
        'customer_profile_id',
        'lead_id',
        'crm_deal_id',
        'location_id',
        'created_by_user_id',
        'supersedes_quote_id',
        'status',
        'source',
        'currency',
        'version',
        'subtotal_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'idempotency_key',
        'payload_hash',
        'issued_at',
        'expires_at',
        'accepted_at',
        'rejected_at',
        'cancelled_at',
        'converted_order_id',
        'converted_appointment_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'issued_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'rejected_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function supersededQuote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_quote_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function convertedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'converted_appointment_id');
    }
}
