<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class IdempotencyKey extends Model
{
    use Prunable;

    protected $table = 'idempotency_keys';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'response_payload',
        'created_at', // CRITICAL: Allowed for manual locking logic
    ];

    protected $casts = [
        'response_payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subHours(48));
    }
}
