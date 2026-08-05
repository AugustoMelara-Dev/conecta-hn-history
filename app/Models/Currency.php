<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Currency extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $guarded = ['code'];

    protected $casts = [
        'minor_unit' => 'integer',
        'is_active' => 'boolean',
    ];
}
