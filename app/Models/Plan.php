<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan - Plan de subscripción (Free, Pro, Enterprise).
 */
class Plan extends Model
{
    use HasUuids;

    protected $table = 'plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'max_users',
        'max_products',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];
}
