<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AiAssistantRun - Log de ejecuciones de AI assistants.
 */
class AiAssistantRun extends Model
{
    use HasUuids;

    protected $table = 'ai_assistant_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'assistant_key',
        'correlation_id',
        'prompt',
        'response',
        'cost_usd',
        'status',
        'duration_ms',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'cost_usd' => 'decimal:6',
        'duration_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
