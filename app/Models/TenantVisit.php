<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * TenantVisit - Page view tracking with session fingerprinting
 *
 * Each record represents a unique daily session per visitor.
 * IP addresses are hashed with daily salt for privacy compliance.
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class TenantVisit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'session_hash',
        'user_agent',
        'referer',
        'device_type',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    // ═══════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Generate a privacy-compliant session hash.
     *
     * ARCHITECTURAL GOVERNANCE:
     * - Uses dedicated analytics salt (not app.key) for key rotation safety
     * - Forces UTC timezone for cross-timezone consistency
     * - Same visitor on same tenant = same hash for 24h UTC
     *
     * @param  string  $ip  Visitor IP address
     * @param  string  $userAgent  Browser user agent
     * @param  string|int  $tenantId  Tenant being visited
     * @return string SHA-256 hash for session fingerprinting
     */
    public static function generateSessionHash(
        string $ip,
        string $userAgent,
        string|int $tenantId
    ): string {
        // DETERMINISM: Use UTC timezone explicitly to prevent DST/timezone issues
        $utcDate = now('UTC')->format('Y-m-d');

        // DECOUPLED: Use dedicated analytics salt, not app.key
        // This allows app key rotation without breaking historical analytics
        $analyticsSalt = config('services.analytics.salt', env('ANALYTICS_SALT', 'conecta-analytics-default-salt'));

        $dailySalt = $utcDate.$analyticsSalt;

        $fingerprint = implode('|', [
            $ip,
            $userAgent,
            $tenantId,
            $dailySalt,
        ]);

        return hash('sha256', $fingerprint);
    }

    /**
     * Detect device type from user agent.
     */
    public static function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (preg_match('/bot|crawl|spider|slurp|googlebot/i', $userAgent)) {
            return 'bot';
        }

        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/tablet|ipad|kindle|silk/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Scope: Filter visits within date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Get visits for current month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
