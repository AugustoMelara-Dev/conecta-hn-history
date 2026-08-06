<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tag - Tag para categorizar contactos/deals/empresas.
 */
class Tag extends Model
{
    use HasUuids;

    protected $table = 'tags';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'color',
        'type',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag')
            ->withTimestamps();
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(CrmDeal::class, 'deal_tag')
            ->withTimestamps();
    }
}
