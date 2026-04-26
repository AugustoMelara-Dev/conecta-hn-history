<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicReindexCheckpoint extends Model
{
    use HasFactory;

    protected $table = 'dynamic_reindex_checkpoints';

    protected $fillable = [
        'dynamic_reindex_run_id',
        'chunk_number',
        'status',
        'cursor_start',
        'cursor_end',
        'processed_count',
        'failed_count',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'chunk_number' => 'integer',
        'processed_count' => 'integer',
        'failed_count' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DynamicReindexRun::class, 'dynamic_reindex_run_id');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(DynamicReindexFailure::class, 'dynamic_reindex_checkpoint_id');
    }
}

