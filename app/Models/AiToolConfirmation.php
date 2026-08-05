<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $result
 * @property string $confirmation_token
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $executed_at
 * @property CarbonImmutable|null $cancelled_at
 */
final class AiToolConfirmation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [
        'id',
        'tenant_id',
        'actor_id',
        'tool',
        'payload',
        'payload_hash',
        'confirmation_token',
        'confirmation_token_hash',
        'idempotency_key',
        'status',
        'result',
        'expires_at',
        'executed_at',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'payload',
        'payload_hash',
        'confirmation_token',
        'confirmation_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'confirmation_token' => 'encrypted',
            'result' => 'encrypted:array',
            'expires_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
