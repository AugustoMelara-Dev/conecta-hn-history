<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantMarket extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = ['is_primary' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketPackVersion(): BelongsTo
    {
        return $this->belongsTo(MarketPackVersion::class);
    }

    public function localityCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
