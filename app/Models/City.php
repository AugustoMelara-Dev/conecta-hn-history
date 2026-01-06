<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'department',
        'hero_image',
        'welcome_text',
        'meta_title',
        'meta_description',
        'population',
        'is_available',
        'is_featured',
        'launch_order',
        'coordinates',
        'municipality_id',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the municipality that owns the city.
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Get the tenants in this city.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Get the products in this city.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
