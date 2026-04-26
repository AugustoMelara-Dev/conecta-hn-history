<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaVersion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'schema_versions';

    protected $fillable = [
        'entity_definition_id',
        'version_number',
        'status',
        'schema_json',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'published_at' => 'datetime',
    ];

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

