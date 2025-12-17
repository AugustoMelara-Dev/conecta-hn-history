<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'order_number',
        'status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'subtotal',
        'discount',
        'delivery_fee',
        'total',
        'source',
        'whatsapp_message_id',
        'whatsapp_conversation_id',
        'payment_method',
        'payment_status',
        'payment_reference',
        'paid_at',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'prepared_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'prepared_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ═══ Generación automática de número de orden ═══
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber($order->tenant_id);
            }
        });
    }

    public static function generateOrderNumber(int $tenantId): string
    {
        $prefix = 'ORD';
        $year = date('Y');
        $count = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }

    // ═══ RELACIONES ═══

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ═══ SCOPES ═══

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // ═══ HELPERS ═══

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
