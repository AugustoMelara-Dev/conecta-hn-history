<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicPrintTemplateVersion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dynamic_print_template_versions';

    protected $fillable = [
        'dynamic_print_template_id',
        'version_number',
        'status',
        'blocks_json',
        'metadata_json',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'blocks_json' => 'array',
        'metadata_json' => 'array',
        'published_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DynamicPrintTemplate::class, 'dynamic_print_template_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

