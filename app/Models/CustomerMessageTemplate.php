<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMessageTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'template_key',
        'name',
        'description',
        'channel',
        'customer_segment',
        'business_segment_key',
        'automation_key',
        'subject',
        'body',
        'cta_label',
        'template_name',
        'template_language',
        'template_variables',
        'requires_marketing_opt_in',
        'is_active',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'template_variables' => 'array',
        'requires_marketing_opt_in' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getChannelLabelAttribute(): string
    {
        return $this->channel === 'whatsapp' ? 'WhatsApp' : 'Email';
    }

    public function getSegmentLabelAttribute(): string
    {
        return match ($this->customer_segment) {
            'vip' => 'VIP',
            'repeat' => 'Recurrente',
            'reactivation' => 'Reactivacion',
            'booked_service' => 'Con citas',
            'active' => 'Activo',
            'prospect' => 'Prospecto',
            'new' => 'Nuevo',
            null, '' => 'Todos los segmentos',
            default => str((string) $this->customer_segment)->replace('_', ' ')->title()->value(),
        };
    }

    public function getBusinessSegmentLabelAttribute(): string
    {
        if (! filled($this->business_segment_key)) {
            return 'Cualquier rubro';
        }

        $label = config("business-segments.profiles.{$this->business_segment_key}.label");

        return is_string($label) && $label !== ''
            ? $label
            : str((string) $this->business_segment_key)->replace('_', ' ')->title()->value();
    }
}
