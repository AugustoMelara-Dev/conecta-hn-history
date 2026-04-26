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

class DynamicReindexRun extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_reindex_runs';

    protected $fillable = [
        'tenant_id',
        'entity_definition_id',
        'status',
        'chunk_size',
        'total_records',
        'processed_records',
        'failed_records',
        'last_record_id',
        'dispatched_by',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'chunk_size' => 'integer',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'failed_records' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entityDefinition(): BelongsTo
    {
        return $this->belongsTo(EntityDefinition::class, 'entity_definition_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(DynamicReindexCheckpoint::class, 'dynamic_reindex_run_id');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(DynamicReindexFailure::class, 'dynamic_reindex_run_id');
    }
}

