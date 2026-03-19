<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Inventory\Enums\MovementType;
use App\Infrastructure\Persistence\Eloquent\Traits\AllowsTrustedTenantAssignment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use AllowsTrustedTenantAssignment, HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'location_id',
        'product_id',
        'user_id',
        'quantity_delta',
        'type',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:3',
        'type' => MovementType::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
