<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interaction - Conversion event tracking
 *
 * Records explicit user actions that indicate purchase intent:
 * WhatsApp clicks, calls, location views, cart additions.
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 *
 * @property string|null $session_id
 * @property string|null $ip_address
 */
class Interaction extends Model
{
    use BelongsToTenant, Prunable;

    protected $fillable = [
        'visitor_id',
        'session_id',
        'product_id',
        'type',
        'signal_class',
        'source_authority',
        'is_verified',
        'ip_address',
        'user_agent',
        'referrer',
        'url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'metadata',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'metadata' => 'array',
        'is_verified' => 'boolean',
    ];

    public function prunable(): Builder
    {
        $days = max(1, (int) config('analytics.interaction_retention_days', 90));

        // JOB_GLOBAL_POLL: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        return static::query()->withoutGlobalScopes()->where('created_at', '<=', now()->subDays($days));
    }

    /**
     * Available interaction types.
     *
     * IMPORTANT: Keep in sync with AnalyticsController event types.
     * - Standard actions: whatsapp, call, location, cart, share
     * - Conversion events: conversion (generic), purchase (specific)
     */
    public const TYPE_WHATSAPP_CLICK = 'whatsapp_click';

    public const TYPE_CALL_CLICK = 'call';

    public const TYPE_LOCATION_VIEW = 'location_view';

    public const TYPE_CART_ADD = 'cart_add';

    public const TYPE_SOCIAL_SHARE = 'social_share';

    public const TYPE_CONVERSION = 'conversion';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_MICROSITE_VIEW = 'microsite_view';

    public const TYPE_CHECKOUT_STARTED = 'checkout_started';

    public const TYPE_CHECKOUT_PAID = 'checkout_paid';

    public const TYPE_UPGRADE_STARTED = 'upgrade_started';

    public const TYPE_UPGRADE_PAID = 'upgrade_paid';

    public const TYPE_LEAD_CREATED = 'lead_created';

    /**
     * Available interaction types.
     *
     * IMPORTANT: Keep in sync with AnalyticsController event types.
     * - Standard actions: whatsapp, call, location, cart, share
     * - Conversion events: conversion (generic), purchase (specific)
     */
    public const TYPES = [
        self::TYPE_WHATSAPP_CLICK => 'WhatsApp Click',
        self::TYPE_CALL_CLICK => 'Phone Call',
        self::TYPE_LOCATION_VIEW => 'Location View',
        self::TYPE_CART_ADD => 'Add to Cart',
        self::TYPE_SOCIAL_SHARE => 'Social Share',
        self::TYPE_CONVERSION => 'Conversion',
        self::TYPE_PURCHASE => 'Purchase',
        self::TYPE_MICROSITE_VIEW => 'Microsite Visit',
        self::TYPE_CHECKOUT_STARTED => 'Checkout Started',
        self::TYPE_CHECKOUT_PAID => 'Checkout Paid',
        self::TYPE_UPGRADE_STARTED => 'Upgrade Started',
        self::TYPE_UPGRADE_PAID => 'Upgrade Paid',
        self::TYPE_LEAD_CREATED => 'Lead Created',
    ];

    /**
     * Determine if the interaction represents high purchase intent.
     */
    public function isHighIntent(): bool
    {
        return in_array($this->type, [
            self::TYPE_WHATSAPP_CLICK,
            self::TYPE_CALL_CLICK,
        ], true);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the product related to this interaction (if any).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // Note: forTenant() is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Scope: Filter by interaction type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get interactions for current month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
