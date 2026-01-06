<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
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
        'product_id',
        'customer_name',
        'customer_phone',
        'message',
        'status',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    /**
     * PII ENCRYPTION: GDPR/Compliance - encrypted at rest.
     * Customer contact data is encrypted in the database.
     */
    protected $casts = [
        'customer_phone' => 'encrypted',
        'message' => 'encrypted',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
