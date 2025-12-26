<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Order Model - Transaction Header
 *
 * Represents a customer order with workflow status management.
 * Each order belongs to a tenant and contains multiple items.
 *
 * WORKFLOW:
 * pending → confirmed → processing → ready → delivered
 *                                         → cancelled
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 * COMPLIANCE: Uses LogsActivity trait for GDPR forensic audit trail
 */
class Order extends Model
{
    use BelongsToTenant, LogsActivity, SoftDeletes;

    // ═══════════════════════════════════════════════════════════════════════
    // AUDIT LOGGING CONFIGURATION (Spatie Activity Log)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Configure which attributes to log and how.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'total',
                'subtotal',
                'tax_amount',
                'discount_amount',
                'payment_method',
                'cancel_reason',
                'payment_transaction_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STATUS CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PROCESSING,
        self::STATUS_READY,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    // Kanban column statuses (excludes cancelled)
    public const KANBAN_STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_CONFIRMED => 'Confirmado',
        self::STATUS_PROCESSING => 'En Preparación',
        self::STATUS_READY => 'Listo',
        self::STATUS_DELIVERED => 'Entregado',
    ];

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'customer_notes',
        'subtotal',
        'tax_amount',
        'delivery_fee',
        'discount_amount',
        'total',
        'payment_method',
        // Payment tracking (webhook-populated)
        'payment_transaction_id',
        'payment_confirmed_at',
        'refund_transaction_id',
        'refunded_at',
        // Delivery & Status
        'delivery_type',
        'estimated_ready_at',
        'delivered_at',
        'status',
        'cancel_reason',
        'whatsapp_sent',
        'whatsapp_sent_at',
        // Workflow timestamps
        'confirmed_at',
        'prepared_at',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'whatsapp_sent' => 'boolean',
        'estimated_ready_at' => 'datetime',
        'delivered_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        // Payment tracking
        'payment_method' => PaymentMethod::class,
        'payment_confirmed_at' => 'datetime',
        'refunded_at' => 'datetime',
        // Workflow timestamps
        'confirmed_at' => 'datetime',
        'prepared_at' => 'datetime',
        // PII ENCRYPTION: GDPR/Compliance - encrypted at rest
        'customer_email' => 'encrypted',
        'customer_phone' => 'encrypted',
        'customer_address' => 'encrypted',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // Note: forTenant() is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════

    public function getFormattedTotalAttribute(): string
    {
        return 'L. '.number_format($this->total, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::KANBAN_STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_CONFIRMED => 'info',
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_READY => 'success',
            self::STATUS_DELIVERED => 'gray',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public function getItemsSummaryAttribute(): string
    {
        return $this->items->map(function ($item) {
            return "{$item->quantity}x {$item->product_name}";
        })->implode(', ');
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Generate the next order number for a tenant.
     *
     * CONCURRENCY SAFETY:
     * Uses pessimistic locking (FOR UPDATE) on the Tenant row to serialize
     * concurrent order creation requests. This prevents race conditions where
     * two requests read the same last order number and try to create duplicates.
     *
     * ARCHITECTURAL DECISION:
     * We lock the Tenant row instead of the Order table because:
     * 1. It's a single row lock (minimal contention)
     * 2. Order table could have millions of rows (scanning would be slow)
     * 3. Tenant is always loaded anyway during order creation
     *
     * @param  int  $tenantId  The tenant to generate order number for
     * @return string The next order number (e.g., "#1001")
     */
    public static function generateOrderNumber(int $tenantId): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId) {
            // Acquire exclusive lock on tenant row to serialize order creation
            $tenant = \App\Models\Tenant::lockForUpdate()->find($tenantId);

            if (! $tenant) {
                throw new \RuntimeException("Tenant {$tenantId} not found during order number generation");
            }

            // Now safely read the last order (no race condition possible)
            $lastOrder = static::where('tenant_id', $tenantId)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastOrder) {
                // Extract number from "#1001" format
                $lastNumber = (int) str_replace('#', '', $lastOrder->order_number);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1001; // Start from 1001
            }

            return '#'.$nextNumber;
        });
    }

    /**
     * Build WhatsApp message for this order.
     */
    public function buildWhatsAppMessage(): string
    {
        $lines = [
            "🛒 *Nuevo Pedido {$this->order_number}*",
            '',
            "👤 *Cliente:* {$this->customer_name}",
            "📞 *Teléfono:* {$this->customer_phone}",
        ];

        if ($this->customer_address) {
            $lines[] = "📍 *Dirección:* {$this->customer_address}";
        }

        $lines[] = '';
        $lines[] = '📦 *Productos:*';

        foreach ($this->items as $item) {
            $line = "• {$item->quantity}x {$item->product_name}";
            if ($item->options) {
                $options = collect($item->options)->pluck('option')->implode(', ');
                $line .= " ({$options})";
            }
            $line .= ' - L. '.number_format($item->line_total, 2);
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = "💰 *Total: {$this->formatted_total}*";

        if ($this->customer_notes) {
            $lines[] = '';
            $lines[] = "📝 *Notas:* {$this->customer_notes}";
        }

        return implode("\n", $lines);
    }

    /**
     * Get WhatsApp URL for this order.
     *
     * LOCALIZATION: Honduras Market Adaptation
     * Users culturally input 8-digit local numbers (9999-9999).
     * The wa.me API requires international format (504XXXXXXXX).
     * This method auto-detects and normalizes local numbers.
     *
     * ENCODING: Uses rawurlencode() for RFC 3986 compliance.
     * urlencode() produces '+' for spaces, but rawurlencode()
     * produces '%20', which is required for iOS 15+ deep links.
     */
    public function getWhatsAppUrl(): ?string
    {
        $phone = $this->tenant->whatsapp_number ?? $this->tenant->phone;

        if (! $phone) {
            return null;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: Clean all non-numeric characters (dashes, spaces, parentheses)
        // ═══════════════════════════════════════════════════════════════════════
        $cleanPhone = preg_replace('/\D/', '', $phone);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: HONDURAS LOCALIZATION - Auto-prepend country code
        // ═══════════════════════════════════════════════════════════════════════
        // Honduran mobile/landline numbers are exactly 8 digits.
        // If user entered local format (e.g., "9999-9999" → "99999999"),
        // we auto-prepend the country code 504 for WhatsApp compatibility.
        //
        // PASSTHROUGH: Numbers that are NOT 8 digits are left untouched.
        // This preserves already-international numbers (11-12 digits) and
        // prevents corrupting valid numbers from other countries.
        if (strlen($cleanPhone) === 8) {
            $cleanPhone = '504' . $cleanPhone;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: RFC 3986 compliant URL encoding
        // ═══════════════════════════════════════════════════════════════════════
        $message = rawurlencode($this->buildWhatsAppMessage());

        return "https://wa.me/{$cleanPhone}?text={$message}";
    }

    /**
     * Transition to a new status.
     *
     * Dispatches OrderStatusChangedNotification to customer (async).
     */
    public function transitionTo(string $status): bool
    {
        if (! in_array($status, self::STATUSES)) {
            return false;
        }

        // Capture previous status for notification
        $previousStatus = $this->status;

        $this->status = $status;

        if ($status === self::STATUS_DELIVERED) {
            $this->delivered_at = now();
        }

        $saved = $this->save();

        // ═══════════════════════════════════════════════════════════════════════
        // DISPATCH STATUS CHANGE NOTIFICATION (Async, queued)
        // Notifies customer via email if they provided one
        // The notification class handles defensive checks for null email
        // ═══════════════════════════════════════════════════════════════════════
        if ($saved && $previousStatus !== $status) {
            // Create an anonymous notifiable for the customer email
            $this->notifyCustomerOfStatusChange($previousStatus, $status);
        }

        return $saved;
    }

    /**
     * Notify customer of status change via email.
     *
     * Uses anonymous notifiable pattern since customers aren't User models.
     */
    protected function notifyCustomerOfStatusChange(string $previousStatus, string $newStatus): void
    {
        // Defensive: Skip if no customer email
        if (empty($this->customer_email)) {
            return;
        }

        // Use anonymous notifiable to send to customer email
        \Illuminate\Support\Facades\Notification::route('mail', $this->customer_email)
            ->notify(new \App\Notifications\OrderStatusChangedNotification($this, $previousStatus, $newStatus));
    }
}
