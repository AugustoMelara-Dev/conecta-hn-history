<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldDefinition extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'field_definitions';

    protected $fillable = [
        'entity_definition_id',
        'field_key',
        'field_type',
        'label',
        'placeholder',
        'help_text',
        'is_required',
        'storage_mode',
        'is_indexed',
        'is_locked',
        'sort_order',
        'default_value',
        'validation_rules',
        'options_json',
        'visibility_json',
        'conditions_json',
        'hooks_json',
        'status',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_indexed' => 'boolean',
        'is_locked' => 'boolean',
        'default_value' => 'array',
        'validation_rules' => 'array',
        'options_json' => 'array',
        'visibility_json' => 'array',
        'conditions_json' => 'array',
        'hooks_json' => 'array',
    ];

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }
}

