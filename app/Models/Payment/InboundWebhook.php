<?php

declare(strict_types=1);

namespace App\Models\Payment;

use App\Infrastructure\Persistence\Eloquent\Traits\AllowsTrustedTenantAssignment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InboundWebhook extends Model
{
    use AllowsTrustedTenantAssignment, HasUuids;

    protected $fillable = [
        'tenant_id',
        'provider',
        'event_type',
        'provider_transaction_id',
        'payload',
        'status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
