<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'date',
        'unique_visits',
        'total_visits',
    ];

    protected $casts = [
        'date' => 'date',
        'unique_visits' => 'integer',
        'total_visits' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
