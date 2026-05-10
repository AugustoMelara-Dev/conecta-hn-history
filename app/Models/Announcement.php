<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Announcement Model - Scheduled Marketing Banners
 *
 * ADR-001: All datetime columns use _utc suffix, queries use UTC
 * XSS PROTECTION: getContentHtmlAttribute() sanitizes content before display
 *
 * TENANT ISOLATION:
 * This model uses 'business_id' instead of 'tenant_id'.
 * Use scopeForBusiness() for manual tenant filtering instead of BelongsToTenant trait.
 *
 * Types (ADR-002: defined in App\Enums\AnnouncementType):
 * - info: General information (blue)
 * - warning: Important notices (yellow)
 * - promo: Promotional content (green/gradient)
 * - urgent: Critical notices (red)
 *
 * @property int $id
 * @property int $business_id
 * @property string $type
 * @property string $content
 * @property CarbonImmutable $starts_at_utc
 * @property CarbonImmutable $expires_at_utc
 * @property bool $is_active
 */
class Announcement extends Model
{
    use HasFactory;

    // ═══════════════════════════════════════════════════════════════════════════════
    // TYPE CONSTANTS (for backward compatibility with accessors)
    // Prefer using App\Enums\AnnouncementType for new code
    // ═══════════════════════════════════════════════════════════════════════════════

    public const TYPE_INFO = 'info';

    public const TYPE_WARNING = 'warning';

    public const TYPE_PROMO = 'promo';

    public const TYPE_URGENT = 'urgent';

    // ═══════════════════════════════════════════════════════════════════════════════
    // LIFECYCLE HOOKS (Issue #9: Cache auto-invalidation)
    // ═══════════════════════════════════════════════════════════════════════════════

    protected static function booted(): void
    {
        // Auto-invalidate cache on any announcement change
        $invalidateCache = function (Announcement $announcement): void {
            // Guard: Only invalidate if business_id is set
            if ($announcement->business_id === null) {
                return;
            }

            \App\Http\Middleware\HandleInertiaRequests::invalidateAnnouncementsCache(
                $announcement->business_id
            );
        };

        static::saved($invalidateCache);
        static::deleted($invalidateCache);
    }

    protected $fillable = [
        'business_id',
        'type',
        'content',
        'starts_at_utc',
        'expires_at_utc',
        'is_active',
    ];

    protected $casts = [
        'starts_at_utc' => 'immutable_datetime',
        'expires_at_utc' => 'immutable_datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Appended attributes for JSON serialization.
     *
     * PERFORMANCE NOTE: These accessors are computed on every serialization.
     * For bulk operations, use ->setAppends([]) to disable.
     *
     * @var array<string>
     */
    protected $appends = ['content_html'];

    // ═══════════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════════

    public function business(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'business_id');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Scope to a specific business (tenant isolation).
     *
     * USAGE: Announcement::forBusiness($businessId)->active()->get()
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope to currently active and visible announcements.
     * ADR-001: Uses UTC for all datetime comparisons.
     */
    public function scopeActive(Builder $query): Builder
    {
        $nowUtc = CarbonImmutable::now('UTC');

        return $query->where('is_active', true)
            ->where(function ($q) use ($nowUtc) {
                $q->whereNull('starts_at_utc')
                    ->orWhere('starts_at_utc', '<=', $nowUtc);
            })
            ->where(function ($q) use ($nowUtc) {
                $q->whereNull('expires_at_utc')
                    ->orWhere('expires_at_utc', '>=', $nowUtc);
            });
    }

    /**
     * Scope to announcements of a specific type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to upcoming (scheduled but not yet started) announcements.
     * ADR-001: Uses UTC.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        $nowUtc = CarbonImmutable::now('UTC');

        return $query->where('is_active', true)
            ->whereNotNull('starts_at_utc')
            ->where('starts_at_utc', '>', $nowUtc);
    }

    /**
     * Scope to expired announcements.
     * ADR-001: Uses UTC.
     */
    public function scopeExpired(Builder $query): Builder
    {
        $nowUtc = CarbonImmutable::now('UTC');

        return $query->whereNotNull('expires_at_utc')
            ->where('expires_at_utc', '<', $nowUtc);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ACCESSORS - XSS PROTECTION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Get sanitized HTML content.
     *
     * XSS PROTECTION:
     * - Strips all tags except safe formatting tags
     * - Removes event handlers (onclick, onerror, etc.)
     * - Removes javascript: and data: URIs
     * - Removes style attributes to prevent CSS injection
     */
    public function getContentHtmlAttribute(): string
    {
        $content = $this->content ?? '';

        // Allowed tags: basic formatting only
        $allowedTags = '<p><br><strong><b><em><i><u><a><span>';

        // Strip all tags except allowed
        $clean = strip_tags($content, $allowedTags);

        // Remove event handlers (onclick, onerror, onload, etc.)
        $clean = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean) ?? '';

        // Remove javascript:, data:, vbscript: protocols from any attribute
        $clean = preg_replace('/(javascript|data|vbscript)\s*:/i', '', $clean) ?? '';

        // ENHANCED: Specifically sanitize href attributes in anchor tags
        // Matches href="javascript:..." or href='data:...' with various obfuscations
        $clean = preg_replace('/href\s*=\s*["\']?\s*(javascript|data|vbscript)[^"\'>\s]*/i', 'href="#"', $clean) ?? '';

        // Remove style attributes (CSS injection prevention)
        $clean = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $clean) ?? '';

        // Remove any remaining script or iframe tags that might have slipped through
        $clean = preg_replace('/<\s*(script|iframe|object|embed|form)[^>]*>.*?<\/\s*\1\s*>/is', '', $clean) ?? '';

        return trim($clean);
    }

    /**
     * Get CSS color class for announcement type.
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_INFO => 'bg-blue-500',
            self::TYPE_WARNING => 'bg-yellow-500',
            self::TYPE_PROMO => 'bg-gradient-to-r from-green-500 to-emerald-600',
            default => 'bg-gray-500',
        };
    }

    /**
     * Get icon for announcement type.
     */
    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_INFO => 'information-circle',
            self::TYPE_WARNING => 'exclamation-triangle',
            self::TYPE_PROMO => 'gift',
            default => 'bell',
        };
    }

    /**
     * Get human-readable status.
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'Inactivo';
        }

        $now = CarbonImmutable::now('UTC');

        if ($this->starts_at_utc !== null && $now->isBefore($this->starts_at_utc)) {
            return 'Programado';
        }

        if ($this->expires_at_utc !== null && $now->isAfter($this->expires_at_utc)) {
            return 'Expirado';
        }

        return 'Visible';
    }

    /**
     * Get status color for admin panel.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Visible' => 'success',
            'Programado' => 'info',
            'Expirado' => 'gray',
            'Inactivo' => 'danger',
            default => 'gray',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if announcement is currently visible.
     * ADR-001: Uses UTC for comparisons.
     */
    public function isVisible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $nowUtc = CarbonImmutable::now('UTC');

        $startsOk = $this->starts_at_utc === null || $nowUtc->gte($this->starts_at_utc);
        $expiresOk = $this->expires_at_utc === null || $nowUtc->lte($this->expires_at_utc);

        return $startsOk && $expiresOk;
    }
}
