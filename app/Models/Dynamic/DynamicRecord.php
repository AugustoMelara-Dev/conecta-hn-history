<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_records';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'entity_definition_id',
        'schema_version_id',
        'owner_id',
        'status',
        'payload_json',
        'computed_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'computed_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }

    public function schemaVersion(): BelongsTo
    {
        return $this->belongsTo(SchemaVersion::class, 'schema_version_id');
    }

    public function indexValues(): HasMany
    {
        return $this->hasMany(DynamicRecordIndexValue::class, 'dynamic_record_id');
    }
}

