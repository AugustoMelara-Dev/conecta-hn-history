<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class EntityDefinition extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'entity_definitions';

    protected $fillable = [
        'tenant_id',
        'source_entity_definition_id',
        'owner_scope',
        'module_key',
        'entity_key',
        'name',
        'business_segment_key',
        'description',
        'status',
        'allow_tenant_overrides',
        'is_locked',
        'active_schema_version_id',
    ];

    protected $casts = [
        'allow_tenant_overrides' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceDefinition(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_entity_definition_id');
    }

    public function schemaVersions(): HasMany
    {
        return $this->hasMany(SchemaVersion::class, 'entity_definition_id');
    }

    public function activeSchemaVersion(): BelongsTo
    {
        return $this->belongsTo(SchemaVersion::class, 'active_schema_version_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FieldDefinition::class, 'entity_definition_id')->orderBy('sort_order');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(LayoutDefinition::class, 'entity_definition_id');
    }

    /**
     * @return Collection<int, FieldDefinition>
     */
    public function indexedFields(): Collection
    {
        /** @var Collection<int, FieldDefinition> $fields */
        $fields = $this->fields()->get()->filter(
            static fn (FieldDefinition $field): bool => $field->is_indexed
        )->values();

        return $fields;
    }
}

