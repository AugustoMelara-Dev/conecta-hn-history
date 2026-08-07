<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ImportJob - Job de importación ejecutado por el ImportService.
 */
class ImportJob extends Model
{
    protected $fillable = [
        'tenant_id',
        'entity',
        'file',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'error_message',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'failed_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
