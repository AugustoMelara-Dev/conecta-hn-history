<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBusinessClaim extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'email_verified_at',
        'whatsapp_number',
        'whatsapp_otp_verified_at',
        'evidence_type',
        'evidence_url',
        'evidence_notes',
        'reviewed_by',
        'reviewed_at',
        'decision_notes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'whatsapp_otp_verified_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
