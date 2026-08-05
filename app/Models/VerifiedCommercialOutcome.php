<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class VerifiedCommercialOutcome extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'outcome' => CommercialOutcome::class,
        'amount_minor' => 'integer',
        'source_occurred_at' => 'immutable_datetime',
        'source_version' => 'integer',
        'metadata' => 'array',
    ];
}
