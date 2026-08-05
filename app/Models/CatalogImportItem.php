<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CatalogImportItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $fillable = [
        'batch_id',
        'row_number',
        'aggregate_type',
        'aggregate_id',
        'duplicate_of_id',
        'product_id',
        'reviewed_by',
        'status',
        'source_disk',
        'source_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'attempts',
        'error_code',
        'error_message',
        'provider',
        'provider_model',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'duration_ms',
        'normalized_payload',
        'source_payload',
        'validation_errors',
        'review_payload',
        'field_confidence',
        'overall_confidence',
        'provider_response_ciphertext',
        'processed_at',
        'reviewed_at',
        'published_at',
    ];

    protected $casts = [
        'status' => CatalogImportStatus::class,
        'size_bytes' => 'integer',
        'attempts' => 'integer',
        'row_number' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:6',
        'duration_ms' => 'integer',
        'normalized_payload' => 'array',
        'source_payload' => 'array',
        'validation_errors' => 'array',
        'review_payload' => 'array',
        'field_confidence' => 'array',
        'overall_confidence' => 'decimal:4',
        'processed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CatalogImportBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
