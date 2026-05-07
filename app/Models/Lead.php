<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lead - Contact form submissions and inquiries
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class Lead extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'customer_profile_id',
        'product_id',
        'source',
        'session_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'message',
        'status',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function getCustomerNameAttribute(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function setCustomerNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->attributes['phone'] ?? null;
    }

    public function setCustomerPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $value;
    }
}
