<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AutomationToolPolicy - Política de tools permitidas por agente/usuario.
 */
class AutomationToolPolicy extends Model
{
    use HasUuids;

    protected $table = 'automation_tool_policies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'user_id',
        'allowed_tools',
        'rate_limit_per_hour',
        'is_active',
    ];

    protected $casts = [
        'allowed_tools' => 'array',
        'rate_limit_per_hour' => 'integer',
        'is_active' => 'boolean',
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
