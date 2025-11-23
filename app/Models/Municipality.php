<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $guarded = ['id'];

    /**
     * Get the department that owns the municipality.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the cities for the municipality.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
