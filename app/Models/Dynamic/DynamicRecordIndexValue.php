<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicRecordIndexValue extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'dynamic_record_index_values';

    protected $fillable = [
        'dynamic_record_id',
        'tenant_id',
        'entity_definition_id',
        'field_key',
        'value_string',
        'value_number',
        'value_date',
        'value_boolean',
    ];

    protected $casts = [
        'value_boolean' => 'boolean',
        'value_number' => 'decimal:6',
        'value_date' => 'date',
    ];

    public function dynamicRecord(): BelongsTo
    {
        return $this->belongsTo(DynamicRecord::class, 'dynamic_record_id');
    }

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }
}

