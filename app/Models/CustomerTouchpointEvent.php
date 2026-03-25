<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTouchpointEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_touchpoint_id',
        'provider',
        'event_type',
        'status',
        'occurred_at',
        'payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function touchpoint(): BelongsTo
    {
        return $this->belongsTo(CustomerTouchpoint::class, 'customer_touchpoint_id');
    }
}
