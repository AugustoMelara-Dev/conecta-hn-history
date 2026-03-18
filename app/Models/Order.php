<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 *
 * @property string $status
 * @property string|null $payment_status
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $cancellation_reason
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|string|null $cancelled_by
 * @property-read Tenant $tenant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrderItem> $items
 */
class Order extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    // ═══════════════════════════════════════════════════════════════════════
    // EAGER LOADING POLICY (N+1 Prevention)
    // ═══════════════════════════════════════════════════════════════════════
    // These relationships are ALWAYS loaded to ensure O(1) query complexity
    // in the Tenant Dashboard and API responses.
    // Note: tenant() is provided by BelongsToTenant trait
    protected $with = ['items'];

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
                'discount',
                'delivery_fee',
                'payment_method',
                'payment_status',
                'cancellation_reason',
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
        // ═══════════════════════════════════════════════════════════════════════
        // FOREIGN KEYS (user_id is nullable, set by authenticated orders)
        // ═══════════════════════════════════════════════════════════════════════
        'user_id',
        'location_id',
        'order_number',
        'tracking_uuid', // Real-time tracking token (UUID v4)
        // PII fields
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'customer_notes',
        // Monetary (matching migration)
        'subtotal',
        'discount',
        'delivery_fee',
        'total',
        // Payment (matching migration)
        'payment_method',
        'payment_status',
        'payment_reference',
        'paid_at',
        // Source & Status
        'source',
        'status',
        'internal_notes',
        'cancellation_reason',
        // Workflow timestamps
        'confirmed_at',
        'prepared_at',
        'delivered_at',
        'cancelled_at',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        // Monetary
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        // Workflow timestamps
        'confirmed_at' => 'datetime',
        'prepared_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'paid_at' => 'datetime',
        // NOTE: Encryption removed - column VARCHAR sizes in migration
        // are too small for encrypted values. To enable encryption,
        // first run migration to change columns to TEXT type.
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The user who placed this order (for authenticated orders).
     * Nullable - guest orders have no user.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the event history for this order (Event Sourcing).
     *
     * USAGE:
     * - $order->events()->latest('occurred_at')->get()
     * - $order->events()->where('event_type', 'status_changed')->get()
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('occurred_at');
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
        return 'L. '.number_format((float) $this->total, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::KANBAN_STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_CONFIRMED => 'info',
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_READY => 'success',
            self::STATUS_DELIVERED => 'gray',
            self::STATUS_CANCELLED => 'danger',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getItemsSummaryAttribute(): string
    {
        return $this->items->map(function (OrderItem $item) {
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
    public static function generateOrderNumber(int|string $tenantId): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId) {
            // Acquire exclusive lock on tenant row to serialize order creation
            $tenant = \App\Models\Tenant::whereKey($tenantId)->lockForUpdate()->first();

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
     *
     * Includes real-time tracking URL if tracking_uuid is available.
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
            /** @var OrderItem $item */
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

        // ═══════════════════════════════════════════════════════════════════════
        // REAL-TIME TRACKING CTA (Customer Journey Completion)
        // ═══════════════════════════════════════════════════════════════════════
        // tracking_uuid serves as a bearer token for unauthenticated WebSocket access.
        // This closes the UX loop by giving customers a trackable entry point.
        if ($this->tracking_uuid) {
            $trackingUrl = url('/track/'.$this->tracking_uuid);
            $lines[] = '';
            $lines[] = '─────────────────────────';
            $lines[] = '🔍 *Sigue tu pedido en vivo:*';
            $lines[] = $trackingUrl;
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
        $tenant = $this->tenant;
        $phone = $tenant->whatsapp_number ?? $tenant->phone;

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
            $cleanPhone = '504'.$cleanPhone;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: RFC 3986 compliant URL encoding
        // ═══════════════════════════════════════════════════════════════════════
        $message = rawurlencode($this->buildWhatsAppMessage());

        return "https://wa.me/{$cleanPhone}?text={$message}";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RICH DOMAIN BEHAVIOR (DDD)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cancel this order with business rule enforcement.
     *
     * INVARIANTS PROTECTED:
     * - Cannot cancel orders that are already shipped/delivered/completed
     * - Idempotent: Cancelling an already cancelled order is a no-op
     *
     * @param  string  $reason  Mandatory cancellation reason (audited)
     * @param  int  $cancelledByUserId  User who initiated the cancellation
     *
     * @throws \App\Domain\Sales\Exceptions\OrderCannotBeCancelledException
     */
    public function cancel(string $reason, int $cancelledByUserId): void
    {
        // IDEMPOTENCY: Already cancelled, nothing to do
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        // INVARIANT: Cannot cancel orders that have progressed past Ready
        $terminalStatuses = [self::STATUS_DELIVERED];
        if (in_array($this->status, $terminalStatuses, true)) {
            throw new \App\Domain\Sales\Exceptions\OrderCannotBeCancelledException(
                "Order #{$this->order_number} cannot be cancelled: already {$this->status}"
            );
        }

        // MUTATION: Apply cancellation state
        $this->status = self::STATUS_CANCELLED;
        $this->cancellation_reason = $reason;
        $this->cancelled_at = now();
        $this->cancelled_by = $cancelledByUserId;
    }

    /**
     * Check if this order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Check if payment was made for this order.
     */
    public function wasPaid(): bool
    {
        return $this->payment_status === 'paid' && $this->paid_at !== null;
    }

    /**
     * Transition to a new status.
     *
     * @deprecated Use OrderStateService::transition() instead.
     *             This method bypasses FSM validation and is retained
     *             only for backward compatibility during migration.
     *
     * @throws \RuntimeException Always throws to prevent bypass of FSM.
     */
    public function transitionTo(string $status): bool
    {
        throw new \RuntimeException(
            'DEPRECATED: Use app(OrderStateService::class)->transition($order, $status) instead. '
            .'Direct model transitions bypass FSM validation and side-effects.'
        );
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
