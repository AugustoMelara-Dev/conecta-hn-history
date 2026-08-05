<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CatalogImportBatch extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $fillable = [
        'created_by',
        'kind',
        'source_origin',
        'idempotency_key',
        'payload_hash',
        'correlation_id',
        'source_disk',
        'source_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'status',
        'total_items',
        'processed_items',
        'failed_items',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => CatalogImportStatus::class,
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'failed_items' => 'integer',
        'size_bytes' => 'integer',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return HasMany<CatalogImportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogImportItem::class, 'batch_id');
    }
}
