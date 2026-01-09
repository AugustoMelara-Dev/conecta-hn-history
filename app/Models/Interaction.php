<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interaction - Conversion event tracking
 *
 * Records explicit user actions that indicate purchase intent:
 * WhatsApp clicks, calls, location views, cart additions.
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class Interaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'product_id',
        'type',
        'metadata',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Available interaction types.
     *
     * IMPORTANT: Keep in sync with AnalyticsController event types.
     * - Standard actions: whatsapp, call, location, cart, share
     * - Conversion events: conversion (generic), purchase (specific)
     */
    public const TYPES = [
        'whatsapp_click' => 'WhatsApp Click',
        'call' => 'Phone Call',
        'location_view' => 'Location View',
        'cart_add' => 'Add to Cart',
        'social_share' => 'Social Share',
        'conversion' => 'Conversion',
        'purchase' => 'Purchase',
    ];

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

    /**
     * Record a new interaction (trusted internal factory method).
     *
     * SECURITY NOTE: Uses forceFill() to bypass $guarded protection because
     * this is an internal method, not exposed to HTTP input.
     * The BelongsToTenant trait does NOT auto-inject here because we need
     * explicit tenant context for analytics (visitor may not be authenticated).
     */
    public static function record(
        int $tenantId,
        string $type,
        ?int $productId = null,
        array $metadata = []
    ): self {
        $interaction = new self;
        $interaction->forceFill([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'type' => $type,
            'metadata' => $metadata,
        ]);
        $interaction->save();

        return $interaction;
    }
}
