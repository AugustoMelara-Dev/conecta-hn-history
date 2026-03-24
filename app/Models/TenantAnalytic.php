<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TenantAnalytic - Aggregated analytics data per tenant per day.
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation.
 *
 * NOTE:
 * The current database schema still stores legacy analytics columns:
 * - views
 * - unique_visitors
 *
 * The rest of the app already speaks the canonical names:
 * - total_visits
 * - unique_visits
 *
 * Accessors and mutators keep both contracts aligned until the schema is
 * migrated for good.
 */
class TenantAnalytic extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'date',
        'views',
        'whatsapp_clicks',
        'unique_visitors',
        'unique_visits',
        'total_visits',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'date' => 'date',
        'views' => 'integer',
        'whatsapp_clicks' => 'integer',
        'unique_visitors' => 'integer',
        'unique_visits' => 'integer',
        'total_visits' => 'integer',
    ];

    public static function totalVisitsColumn(): string
    {
        return 'views';
    }

    public static function uniqueVisitsColumn(): string
    {
        return 'unique_visitors';
    }

    protected function totalVisits(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): int => (int) ($attributes['views'] ?? $value ?? 0),
            set: fn ($value): array => ['views' => (int) $value],
        );
    }

    protected function uniqueVisits(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): int => (int) ($attributes['unique_visitors'] ?? $value ?? 0),
            set: fn ($value): array => ['unique_visitors' => (int) $value],
        );
    }

    // Relationships
    // Note: tenant() relationship is provided by BelongsToTenant trait.
}
