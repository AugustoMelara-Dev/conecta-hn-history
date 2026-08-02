<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Country extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $guarded = ['code'];

    protected $casts = ['is_active' => 'boolean'];

    public function administrativeAreas(): HasMany
    {
        return $this->hasMany(AdministrativeArea::class, 'country_code', 'code');
    }

    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class, 'country_code', 'code');
    }
}
