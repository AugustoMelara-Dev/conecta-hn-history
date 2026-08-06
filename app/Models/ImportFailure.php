<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ImportFailure - Fila fallida durante un ImportJob.
 */
class ImportFailure extends Model
{
    protected $fillable = [
        'import_job_id',
        'row_number',
        'data',
        'errors',
    ];

    protected $casts = [
        'data' => 'array',
        'errors' => 'array',
    ];
}
