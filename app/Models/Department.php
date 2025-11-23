<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    protected $guarded = ['id'];

    /**
     * Get the municipalities for the department.
     */
    public function municipalities():  HasMany
    {
        return $this->hasMany(Municipality::class);
    }

    /**
     * Get all cities through municipalities.
     */
    public function cities(): HasManyThrough
    {
        return $this->hasManyThrough(City::class, Municipality::class);
    }
}
