<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicPrintTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_print_templates';

    protected $fillable = [
        'tenant_id',
        'entity_definition_id',
        'source_print_template_id',
        'owner_scope',
        'template_key',
        'action_key',
        'name',
        'description',
        'blocks_json',
        'placeholder_whitelist',
        'status',
        'is_locked',
        'active_version_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'blocks_json' => 'array',
        'placeholder_whitelist' => 'array',
        'is_locked' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_print_template_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DynamicPrintTemplateVersion::class, 'dynamic_print_template_id');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(DynamicPrintTemplateVersion::class, 'active_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

