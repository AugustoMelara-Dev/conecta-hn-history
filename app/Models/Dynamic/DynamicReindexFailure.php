<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicReindexFailure extends Model
{
    use HasFactory;

    protected $table = 'dynamic_reindex_failures';

    protected $fillable = [
        'dynamic_reindex_run_id',
        'dynamic_reindex_checkpoint_id',
        'dynamic_record_id',
        'chunk_number',
        'error_message',
        'context_json',
        'failed_at',
    ];

    protected $casts = [
        'context_json' => 'array',
        'failed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DynamicReindexRun::class, 'dynamic_reindex_run_id');
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(DynamicReindexCheckpoint::class, 'dynamic_reindex_checkpoint_id');
    }
}

