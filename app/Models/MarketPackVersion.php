<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketPackVersion extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'supported_locales' => 'array',
        'supported_currencies' => 'array',
        'supported_timezones' => 'array',
        'capabilities' => 'array',
        'address_schema' => 'array',
        'effective_from' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function marketPack(): BelongsTo
    {
        return $this->belongsTo(MarketPack::class);
    }

    public function supports(string $capability): bool
    {
        return data_get($this->capabilities ?? [], $capability) === true;
    }
}
