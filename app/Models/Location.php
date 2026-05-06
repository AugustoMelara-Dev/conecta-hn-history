<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'address',
        'phone',
        'contact_name',
        'hours_data',
        'is_active',
        'is_primary',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'hours_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Location $location): void {
            $tenantId = (string) $location->tenant_id;

            if ($tenantId === '') {
                return;
            }

            $hasPrimary = static::query()
                ->where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->exists();

            if (! $hasPrimary) {
                $location->is_primary = true;
            }
        });

        static::saved(function (Location $location): void {
            if (! $location->is_primary) {
                return;
            }

            static::query()
                ->where('tenant_id', $location->tenant_id)
                ->whereKeyNot($location->getKey())
                ->update(['is_primary' => false]);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
