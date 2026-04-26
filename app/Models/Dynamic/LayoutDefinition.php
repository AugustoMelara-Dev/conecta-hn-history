<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayoutDefinition extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'layout_definitions';

    protected $fillable = [
        'entity_definition_id',
        'layout_key',
        'layout_json',
        'status',
    ];

    protected $casts = [
        'layout_json' => 'array',
    ];

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }
}

